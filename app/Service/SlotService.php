<?php

namespace App\Service;

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlotService
{
    const string CACHE_KEY_SLOTS_AVAILABILITY = 'slots:availability';

    /**
     * Получаем доступные слоты
     *
     * @return Collection|mixed
     */
    public function getAvailability()
    {
        $data = Cache::get(self::CACHE_KEY_SLOTS_AVAILABILITY);
        if ($data) {
            return $data;
        }

        $lock = Cache::lock('slots:availability:lock', 5);

        try {
            return $lock->block(3, function () {
                $data = Cache::get(self::CACHE_KEY_SLOTS_AVAILABILITY);
                if ($data) {
                    return $data;
                }

                $data = $this->getSourceSlots();
                Cache::put(self::CACHE_KEY_SLOTS_AVAILABILITY, $data, rand(5, 15));
                return $data;
            });
        } catch (LockTimeoutException $e) {
            Log::warning('lock timeout exception slots availability', ['exception' => $e->getMessage(), 'line' => $e->getLine()]);
            return Cache::get(self::CACHE_KEY_SLOTS_AVAILABILITY) ?? $this->getSourceSlots();
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

    /**
     * Подтверждение записи
     *
     * @param int $id
     *
     * @return Hold
     * @throws \Exception
     */
    public function confirmHold(int $id): Hold
    {
        return DB::transaction(function () use ($id) {

            $hold = Hold::query()->lockForUpdate()->findOrFail($id);

            if ($hold->status !== 'held') {
                throw new \Exception('Hold not in held status', 409);
            }

            $now = now();
            $expires = Carbon::parse($hold->expires_at);

            if ($expires && $now > $expires) {
                throw new \Exception('Hold expired', 409);
            }

            $slot = Slot::query()->lockForUpdate()->findOrFail($hold->slot_id);

            if ($slot->remaining <= 0) {
                throw new \Exception('Slot remaining cannot be less than 0', 409);
            }

            $slot->decrement('remaining');

            $hold->update(['status' => 'confirmed']);

            Cache::forget(self::CACHE_KEY_SLOTS_AVAILABILITY);

            return $hold;
        });
    }

    /**
     * Отмена записи
     *
     * @param int $id
     *
     * @return Hold
     * @throws \Throwable
     */
    public function cancelHold(int $id): Hold
    {
        return DB::transaction(function () use ($id) {
            $hold = Hold::query()->lockForUpdate()->findOrFail($id);

            if ($hold->status !== 'confirmed') {
                throw new \Exception('Hold not confirmed status', 409);
            }

            $slot = Slot::query()->lockForUpdate()->findOrFail($hold->slot_id);

            if ($slot->capacity === $slot->remaining) {
                throw new \Exception('No capacity', 409);
            }

            $slot->increment('remaining');

            $hold->update(['status' => 'cancelled']);

            Cache::forget(self::CACHE_KEY_SLOTS_AVAILABILITY);

            return $hold;
        });
    }
}
