<?php

use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/k/preview/{campaign}', [LandingPageController::class, 'preview'])->name('landing.preview')->middleware('auth');
Route::get('/k/{token}', [LandingPageController::class, 'show'])->name('landing.show');
Route::post('/k/{token}/respond', [LandingPageController::class, 'respond'])->name('landing.respond');
