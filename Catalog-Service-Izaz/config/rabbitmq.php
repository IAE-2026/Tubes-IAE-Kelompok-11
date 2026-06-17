<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ HTTP Publisher Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the HTTP-based RabbitMQ message publisher.
    | The IAE mock server provides an HTTP bridge API that accepts JSON
    | payloads and forwards them to the actual RabbitMQ exchange.
    |
    */

    // Base URL of the RabbitMQ HTTP publisher API
    'base_url' => env('RABBITMQ_HTTP_BASE_URL', 'https://iae-sso.virtualfri.id'),

    // Target RabbitMQ exchange name
    'exchange' => env('RABBITMQ_EXCHANGE', 'iae.central.exchange'),

];
