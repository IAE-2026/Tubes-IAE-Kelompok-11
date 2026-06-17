<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Guest;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use App\Services\SoapLoggingService;
use App\Services\RabbitMqPublisherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GuestController extends Controller
{
    protected SoapLoggingService $soapLoggingService;
    protected RabbitMqPublisherService $rabbitMqPublisherService;

    public function __construct(SoapLoggingService $soapLoggingService, RabbitMqPublisherService $rabbitMqPublisherService)
    {
        $this->soapLoggingService = $soapLoggingService;
        $this->rabbitMqPublisherService = $rabbitMqPublisherService;
    }
    #[OA\Get(
        path: "/{guestId}",
        summary: "Ambil profil guest",
        security: [["ApiKeyAuth" => []]],
        tags: ["Guest Service (Data Diri Tamu)"],
        parameters: [
            new OA\Parameter(name: "guestId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function show($guestId)
    {
        $guest = Guest::find($guestId);
        if (!$guest) {
            return response()->json(['status' => 'error', 'message' => 'Guest not found'], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => $guest,
            'meta' => ['service_name' => 'Guest-Service', 'api_version' => 'v1']
        ], 200);
    }

    #[OA\Post(
        path: "/profile",
        summary: "Simpan profile",
        security: [["ApiKeyAuth" => []]],
        tags: ["Guest Service (Data Diri Tamu)"],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Aurel"),
                    new OA\Property(property: "email", type: "string", example: "aurel@example.com"),
                    new OA\Property(property: "ktp_number", type: "string", example: "1234567890123456"),
                    new OA\Property(property: "phone_number", type: "string", example: "08123456789")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function storeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'ktp_number' => 'required|string|max:20',
            'phone_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Save / Update local guest profile
            $guest = Guest::updateOrCreate(
                ['ktp_number' => $request->ktp_number],
                $request->only(['name', 'email', 'phone_number'])
            );

            // 2. Perform legacy SOAP Audit log (Orchestration step 1)
            $receiptNumber = $this->soapLoggingService->sendSoapAudit('StoreProfile', [
                'id' => $guest->id,
                'name' => $guest->name,
                'email' => $guest->email,
                'ktp_number' => $guest->ktp_number,
                'phone_number' => $guest->phone_number,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Save Receipt Number to local DB
            $guest->receipt_number = $receiptNumber;
            $guest->save();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to complete critical transaction: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process critical transaction: ' . $e->getMessage(),
                'errors' => null
            ], 500);
        }

        // 3. Broadcast Event to RabbitMQ (Orchestration step 2)
        try {
            $this->rabbitMqPublisherService->publishRabbitMessage('profile.stored', [
                'event' => 'profile.stored',
                'timestamp' => now()->toIso8601String(),
                'team_id' => env('CENTRAL_TEAM_ID', 'TEAM-11'),
                'data' => [
                    'guest_id' => $guest->id,
                    'name' => $guest->name,
                    'email' => $guest->email,
                    'ktp_number' => $guest->ktp_number,
                    'phone_number' => $guest->phone_number,
                    'receipt_number' => $guest->receipt_number,
                    'stored_by' => auth()->user() ? auth()->user()->email : 'system',
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast message to RabbitMQ: " . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile saved and integrated successfully',
            'data' => $guest,
            'meta' => [
                'service_name' => 'Guest-Service',
                'api_version' => 'v1',
                'soap_audit' => 'SUCCESS',
                'receipt_number' => $guest->receipt_number,
            ]
        ], 200);
    }

    #[OA\Post(
        path: "/validate-ktp",
        summary: "Validasi KTP",
        security: [["ApiKeyAuth" => []]],
        tags: ["Guest Service (Data Diri Tamu)"],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "ktp_number", type: "string", example: "1234567890123456")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Valid")
        ]
    )]
    public function validateKtp(Request $request)
    {
        $guest = Guest::where('ktp_number', $request->ktp_number)->first();
        if (!$guest) {
            return response()->json(['status' => 'error', 'message' => 'KTP not found'], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => ['is_valid' => true, 'name' => $guest->name],
            'meta' => ['service_name' => 'Guest-Service', 'api_version' => 'v1']
        ], 200);
    }
}