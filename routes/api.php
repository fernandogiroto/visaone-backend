<?php

use App\Http\Controllers\Api\AgentCaseController;
use App\Http\Controllers\Api\AgentOfferController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\CaseMessageController;
use App\Http\Controllers\Api\CaseStepController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ClientCaseController;
use App\Http\Controllers\Api\IntakeController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Públicas (triagem antes do login)
|--------------------------------------------------------------------------
*/
Route::get('catalog', CatalogController::class);
Route::post('intake', [IntakeController::class, 'store']);
Route::post('intake/{case}/register', [IntakeController::class, 'register'])->middleware('throttle:10,1');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Autenticadas (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/change-password', [AuthController::class, 'changePassword']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('auth/photo', [AuthController::class, 'updatePhoto']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read', [NotificationController::class, 'markAllRead']);

    // Cliente
    Route::middleware('role:client')->group(function () {
        Route::get('client/case', [ClientCaseController::class, 'show']);
        Route::get('client/cases', [ClientCaseController::class, 'index']);
        Route::post('client/cases', [ClientCaseController::class, 'store']);
        Route::post('cases/{case}/steps/{number}/submit', [CaseStepController::class, 'submit'])->whereNumber('number');
    });

    // Agente
    Route::middleware('role:agent')->prefix('agent')->group(function () {
        Route::get('offers', [AgentOfferController::class, 'index']);
        Route::post('offers/{assignment}/accept', [AgentOfferController::class, 'accept']);
        Route::post('offers/{assignment}/decline', [AgentOfferController::class, 'decline']);
        Route::get('cases', [AgentCaseController::class, 'index']);
        Route::post('cases/{case}/steps/{number}/review', [CaseStepController::class, 'review'])->whereNumber('number');
    });

    // Compartilhado (dono do caso ou agente responsável)
    Route::get('cases/{case}', [CaseController::class, 'show']);
    Route::post('cases/{case}/messages', [CaseMessageController::class, 'store']);
    Route::post('cases/{case}/messages/{message}/respond', [CaseMessageController::class, 'respond'])->whereNumber('message');
    Route::put('cases/{case}/messages/{message}', [CaseMessageController::class, 'update'])->whereNumber('message');
    Route::delete('cases/{case}/messages/{message}', [CaseMessageController::class, 'destroy'])->whereNumber('message');
    Route::get('cases/{case}/messages/{message}/file', [CaseMessageController::class, 'file'])
        ->whereNumber('message')
        ->name('cases.messages.file');
    Route::get('cases/{case}/documents/{document}', [CaseController::class, 'download'])
        ->name('cases.documents.download');
});
