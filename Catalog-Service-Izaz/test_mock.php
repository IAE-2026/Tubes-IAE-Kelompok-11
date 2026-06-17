<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client();

try {
    echo "1. Testing SSO...\n";
    $res = $client->post('https://iae-sso.virtualfri.id/api/v1/auth/token', [
        'json' => [
            'email' => 'warga31@ktp.iae.id',
            'password' => 'KtpDigital2026!'
        ]
    ]);
    $ssoBody = json_decode($res->getBody(), true);
    print_r($ssoBody);
    
    $token = $ssoBody['data']['token'] ?? $ssoBody['token'] ?? null;
    echo "\nToken: $token\n";
    
    if ($token) {
        echo "\n2. Testing RabbitMQ...\n";
        try {
            $resMq = $client->post('https://iae-sso.virtualfri.id/api/v1/messages/publish', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ],
                'json' => [
                    'exchange' => 'iae.central.exchange',
                    'routing_key' => 'room.bookmarked',
                    'message' => [
                        'event' => 'ROOM_BOOKMARK_CREATED',
                        'nim' => '102022400306',
                        'data' => ['room_id' => '123']
                    ]
                ]
            ]);
            echo $resMq->getBody() . "\n";
        } catch (\Exception $e) {
            echo "RabbitMQ Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n3. Testing SOAP...\n";
        try {
            $xml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:aud="http://iae.central.mock/audit">
   <soapenv:Header/>
   <soapenv:Body>
      <aud:SubmitAudit>
         <aud:NIM>102022400306</aud:NIM>
         <aud:Action>ROOM_BOOKMARK</aud:Action>
         <aud:Details>Test Room</aud:Details>
      </aud:SubmitAudit>
   </soapenv:Body>
</soapenv:Envelope>
XML;
            $resSoap = $client->post('https://iae-sso.virtualfri.id/soap/v1/audit', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/xml'
                ],
                'body' => $xml
            ]);
            echo $resSoap->getBody() . "\n";
        } catch (\Exception $e) {
            echo "SOAP Error: " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "SSO Error: " . $e->getMessage() . "\n";
}

