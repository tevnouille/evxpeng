<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ChargerLookupController;
use App\Http\Controllers\ChargingCurveController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ChargingSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetectedChargeController;
use App\Http\Controllers\DataSourceController;
use App\Http\Controllers\FavoriteRouteController;
use App\Http\Controllers\FuelPriceController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MyVehicleController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\RoutePlannerController;
use App\Http\Controllers\ServerInfoController;
use App\Http\Controllers\TripMapController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/recharges');

Route::get('/recharges', [ChargingSessionController::class, 'index'])->name('charging-sessions.index');
Route::post('/recharges', [ChargingSessionController::class, 'store'])->name('charging-sessions.store');
// Assistance a la saisie : base nationale des bornes.
Route::get('/recharges/bornes', [ChargerLookupController::class, 'stations'])->name('chargers.stations');
Route::get('/recharges/fournisseurs', [ChargerLookupController::class, 'operators'])->name('chargers.operators');
Route::get('/recharges/bornes-proches', [ChargerLookupController::class, 'nearby'])->name('chargers.nearby');

Route::get('/recharges/{chargingSession}/edit', [ChargingSessionController::class, 'edit'])->name('charging-sessions.edit');
Route::put('/recharges/{chargingSession}', [ChargingSessionController::class, 'update'])->name('charging-sessions.update');
Route::delete('/recharges/{chargingSession}', [ChargingSessionController::class, 'destroy'])->name('charging-sessions.destroy');

// Detections de recharge ecartees des propositions : le rapprochement
// automatique ne voit pas les recharges saisies a la main.
Route::post('/recharges-detectees/ignorer', [DetectedChargeController::class, 'ignore'])->name('detected-charges.ignore');
Route::post('/recharges-detectees/retablir', [DetectedChargeController::class, 'restore'])->name('detected-charges.restore');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

Route::get('/carburants', [FuelPriceController::class, 'index'])->name('fuel-prices.index');

Route::get('/ma-voiture', [MyVehicleController::class, 'index'])
    ->middleware(\App\Http\Middleware\RequiresTelemetry::class)
    ->name('my-vehicle.index');

Route::get('/deplacements', [TripMapController::class, 'index'])
    ->middleware(\App\Http\Middleware\RequiresTelemetry::class)
    ->name('trips.index');

Route::get('/planificateur', [RoutePlannerController::class, 'index'])->name('planner.index');
Route::get('/planificateur/adresses', [RoutePlannerController::class, 'suggestions'])->name('planner.suggestions');

Route::get('/favoris', [FavoriteRouteController::class, 'index'])->name('favorites.index');
Route::post('/favoris', [FavoriteRouteController::class, 'store'])->name('favorites.store');
Route::get('/favoris/{favorite}', [FavoriteRouteController::class, 'show'])->name('favorites.show');
Route::put('/favoris/{favorite}', [FavoriteRouteController::class, 'update'])->name('favorites.update');
Route::delete('/favoris/{favorite}', [FavoriteRouteController::class, 'destroy'])->name('favorites.destroy');
Route::post('/favoris/{favorite}/bornes', [FavoriteRouteController::class, 'addStation'])->name('favorites.stations.store');
Route::delete('/favoris/{favorite}/bornes/{station}', [FavoriteRouteController::class, 'removeStation'])->name('favorites.stations.destroy');
Route::post('/favoris/{favorite}/copier', [FavoriteRouteController::class, 'copy'])->name('favorites.copy');

Route::get('/courbe-de-recharge', [ChargingCurveController::class, 'index'])->name('charging-curves.index');
Route::get('/courbe-de-recharge/etat/{vehicle}', [ChargingCurveController::class, 'state'])->name('charging-curves.state');

Route::get('/historique', [HistoryController::class, 'index'])->name('history.index');
Route::get('/historique/{year}/{month}', [HistoryController::class, 'show'])->whereNumber('year')->whereNumber('month')->name('history.show');

Route::get('/mon-compte', [AccountController::class, 'index'])->name('account.index');
Route::put('/mon-compte', [AccountController::class, 'update'])->name('account.update');
Route::put('/mon-compte/preferences', [AccountController::class, 'updatePreferences'])->name('account.preferences');
Route::post('/mon-compte/sms-test', [AccountController::class, 'testSms'])->name('account.test-sms');

// Journal des nouveautes : ouvert a tous les comptes.
Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');

Route::get('/admin', [ReferenceDataController::class, 'index'])->name('reference-data.index');

Route::get('/admin/sms', [ReferenceDataController::class, 'smsMessages'])->name('reference-data.sms.index');

// Etat des donnees rapatriees de l'exterieur. La page est consultable par tous ;
// c'est le controleur qui refuse la relance des sources partagees a un non-admin.
Route::get('/admin/sources', [DataSourceController::class, 'index'])->name('reference-data.sources.index');
Route::post('/admin/sources/{source}', [DataSourceController::class, 'refresh'])->name('reference-data.sources.refresh');

// Etat du serveur : versions et paquets, donc reserve a l'administrateur.
Route::middleware(\App\Http\Middleware\RequiresAdmin::class)->group(function () {
    Route::get('/admin/serveur', [ServerInfoController::class, 'index'])->name('reference-data.server.index');
    Route::post('/admin/serveur/verifier', [ServerInfoController::class, 'refresh'])->name('reference-data.server.refresh');
    Route::post('/admin/serveur/mettre-a-jour', [ServerInfoController::class, 'update'])->name('reference-data.server.update');
    Route::post('/admin/serveur/tout-mettre-a-jour', [ServerInfoController::class, 'updateAll'])->name('reference-data.server.update-all');
});

// Gestion des comptes : reservee a l'administrateur.
Route::middleware(\App\Http\Middleware\RequiresAdmin::class)->group(function () {
    Route::get('/admin/utilisateurs', [UserAdminController::class, 'index'])->name('reference-data.users.index');
    Route::post('/admin/utilisateurs', [UserAdminController::class, 'store'])->name('reference-data.users.store');
    Route::put('/admin/utilisateurs/{user}/autoriser', [UserAdminController::class, 'approve'])->name('reference-data.users.approve');
    Route::put('/admin/utilisateurs/{user}/retirer', [UserAdminController::class, 'revoke'])->name('reference-data.users.revoke');
    Route::delete('/admin/utilisateurs/{user}', [UserAdminController::class, 'destroy'])->name('reference-data.users.destroy');
});

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
