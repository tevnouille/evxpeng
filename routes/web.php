<?php

use App\Http\Controllers\ChargingSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReferenceDataController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/recharges');

Route::get('/recharges', [ChargingSessionController::class, 'index'])->name('charging-sessions.index');
Route::post('/recharges', [ChargingSessionController::class, 'store'])->name('charging-sessions.store');
Route::get('/recharges/{chargingSession}/edit', [ChargingSessionController::class, 'edit'])->name('charging-sessions.edit');
Route::put('/recharges/{chargingSession}', [ChargingSessionController::class, 'update'])->name('charging-sessions.update');
Route::delete('/recharges/{chargingSession}', [ChargingSessionController::class, 'destroy'])->name('charging-sessions.destroy');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

Route::get('/admin', [ReferenceDataController::class, 'index'])->name('reference-data.index');
Route::post('/admin/providers', [ReferenceDataController::class, 'storeProvider'])->name('reference-data.providers.store');
Route::put('/admin/providers/{provider}', [ReferenceDataController::class, 'updateProvider'])->name('reference-data.providers.update');
Route::delete('/admin/providers/{provider}', [ReferenceDataController::class, 'destroyProvider'])->name('reference-data.providers.destroy');
Route::post('/admin/power-ratings', [ReferenceDataController::class, 'storePowerRating'])->name('reference-data.power-ratings.store');
Route::put('/admin/power-ratings/{powerRating}', [ReferenceDataController::class, 'updatePowerRating'])->name('reference-data.power-ratings.update');
Route::delete('/admin/power-ratings/{powerRating}', [ReferenceDataController::class, 'destroyPowerRating'])->name('reference-data.power-ratings.destroy');
