<?php

namespace App\Http\Controllers;

use App\Service\SlotService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HoldController extends Controller
{

    private SlotService $service;

    public function __construct(SlotService $service)
    {
        $this->service = $service;
    }

    public function createHold(Request $request, int $id)
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            return $this->service->createHold($id, $idempotencyKey);
        } catch (\Throwable $exception) {
            return response()->json(['error' => true, 'message' => $exception->getMessage()], $exception->getCode() ?: 500);
        }
    }

    public function confirmHold(int $id)
    {
        return ['ok'];
    }

    public function deleteHold(int $id)
    {
        return ['ok'];
    }
}
