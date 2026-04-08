<?php

namespace App\Service;

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlotService
{

    /**
     * Получаем доступные слоты
     *
     * @return Collection|mixed
     */
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
     * Получаем информацию по слотам из базы данных
     *
     * @return Collection
     */
    private function getSourceSlots(): Collection
    {
        return Slot::all(['id as slot_id', 'capacity', 'remaining']);
    }

    /**
     * Создания брони
     *
     * @param int    $slotId
     * @param string $idempotencyKey
     *
     * @return Hold
     * @throws \Throwable
     */
    public function createHold(int $slotId, mixed $idempotencyKey): Hold
    {

        if (empty($idempotencyKey) || !Str::isUuid($idempotencyKey)) {
            throw new \Exception('No valid idempotency key', 400);
        }

        $exist = Hold::query()->where(['idempotency_key' => $idempotencyKey])->first();
        if ($exist) {
            return $exist;
        }

        return DB::transaction(function () use ($slotId, $idempotencyKey) {

            $slot = Slot::query()->lockForUpdate()->findOrFail($slotId);

            if ($slot->remaining <= 0) {
                throw new \Exception('Slot remaining cannot be less than 0', 409);
            }

            $hold = new Hold([
                'slot_id' => $slot->id,
                'status' => 'held',
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes(5),
            ]);

            $hold->saveOrFail();

            return $hold;
        });
    }
}
