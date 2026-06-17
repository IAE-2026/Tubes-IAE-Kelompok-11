<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * SoapAuditService — SOAP XML Client for External Audit Logging
 *
 * Sends structured audit trail records to the central IAE SOAP endpoint
 * whenever a critical, state-changing transaction occurs (e.g., a new room
 * is added to the catalog).
 *
 * Responsibilities:
 * 1. Construct a well-formed SOAP XML Envelope from JSON data.
 * 2. POST the XML to the external SOAP audit endpoint with Bearer auth.
 * 3. Parse the XML response and extract the ReceiptNumber for confirmation.
 *
 * SOAP Endpoint : POST https://iae-sso.virtualfri.id/soap/v1/audit
 * Auth          : Bearer Token (forwarded from the authenticated SSO user)
 * Content-Type  : text/xml; charset=utf-8
 */
class SoapAuditService
{
    /**
     * The full SOAP endpoint URL.
     */
    protected string $soapUrl;

    /**
     * The Team ID to include in every audit request.
     */
    protected string $teamId;

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
        $this->soapUrl  = config('soap.endpoint', 'https://iae-sso.virtualfri.id/soap/v1/audit');
        $this->teamId   = config('soap.team_id', 'TEAM-11');
        $this->tokenUrl = config('soap.token_url', 'https://iae-sso.virtualfri.id/api/v1/auth/token');
    }

    /**
     * Obtain an M2M (Machine-to-Machine) JWT from the IAE auth endpoint.
     *
     * @return array{success: bool, token: string|null, error: string|null}
     */
    protected function fetchM2mToken(): array
    {
        Log::info('[SOAP Audit] Fetching M2M token.', ['endpoint' => $this->tokenUrl]);

        try {
            $response = Http::timeout(10)
                ->post($this->tokenUrl, [
                    'api_key' => $this->apiKey,
                ]);

            if ($response->failed()) {
                Log::error('[SOAP Audit] M2M token request failed.', [
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
                Log::error('[SOAP Audit] M2M token not found in response.', [
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'token'   => null,
                    'error'   => 'M2M token not found in auth response: ' . $response->body(),
                ];
            }

            Log::info('[SOAP Audit] M2M token obtained successfully.');
            return ['success' => true, 'token' => $m2mToken, 'error' => null];
        } catch (Exception $e) {
            Log::error('[SOAP Audit] Exception fetching M2M token.', ['error' => $e->getMessage()]);
            return ['success' => false, 'token' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send an audit log entry to the external SOAP service.
     *
     * @param  string       $activityName  The activity type (e.g., "RoomAddedToCatalog").
     * @param  array        $logData       The transaction data to embed as JSON in CDATA.
     * @param  string|null  $bearerToken   Ignored — M2M token is now fetched automatically.
     * @return array{success: bool, receipt_number: string|null, raw_response: string|null, error: string|null}
     */
    public function sendAuditLog(string $activityName, array $logData, ?string $bearerToken = null): array
    {
        // ── Step 1: Obtain M2M token ──
        $m2mResult = $this->fetchM2mToken();

        if (!$m2mResult['success']) {
            return [
                'success'        => false,
                'receipt_number' => null,
                'raw_response'   => null,
                'error'          => $m2mResult['error'],
            ];
        }

        $m2mToken = $m2mResult['token'];

        // ── Step 2: Build the SOAP XML Envelope ──
        $xmlBody = $this->buildSoapEnvelope($activityName, $logData);

        Log::info('[SOAP Audit] Sending audit request.', [
            'endpoint'      => $this->soapUrl,
            'activity_name' => $activityName,
            'team_id'       => $this->teamId,
        ]);
        Log::debug('[SOAP Audit] Request XML:', ['xml' => $xmlBody]);

        // ── Step 3: Send the POST request ──
        // NOTE: We use ->send() WITHOUT ->retry() to prevent Laravel from
        // throwing a RequestException that truncates the response body.
        try {
            $response = Http::withHeaders([
                    'Content-Type'  => 'text/xml; charset=utf-8',
                    'Authorization' => 'Bearer ' . $m2mToken,
                    'SOAPAction'    => 'SubmitAudit',
                    'X-API-KEY'     => $this->apiKey,
                ])
                ->timeout(15)
                ->send('POST', $this->soapUrl, ['body' => $xmlBody]);

            $rawBody = $response->body();

            Log::info('[SOAP Audit] Response received.', [
                'status' => $response->status(),
            ]);
            Log::debug('[SOAP Audit] Full response body:', ['body' => $rawBody]);

            if ($response->failed()) {
                // Extract the SOAP faultstring so we can surface it clearly
                $faultString = null;
                if (preg_match('/<(?:\w+:)?faultstring>(.*?)<\/(?:\w+:)?faultstring>/s', $rawBody, $fm)) {
                    $faultString = trim($fm[1]);
                }

                Log::error('[SOAP Audit] Request failed.', [
                    'status'      => $response->status(),
                    'faultstring' => $faultString,
                    'full_body'   => $rawBody,
                ]);

                return [
                    'success'        => false,
                    'receipt_number' => null,
                    'raw_response'   => $rawBody,
                    'error'          => 'HTTP ' . $response->status() . ' : ' . $rawBody,
                    'audit_error'    => $rawBody,
                ];
            }

            // ── Parse the response XML ──
            $receiptNumber = $this->parseReceiptNumber($rawBody);

            Log::info('[SOAP Audit] Audit logged successfully.', [
                'receipt_number' => $receiptNumber,
            ]);

            return [
                'success'        => true,
                'receipt_number' => $receiptNumber,
                'raw_response'   => $rawBody,
                'error'          => null,
            ];
        } catch (Exception $e) {
            // If the exception has a response object, extract the full body from it
            $exceptionBody = method_exists($e, 'response') && $e->response
                ? $e->response->body()
                : null;

            Log::error('[SOAP Audit] Exception during SOAP request.', [
                'error'         => $e->getMessage(),
                'response_body' => $exceptionBody,
                'trace'         => $e->getTraceAsString(),
            ]);

            return [
                'success'        => false,
                'receipt_number' => null,
                'raw_response'   => $exceptionBody,
                'error'          => $exceptionBody
                    ? 'HTTP Exception: ' . $exceptionBody
                    : $e->getMessage(),
                'audit_error'    => $exceptionBody ?? $e->getMessage(),
            ];
        }
    }

    /**
     * Build the SOAP XML Envelope string.
     *
     * Constructs a standards-compliant SOAP 1.1 envelope with the required
     * IAE audit namespace and CDATA-wrapped JSON log content.
     *
     * @param  string  $activityName  The activity identifier.
     * @param  array   $logData       Data to serialize as JSON inside CDATA.
     * @return string  The complete XML string.
     */
    protected function buildSoapEnvelope(string $activityName, array $logData): string
    {
        // Encode the log data as JSON for the CDATA section
        $jsonContent = json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Build the XML manually to guarantee exact format control.
        // Using heredoc for readability — no extra whitespace issues.
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:iae="http://iae.central.mock/audit">
    <soap:Header/>
    <soap:Body>
        <iae:AuditRequest>
            <iae:TeamID>{$this->teamId}</iae:TeamID>
            <iae:ActivityName>{$activityName}</iae:ActivityName>
            <iae:LogContent><![CDATA[{$jsonContent}]]></iae:LogContent>
        </iae:AuditRequest>
    </soap:Body>
</soap:Envelope>
XML;

        return $xml;
    }

    /**
     * Parse the SOAP XML response and extract the ReceiptNumber.
     *
     * Handles multiple XML namespace formats gracefully:
     * - Prefixed: <iae:ReceiptNumber>
     * - Unprefixed: <ReceiptNumber>
     * - Falls back to regex extraction if SimpleXML namespace parsing fails.
     *
     * @param  string  $xmlResponse  The raw XML response body.
     * @return string|null  The receipt number, or null if not found.
     */
    protected function parseReceiptNumber(string $xmlResponse): ?string
    {
        if (empty($xmlResponse)) {
            return null;
        }

        try {
            // Suppress XML parsing warnings (external XML may be imperfect)
            $previousErrorSetting = libxml_use_internal_errors(true);

            $xml = simplexml_load_string($xmlResponse);

            if ($xml === false) {
                Log::warning('[SOAP Audit] Failed to parse response XML.', [
                    'errors' => libxml_get_errors(),
                ]);
                libxml_clear_errors();
                libxml_use_internal_errors($previousErrorSetting);

                // Fallback: try regex extraction
                return $this->extractReceiptNumberByRegex($xmlResponse);
            }

            libxml_use_internal_errors($previousErrorSetting);

            // ── Try namespace-aware extraction ──
            // Register the IAE namespace
            $namespaces = $xml->getNamespaces(true);

            foreach ($namespaces as $prefix => $uri) {
                $xml->registerXPathNamespace($prefix ?: 'ns', $uri);
            }

            // Try multiple XPath patterns to find ReceiptNumber
            $xpathPatterns = [
                '//iae:ReceiptNumber',
                '//ns:ReceiptNumber',
                '//*[local-name()="ReceiptNumber"]',
            ];

            foreach ($xpathPatterns as $xpath) {
                $result = $xml->xpath($xpath);
                if (!empty($result)) {
                    return trim((string) $result[0]);
                }
            }

            // ── Fallback: strip namespaces and try direct access ──
            $cleanXml = preg_replace('/(<\/?)(\w+):/', '$1', $xmlResponse);
            $cleanXml = preg_replace('/\s+xmlns:\w+="[^"]*"/', '', $cleanXml);
            $parsed   = simplexml_load_string($cleanXml);

            if ($parsed !== false) {
                // Try to navigate Body -> AuditResponse -> ReceiptNumber
                $body = $parsed->Body ?? null;
                if ($body) {
                    foreach ($body->children() as $child) {
                        if (isset($child->ReceiptNumber)) {
                            return trim((string) $child->ReceiptNumber);
                        }
                    }
                }
            }

            // ── Last resort: regex ──
            return $this->extractReceiptNumberByRegex($xmlResponse);
        } catch (Exception $e) {
            Log::error('[SOAP Audit] Exception while parsing XML response.', [
                'error' => $e->getMessage(),
            ]);
            return $this->extractReceiptNumberByRegex($xmlResponse);
        }
    }

    /**
     * Extract ReceiptNumber using regex as a last-resort fallback.
     *
     * @param  string  $xmlResponse
     * @return string|null
     */
    protected function extractReceiptNumberByRegex(string $xmlResponse): ?string
    {
        // Match <iae:ReceiptNumber>...</iae:ReceiptNumber> or <ReceiptNumber>...</ReceiptNumber>
        if (preg_match('/<(?:\w+:)?ReceiptNumber>(.*?)<\/(?:\w+:)?ReceiptNumber>/s', $xmlResponse, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Convenience method: Log a "Room Added to Catalog" audit event.
     *
     * @param  \App\Models\Room  $room        The newly created room.
     * @param  string|null       $userEmail   The email of the user who performed the action.
     * @param  string|null       $bearerToken Optional explicit token override.
     * @return array
     */
    public function logRoomCreated($room, ?string $userEmail = null, ?string $bearerToken = null): array
    {
        $email = $userEmail ?? Auth::user()?->email ?? 'unknown';

        $logData = [
            'room_name'  => $room->name,
            'room_id'    => $room->id,
            'location'   => $room->location,
            'price'      => $room->price,
            'action'     => 'created',
            'user_email' => $email,
            'timestamp'  => now()->toIso8601String(),
        ];

        return $this->sendAuditLog('RoomAddedToCatalog', $logData, $bearerToken);
    }
}
