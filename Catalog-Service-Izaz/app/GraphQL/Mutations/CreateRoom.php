<?php

namespace App\GraphQL\Mutations;

use App\Models\Room;
use App\Services\RabbitMqPublisherService;
use App\Services\SoapAuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GraphQL\Error\Error;

/**
 * CreateRoom Mutation Resolver
 *
 * Handles the `createRoom` GraphQL mutation — the full enterprise integration flow:
 * 1. Validates the input arguments.
 * 2. Persists the new room to the database.
 * 3. Sends a SOAP audit log to the external IAE service (Modul 2).
 * 4. Publishes a RabbitMQ event notification via HTTP bridge (Modul 3).
 * 5. Returns the created room along with integration status.
 *
 * Requires SSO authentication (enforced by @guard in the schema).
 */
class CreateRoom
{
    /**
     * The SOAP audit service (Modul 2).
     */
    protected SoapAuditService $soapAuditService;

    /**
     * The RabbitMQ publisher service (Modul 3).
     */
    protected RabbitMqPublisherService $rabbitMqService;

    public function __construct(
        SoapAuditService $soapAuditService,
        RabbitMqPublisherService $rabbitMqService
    ) {
        $this->soapAuditService = $soapAuditService;
        $this->rabbitMqService  = $rabbitMqService;
    }

    /**
     * Resolve the createRoom mutation.
     *
     * @param  null   $_     Unused root value.
     * @param  array  $args  The mutation arguments (name, location, price, description).
     * @return array         The created room data + SOAP audit + RabbitMQ status.
     *
     * @throws Error  If validation fails.
     */
    public function __invoke($_, array $args): array
    {
        // ── Step 1: Validate input ──
        $validator = Validator::make($args, [
            'name'        => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'description' => 'sometimes|string|max:2000',
        ]);

        if ($validator->fails()) {
            throw new Error('Validation failed: ' . $validator->errors()->first());
        }

        // ── Step 2: Create the room in the database ──
        $room = Room::create([
            'name'        => $args['name'],
            'location'    => $args['location'],
            'price'       => $args['price'],
            'description' => $args['description'] ?? '',
        ]);

        Log::info('[CreateRoom] Room created successfully.', [
            'room_id'  => $room->id,
            'name'     => $room->name,
            'location' => $room->location,
        ]);

        $userEmail = Auth::user()?->email ?? 'anonymous';

        // ── Step 3: Send SOAP audit log (Modul 2) ──
        $auditResult = $this->soapAuditService->logRoomCreated($room, $userEmail);

        Log::info('[CreateRoom] SOAP audit result.', [
            'success'        => $auditResult['success'],
            'receipt_number' => $auditResult['receipt_number'] ?? 'N/A',
        ]);

        // ── Step 4: Publish RabbitMQ event (Modul 3) ──
        // Wrapped in try-catch so a RabbitMQ failure never breaks the main transaction.
        // Passes the SOAP receipt number so the dashboard can link both records.
        $rabbitMqPublished = false;
        $rabbitMqError     = null;
        $soapReceiptNumber = $auditResult['receipt_number'] ?? null;
        try {
            $mqResult = $this->rabbitMqService->publishRoomCreated($room, $userEmail, $soapReceiptNumber);
            $rabbitMqPublished = $mqResult['success'];
            $rabbitMqError     = $mqResult['error'] ?? null;

            Log::info('[CreateRoom] RabbitMQ publish result.', [
                'success' => $mqResult['success'],
                'error'   => $mqResult['error'] ?? 'none',
            ]);
        } catch (\Throwable $e) {
            // Graceful degradation — log but don't fail the mutation
            $rabbitMqError = $e->getMessage();
            Log::error('[CreateRoom] RabbitMQ publish exception (non-fatal).', [
                'error' => $e->getMessage(),
            ]);
        }

        // ── Step 5: Return combined result ──
        return [
            'room'               => $room,
            'audit_success'      => $auditResult['success'],
            'receipt_number'     => $auditResult['receipt_number'],
            'audit_error'        => $auditResult['error'],
            'rabbitmq_published' => $rabbitMqPublished,
            'rabbitmq_error'     => $rabbitMqError,
        ];
    }
}
