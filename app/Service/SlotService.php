<?php

namespace App\Service;

use App\Models\Slot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SlotService
{

    public function getAvailability()
    {
        $data = Cache::get('slots:availability');
        if ($data) {
            return $data;
        }

        $lock = Cache::lock('slots:availability:lock', 5);

        try {
            return $lock->block(3, function () {
                $data = Cache::get('slots:availability');
                if ($data) {
                    return $data;
                }

                $data = $this->getSourceSlots();
                Cache::put('slots:availability', $data, rand(5, 15));
                return $data;
            });
        } catch (LockTimeoutException $e) {
            Log::warning('lock timeout exception slots availability', ['exception' => $e->getMessage(), 'line' => $e->getLine()]);
            return Cache::get('slots:availability') ?? $this->getSourceSlots();
        } finally {
            if (isset($lock)) {
                $lock->release();
            }
        }


    }

    /**
     * @return Collection
     */
    private function getSourceSlots(): Collection
    {
        return Slot::all(['id as slot_id', 'capacity', 'remaining']);
    }
}
