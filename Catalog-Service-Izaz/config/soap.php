<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SOAP Audit Service Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the external SOAP audit logging service.
    | Used by App\Services\SoapAuditService to send audit trail records
    | for critical, state-changing transactions.
    |
    */

    // Full URL of the SOAP audit endpoint
    'endpoint' => env('SOAP_AUDIT_URL', 'https://iae-sso.virtualfri.id/soap/v1/audit'),

    // Team identifier included in every SOAP audit request
    'team_id' => env('SOAP_TEAM_ID', 'TEAM-11'),

];
