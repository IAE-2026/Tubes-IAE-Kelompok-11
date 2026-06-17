<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * RabbitMqPublisherService — HTTP-based AMQP Event Publisher
 *
 * Publishes asynchronous event notifications to the central RabbitMQ exchange
 * via the IAE HTTP bridge API. This allows department systems to subscribe
 * to catalog events (e.g., room creation) without tight coupling.
 *
 * Endpoint : POST https://iae-sso.virtualfri.id/api/v1/messages/publish
 * Auth     : Bearer Token (forwarded from the authenticated SSO user)
 * Exchange : iae.central.exchange
 */
class RabbitMqPublisherService
{
    /**
     * The HTTP publisher API endpoint.
     */
    protected string $publishUrl;

    /**
     * The target RabbitMQ exchange name.
     */
    protected string $exchange;

    /**
     * The M2M token endpoint URL.
     */
    protected string $tokenUrl;

    /**
     * The API key used for M2M authentication and grade tracking.
     */
    protected string $apiKey = 'KEY-MHS-335';

    public function __construct()
    {
        $baseUrl          = config('rabbitmq.base_url', 'https://iae-sso.virtualfri.id');
        $this->publishUrl = rtrim($baseUrl, '/') . '/api/v1/messages/publish';
        $this->exchange   = config('rabbitmq.exchange', 'iae.central.exchange');
        $this->tokenUrl   = config('rabbitmq.token_url', 'https://iae-sso.virtualfri.id/api/v1/auth/token');
    }

    /**
     * Obtain an M2M (Machine-to-Machine) JWT from the IAE auth endpoint.
     *
     * @return array{success: bool, token: string|null, error: string|null}
     */
    protected function fetchM2mToken(): array
    {
        Log::info('[RabbitMQ] Fetching M2M token.', ['endpoint' => $this->tokenUrl]);

        try {
            $response = Http::timeout(10)
                ->post($this->tokenUrl, [
                    'api_key' => $this->apiKey,
                ]);

            if ($response->failed()) {
                Log::error('[RabbitMQ] M2M token request failed.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [
                    'success' => false,
                    'token'   => null,
                    'error'   => 'M2M token request failed (HTTP ' . $response->status() . '): ' . $response->body(),
                ];
            }

            // Try multiple response shapes: { "token": "..." } or { "data": { "token": "..." } }
            $m2mToken = $response->json('token') ?? $response->json('data.token');

            if (!$m2mToken) {
                Log::error('[RabbitMQ] M2M token not found in response.', [
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'token'   => null,
                    'error'   => 'M2M token not found in auth response: ' . $response->body(),
                ];
            }

            Log::info('[RabbitMQ] M2M token obtained successfully.');
            return ['success' => true, 'token' => $m2mToken, 'error' => null];
        } catch (Exception $e) {
            Log::error('[RabbitMQ] Exception fetching M2M token.', ['error' => $e->getMessage()]);
            return ['success' => false, 'token' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Publish a message to the RabbitMQ exchange via the HTTP bridge.
     *
     * @param  string       $routingKey   The routing key (e.g., "catalog.room.created").
     * @param  array        $message      The event payload (will be sent as JSON).
     * @param  string|null  $bearerToken  Ignored — M2M token is now fetched automatically.
     * @return array{success: bool, response_data: mixed, error: string|null}
     */
    public function publish(string $routingKey, array $payload, ?string $bearerToken = null): array
    {
        // ── Step 1: Obtain M2M token ──
        $m2mResult = $this->fetchM2mToken();

        if (!$m2mResult['success']) {
            return [
                'success'       => false,
                'response_data' => null,
                'error'         => $m2mResult['error'],
            ];
        }

        $m2mToken = $m2mResult['token'];

        // Ensure exchange and routing_key are always present
        $payload['exchange']    = $payload['exchange'] ?? $this->exchange;
        $payload['routing_key'] = $payload['routing_key'] ?? $routingKey;

        // ── Full debug logging ──
        Log::info('[RabbitMQ] Publishing message.', [
            'endpoint'    => $this->publishUrl,
            'exchange'    => $payload['exchange'],
            'routing_key' => $payload['routing_key'],
        ]);
        Log::info('[RabbitMQ] Full request payload:', $payload);

        // ── Step 2: Send the POST request ──
        try {
            $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $m2mToken,
                    'Accept'        => 'application/json',
                    'X-API-KEY'     => $this->apiKey,
                ])
                ->timeout(15)
                ->post($this->publishUrl, $payload);

            $statusCode   = $response->status();
            $rawBody      = $response->body();
            $responseJson = $response->json();

            // ── Always log the full response ──
            Log::info('[RabbitMQ] Response received.', [
                'status' => $statusCode,
                'body'   => $rawBody,
            ]);

            // ── Strict failure check ──
            if ($response->failed()) {
                Log::error('[RabbitMQ] Publish FAILED.', [
                    'http_status' => $statusCode,
                    'response'    => $rawBody,
                ]);
                return [
                    'success'       => false,
                    'response_data' => $responseJson ?? $rawBody,
                    'error'         => "RabbitMQ publish failed (HTTP {$statusCode}): {$rawBody}",
                ];
            }

            Log::info('[RabbitMQ] Message published successfully.', [
                'status'      => $statusCode,
                'routing_key' => $routingKey,
                'response'    => $rawBody,
            ]);

            return [
                'success'       => true,
                'response_data' => $responseJson ?? $rawBody,
                'error'         => null,
            ];
        } catch (Exception $e) {
            Log::error('[RabbitMQ] Exception during publish.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success'       => false,
                'response_data' => null,
                'error'         => $e->getMessage(),
            ];
        }
    }

    /**
     * Publish a "Room Added to Catalog" event.
     *
     * Uses the exact flat payload structure required by the IAE dashboard.
     *
     * @param  \App\Models\Room  $room              The newly created room.
     * @param  string|null       $userEmail          The email of the user who created the room.
     * @param  string|null       $receiptNumber      The SOAP audit receipt number from Modul 2.
     * @param  string|null       $bearerToken        Optional explicit token override.
     * @return array
     */
    public function publishRoomCreated(
        $room,
        ?string $userEmail = null,
        ?string $receiptNumber = null,
        ?string $bearerToken = null
    ): array {
        $payload = [
            'exchange'    => $this->exchange,
            'routing_key' => 'catalog.room.created',
            'message'     => [
                'event'     => 'catalog.room.created',
                'timestamp' => now()->setTimezone('UTC')->format('Y-m-d\TH:i:sP'),
                'data'      => [
                    'room_id'               => $room->id ?? 'unknown',
                    'room_name'             => $room->name ?? 'unknown',
                    'location'              => $room->location ?? 'unknown',
                    'price'                 => $room->price ?? 0,
                    'description'           => $room->description ?? '',
                    'legacy_receipt_number' => $receiptNumber,
                ],
            ],
        ];

        return $this->publish('catalog.room.created', $payload, $bearerToken);
    }
}
