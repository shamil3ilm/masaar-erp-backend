<?php

use App\Http\Controllers\Api\V1\Core\ModuleAccessController;
use Illuminate\Support\Facades\Route;

// Module definitions
Route::get('/modules', [ModuleAccessController::class, 'index']);

// Organization module access
Route::get('/organization-modules', [ModuleAccessController::class, 'orgModules']);
Route::patch('/organization-modules/{moduleId}/active', [ModuleAccessController::class, 'setModuleActive'])->middleware('super.admin');

// Role permissions
Route::get('/roles/{role}/permissions', [ModuleAccessController::class, 'rolePermissions'])->middleware('super.admin');
Route::put('/roles/{role}/permissions/{moduleId}', [ModuleAccessController::class, 'setRolePermission'])->middleware('super.admin');

// User overrides
Route::get('/users/{user}/overrides', [ModuleAccessController::class, 'userOverrides'])->middleware('super.admin');
Route::put('/users/{user}/overrides/{moduleId}', [ModuleAccessController::class, 'setUserOverride'])->middleware('super.admin');
Route::delete('/users/{user}/overrides/{moduleId}', [ModuleAccessController::class, 'removeUserOverride'])->middleware('super.admin');

// Menu & access logs
Route::get('/menu', [ModuleAccessController::class, 'menuItems']);
Route::get('/access-logs', [ModuleAccessController::class, 'accessLogs'])->middleware('super.admin');
