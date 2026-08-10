<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\JobPositionRuleController;
use App\Http\Controllers\ManagedUserController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\PlatformRoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoleRequestController;
use App\Http\Controllers\SimulatorController;
use App\Http\Controllers\SodConflictController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Catálogos
    Route::resource('areas', AreaController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('cargos', JobPositionController::class)
        ->parameters(['cargos' => 'cargo'])
        ->only(['index', 'store', 'update', 'destroy']);
    // Reglas de cargo (permitido / no permitido)
    Route::get('cargos/{cargo}/reglas', [JobPositionRuleController::class, 'index'])->name('reglas.index');
    Route::post('cargos/{cargo}/reglas', [JobPositionRuleController::class, 'store'])->name('reglas.store');
    Route::delete('cargos/{cargo}/reglas/{role}', [JobPositionRuleController::class, 'destroy'])->name('reglas.destroy');
    Route::resource('gestionados', ManagedUserController::class)
        ->parameters(['gestionados' => 'gestionado'])
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('aplicaciones', ApplicationController::class)
        ->parameters(['aplicaciones' => 'aplicacion'])
        ->only(['index', 'store', 'update', 'destroy']);

    // Roles de negocio y sus opciones (anidados en una aplicación)
    Route::get('aplicaciones/{aplicacion}/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('aplicaciones/{aplicacion}/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::post('roles/{role}/opciones', [OptionController::class, 'store'])->name('opciones.store');
    Route::put('opciones/{option}', [OptionController::class, 'update'])->name('opciones.update');
    Route::delete('opciones/{option}', [OptionController::class, 'destroy'])->name('opciones.destroy');

    // Matriz SoD
    Route::resource('matriz', SodConflictController::class)
        ->parameters(['matriz' => 'matriz'])
        ->only(['index', 'store', 'update', 'destroy']);

    // Simulador SoD
    Route::get('simulador', [SimulatorController::class, 'index'])->name('simulador.index');

    // Solicitudes y autorizaciones
    Route::get('solicitudes', [RoleRequestController::class, 'index'])->name('solicitudes.index');
    Route::post('solicitudes', [RoleRequestController::class, 'store'])->name('solicitudes.store');
    Route::post('solicitudes/{solicitud}/aprobar', [RoleRequestController::class, 'approve'])->name('solicitudes.approve');
    Route::post('solicitudes/{solicitud}/rechazar', [RoleRequestController::class, 'reject'])->name('solicitudes.reject');

    // Administración: Agentes (usuarios con login)
    Route::resource('agentes', AgentController::class)
        ->parameters(['agentes' => 'agente'])
        ->only(['index', 'store', 'update', 'destroy']);

    // Administración: Roles y permisos (RBAC de plataforma)
    Route::get('roles-permisos', [PlatformRoleController::class, 'index'])->name('roles-permisos.index');
    Route::put('roles-permisos/{role}', [PlatformRoleController::class, 'update'])->name('roles-permisos.update');

    // Bitácora (auditoría)
    Route::get('bitacora', [AuditController::class, 'index'])->name('bitacora.index');

    // Configuración del menú (Menú -> Opciones de menú)
    Route::resource('menus', MenuController::class)
        ->parameters(['menus' => 'menu'])
        ->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
