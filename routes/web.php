<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\ManagedUserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Catálogos
    Route::resource('areas', AreaController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('cargos', JobPositionController::class)
        ->parameters(['cargos' => 'cargo'])
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('gestionados', ManagedUserController::class)
        ->parameters(['gestionados' => 'gestionado'])
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('aplicaciones', ApplicationController::class)
        ->parameters(['aplicaciones' => 'aplicacion'])
        ->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
