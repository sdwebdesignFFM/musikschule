<?php

use App\Exports\BeispielImportExport;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/beispiel-import.xlsx', function () {
    return Excel::download(new BeispielImportExport(), 'beispiel-import.xlsx');
})->name('beispiel-import');

Route::get('/t/open/{trackingId}', [TrackingController::class, 'open'])->name('tracking.open');
Route::get('/t/click/{trackingId}', [TrackingController::class, 'click'])->name('tracking.click');

Route::get('/k/preview/{campaign}', [LandingPageController::class, 'preview'])->name('landing.preview')->middleware('auth');
Route::get('/k/{token}', [LandingPageController::class, 'show'])->name('landing.show');
Route::post('/k/{token}/respond', [LandingPageController::class, 'respond'])->name('landing.respond');
