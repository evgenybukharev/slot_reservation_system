<?php


use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\HoldController;

Route::get('/slots/availability', [AvailabilityController::class, 'getAvailability']);

Route::post('/slots/{id}/hold', [HoldController::class, 'createHold']);
Route::post('/holds/{id}/confirm', [HoldController::class, 'confirmHold']);
Route::delete('/holds/{id}', [HoldController::class, 'cancelHold']);
