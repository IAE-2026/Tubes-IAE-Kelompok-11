<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CentralIntegrationService
{
    protected $ssoBaseUrl;
    protected $email;
    protected $password;
    protected $nim;

    public function __construct($email = null, $password = null)
    {
        $this->ssoBaseUrl = env('SSO_BASE_URL', 'https://iae-sso.virtualfri.id');
        $this->email = $email ?: env('SSO_EMAIL', 'warga31@ktp.iae.id');
        $this->password = $password ?: env('SSO_PASSWORD', 'KtpDigital2026!');
        $this->nim = env('NIM', '102022400306');
    }

    /**
     * Dapatkan Token SSO (M2M / End-user)
     */
    public function getSsoToken()
    {
        try {
            $response = Http::post($this->ssoBaseUrl . '/api/v1/auth/token', [
                'email' => $this->email,
                'password' => $this->password
            ]);

            if ($response->successful() && isset($response['token'])) {
                return $response['token'];
            }
            
            // Jika struktur json beda, misal ada di data.token
            if ($response->successful() && isset($response['data']['token'])) {
                return $response['data']['token'];
            }

            Log::error('SSO Token Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('SSO Token Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Kirim Audit Log via SOAP
     */
    public function sendSoapAudit($token, $action, $details)
    {
        $soapUrl = env('SOAP_BASE_URL', $this->ssoBaseUrl) . '/soap/v1/audit';

        $xmlPayload = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:aud="http://iae.central.mock/audit">
   <soapenv:Header/>
   <soapenv:Body>
      <aud:SubmitAudit>
         <aud:NIM>{$this->nim}</aud:NIM>
         <aud:Action>{$action}</aud:Action>
         <aud:Details>{$details}</aud:Details>
      </aud:SubmitAudit>
   </soapenv:Body>
</soapenv:Envelope>
XML;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'text/xml'
            ])->send('POST', $soapUrl, [
                'body' => $xmlPayload
            ]);

            if (!$response->successful()) {
                Log::error('SOAP Audit Failed: ' . $response->body());
            } else {
                Log::info('SOAP Audit Response: ' . $response->body());
            }
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SOAP Audit Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish Message ke RabbitMQ (via HTTP API Mock Server)
     */
    public function publishRabbitMqMessage($token, $routingKey, $messageData)
    {
        $rabbitUrl = env('RABBITMQ_HTTP_BASE_URL', $this->ssoBaseUrl) . '/api/v1/messages/publish';
        $exchange = env('RABBITMQ_EXCHANGE', 'iae.central.exchange');

        try {
            $response = Http::withToken($token)->post($rabbitUrl, [
                'exchange' => $exchange,
                'routing_key' => $routingKey,
                'message' => $messageData
            ]);

            if (!$response->successful()) {
                Log::error('RabbitMQ Publish Failed: ' . $response->body());
            } else {
                Log::info('RabbitMQ Publish Response: ' . $response->body());
            }
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('RabbitMQ Publish Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sinkronisasi Total (SSO -> RabbitMQ -> SOAP)
     */
    public function syncRoomBookmark($bookmark)
    {
        $status = [
            'sso' => 'failed',
            'rabbitmq' => 'skipped',
            'soap' => 'skipped'
        ];

        // 1. Dapatkan Token
        $token = $this->getSsoToken();
        
        if (!$token) {
            Log::error('Sinkronisasi Gagal: Tidak bisa mendapatkan Token SSO.');
            return $status;
        }
        $status['sso'] = 'success';

        $details = "User men-bookmark kamar dengan ID: " . $bookmark->room_id;

        // 2. Publish ke RabbitMQ (Event-Driven)
        $mqSuccess = $this->publishRabbitMqMessage($token, 'room.bookmarked', [
            'event' => 'ROOM_BOOKMARK_CREATED',
            'nim' => $this->nim,
            'data' => $bookmark->toArray()
        ]);
        $status['rabbitmq'] = $mqSuccess ? 'success' : 'failed';

        // 3. Kirim Audit Trail (SOAP)
        $soapSuccess = $this->sendSoapAudit($token, 'ROOM_BOOKMARK', $details);
        $status['soap'] = $soapSuccess ? 'success' : 'failed';

        return $status;
    }
}
