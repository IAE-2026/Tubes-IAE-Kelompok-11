<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RabbitMqPublisherService;
use App\Services\SoapAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class RoomController extends Controller
{
    #[OA\Get(
        path: '/rooms',
        summary: 'Menampilkan daftar kamar (filter berdasarkan lokasi).',
        tags: ['Rooms'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'location', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filter berdasarkan lokasi (contoh: Bali)'),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Filter berdasarkan tanggal ketersediaan (YYYY-MM-DD)')
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 401, description: 'Unauthorized — invalid or missing Bearer token')
        ]
    )]
    public function index(Request $request)
    {
        $query = Room::query();

        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->query('location') . '%');
        }

        $rooms = $query->get();

        return $this->successResponse($rooms, 'Data retrieved successfully');
    }

    #[OA\Get(
        path: '/rooms/{id}',
        summary: 'Membuka detail lengkap satu kamar (foto, fasilitas, deskripsi).',
        tags: ['Rooms'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Data retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Room not found')
        ]
    )]
    public function show($id)
    {
        $room = Room::with('addons')->find($id);

        if (!$room) {
            return $this->errorResponse('Room not found', null, 404);
        }

        return $this->successResponse($room, 'Data retrieved successfully');
    }

    /**
     * Add a new room to the catalog + full enterprise integration.
     *
     * POST /api/v1/rooms
     *
     * This is the main state-changing endpoint. After saving the room:
     * 1. Calls SoapAuditService (Modul 2) → gets a receipt number.
     * 2. Calls RabbitMqPublisherService (Modul 3) → broadcasts the event.
     * 3. Returns the room data with both integration statuses.
     */
    #[OA\Post(
        path: '/rooms',
        summary: 'Menambahkan kamar baru ke katalog + SOAP audit + RabbitMQ event',
        tags: ['Rooms'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'location', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Kamar VIP'),
                    new OA\Property(property: 'location', type: 'string', example: 'Bandung'),
                    new OA\Property(property: 'price', type: 'number', example: 500000),
                    new OA\Property(property: 'description', type: 'string', example: 'Kamar VIP dengan pemandangan gunung')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Room created with full integration status'),
            new OA\Response(response: 401, description: 'Unauthorized — invalid or missing Bearer token'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(
        Request $request,
        SoapAuditService $soapAuditService,
        RabbitMqPublisherService $rabbitMqService
    ) {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'description' => 'sometimes|string|max:2000',
        ]);

        // ── Step 1: Create the room in the database ──
        $room = Room::create([
            'name'        => $validated['name'],
            'location'    => $validated['location'],
            'price'       => $validated['price'],
            'description' => $validated['description'] ?? '',
        ]);

        Log::info('[RoomController] Room created via REST.', [
            'room_id' => $room->id,
            'name'    => $room->name,
        ]);

        $userEmail = Auth::user()?->email ?? 'anonymous';

        // ── Step 2: SOAP Audit Log (Modul 2) ──
        $auditResult = $soapAuditService->logRoomCreated($room, $userEmail);

        Log::info('[RoomController] SOAP audit result.', [
            'success'        => $auditResult['success'],
            'receipt_number' => $auditResult['receipt_number'] ?? 'N/A',
        ]);

        // ── Step 3: RabbitMQ Event (Modul 3) ──
        $rabbitMqPublished = false;
        $rabbitMqError     = null;
        $soapReceiptNumber = $auditResult['receipt_number'] ?? null;
        try {
            $mqResult = $rabbitMqService->publishRoomCreated($room, $userEmail, $soapReceiptNumber);
            $rabbitMqPublished = $mqResult['success'];
            $rabbitMqError     = $mqResult['error'] ?? null;

            Log::info('[RoomController] RabbitMQ publish result.', [
                'success' => $mqResult['success'],
                'error'   => $mqResult['error'] ?? 'none',
            ]);
        } catch (\Throwable $e) {
            $rabbitMqError = $e->getMessage();
            Log::error('[RoomController] RabbitMQ publish exception (non-fatal).', [
                'error' => $e->getMessage(),
            ]);
        }

        // ── Step 4: Build response ──
        $responseData = $room->toArray();
        $responseData['audit_success']      = $auditResult['success'];
        $responseData['receipt_number']      = $soapReceiptNumber;
        $responseData['audit_error']         = $auditResult['error'];
        $responseData['rabbitmq_published']  = $rabbitMqPublished;
        $responseData['rabbitmq_error']      = $rabbitMqError;

        return $this->successResponse($responseData, 'Room created and integration completed', 201);
    }
}
