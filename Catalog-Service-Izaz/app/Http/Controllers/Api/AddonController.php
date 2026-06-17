<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Addon;
use OpenApi\Attributes as OA;

class AddonController extends Controller
{
    #[OA\Get(
        path: '/addons',
        summary: 'Menampilkan menu tambahan seperti sarapan atau asuransi.',
        tags: ['Addons'],
        security: [['ApiKeyAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Data retrieved successfully')
        ]
    )]
    public function index()
    {
        $addons = Addon::all();

        return $this->successResponse($addons, 'Data retrieved successfully');
    }
}
