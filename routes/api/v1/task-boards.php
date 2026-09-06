<?php

use App\Http\Controllers\Api\V1\TaskBoard\BoardTaskController;
use App\Http\Controllers\Api\V1\TaskBoard\SprintController;
use App\Http\Controllers\Api\V1\TaskBoard\TaskBoardController;
use Illuminate\Support\Facades\Route;

    // Boards
    Route::apiResource('boards', TaskBoardController::class)->except(['store'])
        ->middlewareFor(['update'], 'check.permission:taskboard.boards.edit')
        ->middlewareFor(['destroy'], 'check.permission:taskboard.boards.delete');
    Route::post('boards', [TaskBoardController::class, 'store'])->middleware('check.permission:taskboard.boards.create')->name('boards.store');
    Route::post('boards/{board}/members', [TaskBoardController::class, 'addMember'])->middleware('check.permission:taskboard.boards.edit');
    Route::delete('boards/{board}/members/{member}', [TaskBoardController::class, 'removeMember'])->middleware('check.permission:taskboard.boards.edit');
    Route::post('boards/{board}/columns', [TaskBoardController::class, 'addColumn'])->middleware('check.permission:taskboard.boards.edit');
    Route::put('boards/{board}/columns/{column}', [TaskBoardController::class, 'updateColumn'])->middleware('check.permission:taskboard.boards.edit');
    Route::delete('boards/{board}/columns/{column}', [TaskBoardController::class, 'removeColumn'])->middleware('check.permission:taskboard.boards.edit');
    Route::get('boards/{board}/labels', [TaskBoardController::class, 'labels']);
    Route::post('boards/{board}/labels', [TaskBoardController::class, 'addLabel'])->middleware('check.permission:taskboard.boards.edit');

    // Tasks
    Route::prefix('boards/{board}/tasks')->group(function () {
        Route::get('/', [BoardTaskController::class, 'index']);
        Route::post('/', [BoardTaskController::class, 'store'])->middleware('check.permission:taskboard.tasks.create');
        Route::get('/{task}', [BoardTaskController::class, 'show']);
        Route::put('/{task}', [BoardTaskController::class, 'update'])->middleware('check.permission:taskboard.tasks.manage');
        Route::delete('/{task}', [BoardTaskController::class, 'destroy'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/move', [BoardTaskController::class, 'move'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/assign', [BoardTaskController::class, 'assign'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/comments', [BoardTaskController::class, 'addComment'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/checklists', [BoardTaskController::class, 'addChecklist'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/time-entries', [BoardTaskController::class, 'addTimeEntry'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/attachments', [BoardTaskController::class, 'addAttachment'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/labels', [BoardTaskController::class, 'addLabel'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/watchers', [BoardTaskController::class, 'addWatcher'])->middleware('check.permission:taskboard.tasks.manage');
        Route::post('/{task}/dependencies', [BoardTaskController::class, 'addDependency'])->middleware('check.permission:taskboard.tasks.manage');
    });

    // Sprints
    Route::prefix('sprints')->group(function () {
        Route::get('/', [SprintController::class, 'index']);
        Route::post('/', [SprintController::class, 'store'])->middleware('check.permission:taskboard.sprints.create');
        Route::get('/{sprint}', [SprintController::class, 'show']);
        Route::put('/{sprint}', [SprintController::class, 'update'])->middleware('check.permission:taskboard.sprints.manage');
        Route::delete('/{sprint}', [SprintController::class, 'destroy'])->middleware('check.permission:taskboard.sprints.manage');
        Route::post('/{sprint}/start', [SprintController::class, 'start'])->middleware('check.permission:taskboard.sprints.manage');
        Route::post('/{sprint}/complete', [SprintController::class, 'complete'])->middleware('check.permission:taskboard.sprints.manage');
        Route::post('/{sprint}/tasks', [SprintController::class, 'addTasks'])->middleware('check.permission:taskboard.sprints.manage');
        Route::delete('/{sprint}/tasks', [SprintController::class, 'removeTasks'])->middleware('check.permission:taskboard.sprints.manage');
        Route::get('/{sprint}/burndown', [SprintController::class, 'burndownChart']);
    });
