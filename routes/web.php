<?php

use App\Http\Controllers\ChargingSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
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

Route::get('/historique', [HistoryController::class, 'index'])->name('history.index');
Route::get('/historique/{year}/{month}', [HistoryController::class, 'show'])->whereNumber('year')->whereNumber('month')->name('history.show');

Route::get('/admin', [ReferenceDataController::class, 'index'])->name('reference-data.index');

Route::get('/admin/vehicules', [ReferenceDataController::class, 'vehicles'])->name('reference-data.vehicles.index');
Route::post('/admin/vehicules', [ReferenceDataController::class, 'storeVehicle'])->name('reference-data.vehicles.store');
Route::put('/admin/vehicules/{vehicle}', [ReferenceDataController::class, 'updateVehicle'])->name('reference-data.vehicles.update');
Route::delete('/admin/vehicules/{vehicle}', [ReferenceDataController::class, 'destroyVehicle'])->name('reference-data.vehicles.destroy');

Route::get('/admin/localisations', [ReferenceDataController::class, 'locations'])->name('reference-data.locations.index');
Route::post('/admin/localisations', [ReferenceDataController::class, 'storeLocation'])->name('reference-data.locations.store');
Route::put('/admin/localisations/{location}', [ReferenceDataController::class, 'updateLocation'])->name('reference-data.locations.update');
Route::delete('/admin/localisations/{location}', [ReferenceDataController::class, 'destroyLocation'])->name('reference-data.locations.destroy');

Route::get('/admin/fournisseurs', [ReferenceDataController::class, 'providers'])->name('reference-data.providers.index');
Route::post('/admin/fournisseurs', [ReferenceDataController::class, 'storeProvider'])->name('reference-data.providers.store');
Route::put('/admin/fournisseurs/{provider}', [ReferenceDataController::class, 'updateProvider'])->name('reference-data.providers.update');
Route::delete('/admin/fournisseurs/{provider}', [ReferenceDataController::class, 'destroyProvider'])->name('reference-data.providers.destroy');

Route::get('/admin/puissances', [ReferenceDataController::class, 'powerRatings'])->name('reference-data.power-ratings.index');
Route::post('/admin/puissances', [ReferenceDataController::class, 'storePowerRating'])->name('reference-data.power-ratings.store');
Route::put('/admin/puissances/{powerRating}', [ReferenceDataController::class, 'updatePowerRating'])->name('reference-data.power-ratings.update');
Route::delete('/admin/puissances/{powerRating}', [ReferenceDataController::class, 'destroyPowerRating'])->name('reference-data.power-ratings.destroy');
