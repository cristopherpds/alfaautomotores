<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('catalogo', [VehiculoController::class, 'index'])->name('catalogo');
Route::get('vehiculos/{slug}', [VehiculoController::class, 'show'])->name('vehiculos.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('users', UserController::class)->except('show');
});

require __DIR__.'/settings.php';
