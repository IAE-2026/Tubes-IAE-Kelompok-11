<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoapLoggingService
{
    protected SsoIntegrationService $ssoService;
    protected string $soapUrl;
    protected string $teamId;

    public function __construct(SsoIntegrationService $ssoService)
    {
        $this->ssoService = $ssoService;
        $this->soapUrl = env('CENTRAL_SOAP_AUDIT_URL', 'https://iae-sso.virtualfri.id/soap/v1/audit');
        $this->teamId = env('CENTRAL_TEAM_ID', 'TEAM-11');
    }

    public function sendSoapAudit(string $activityName, array $logData): string
    {
        $token = $this->ssoService->getServiceToken();
        $logContentJson = json_encode($logData);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">' . "\n" .
            '  <soap:Body>' . "\n" .
            '    <iae:AuditRequest>' . "\n" .
            '      <iae:TeamID>' . htmlspecialchars($this->teamId) . '</iae:TeamID>' . "\n" .
            '      <iae:ActivityName>' . htmlspecialchars($activityName) . '</iae:ActivityName>' . "\n" .
            '      <iae:LogContent><![CDATA[' . $logContentJson . ']]></iae:LogContent>' . "\n" .
            '    </iae:AuditRequest>' . "\n" .
            '  </soap:Body>' . "\n" .
            '</soap:Envelope>';

        Log::info("Sending SOAP Audit log for activity: {$activityName}...");

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
        ])->withToken($token)
          ->withBody($xml, 'text/xml')
          ->post($this->soapUrl);

        if ($response->failed()) {
            Log::error("SOAP Audit service call failed: " . $response->body());
            throw new \Exception('Failed to send SOAP audit log: ' . $response->status());
        }

        $xmlString = $response->body();

        try {
            $doc = new \DOMDocument();
            $doc->loadXML($xmlString, LIBXML_NOERROR | LIBXML_NOWARNING);
            
            $statusTags = $doc->getElementsByTagName('Status');
            $receiptTags = $doc->getElementsByTagName('ReceiptNumber');

            $status = $statusTags->length > 0 ? $statusTags->item(0)->nodeValue : null;
            $receiptNumber = $receiptTags->length > 0 ? $receiptTags->item(0)->nodeValue : null;

            if (strtoupper($status) !== 'SUCCESS') {
                throw new \Exception('SOAP audit status indicates failure: ' . $status);
            }

            if (!$receiptNumber) {
                throw new \Exception('SOAP audit succeeded but no ReceiptNumber was returned.');
            }

            Log::info("SOAP Audit succeeded. ReceiptNumber: {$receiptNumber}");
            return $receiptNumber;
        } catch (\Exception $e) {
            Log::error("Failed to parse SOAP response: " . $e->getMessage() . ". Response body: " . $xmlString);
            throw new \Exception('Error parsing SOAP audit response: ' . $e->getMessage());
        }
    }
}
