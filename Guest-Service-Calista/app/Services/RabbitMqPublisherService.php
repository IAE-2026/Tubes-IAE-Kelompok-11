<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMqPublisherService
{
    protected SsoIntegrationService $ssoService;
    protected string $rabbitUrl;

    public function __construct(SsoIntegrationService $ssoService)
    {
        $this->ssoService = $ssoService;
        $this->rabbitUrl = env('CENTRAL_RABBITMQ_BRIDGE_URL', 'https://iae-sso.virtualfri.id/api/v1/messages/publish');
    }


    public function publishRabbitMessage(string $routingKey, array $messageData): array
    {
        $token = $this->ssoService->getServiceToken();

        Log::info("Publishing message to exchange via RabbitMQ bridge with routing key: {$routingKey}...");

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->withToken($token)
          ->post($this->rabbitUrl, [
              'message' => $messageData,
              'routing_key' => $routingKey,
          ]);

        if ($response->failed()) {
            Log::error("Failed to publish RabbitMQ message: " . $response->body());
            throw new \Exception('Failed to publish message via message broker: ' . $response->status());
        }

        Log::info("RabbitMQ message published successfully.");
        return $response->json();
    }
}
