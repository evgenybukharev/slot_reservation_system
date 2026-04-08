<?php

namespace App\Http\Controllers;

use App\Service\SlotService;

class AvailabilityController extends Controller
{
    private SlotService $service;

    public function __construct(SlotService $service)
    {
        $this->service = $service;
    }

    public function getAvailability()
    {
        return $this->service->getAvailability();
    }
}
