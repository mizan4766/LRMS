<?php

use App\Http\Controllers\Api\{ApplicationController, AuthController, CitizenController, DashboardController, EstimateController, LookupController, PaymentController, RecordController, TaskController, UserAdminController};
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => ['ok' => true, 'time' => now()->toDateTimeString()]);
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/password', [AuthController::class, 'changePassword']);

    Route::get('lookups/bootstrap', [LookupController::class, 'bootstrap']);
    Route::get('lookups/mouzas', [LookupController::class, 'mouzas']);
    Route::get('lookups/users', [LookupController::class, 'users']);
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Application (Step 01) and its lands / attachments
    Route::get('applications', [ApplicationController::class, 'index']);
    Route::post('applications', [ApplicationController::class, 'store']);
    Route::get('applications/{id}', [ApplicationController::class, 'show'])->whereNumber('id');
    Route::put('applications/{id}', [ApplicationController::class, 'update'])->whereNumber('id');
    Route::post('applications/{id}/lands', [ApplicationController::class, 'storeLand']);
    Route::put('lands/{landId}', [ApplicationController::class, 'updateLand']);
    Route::delete('lands/{landId}', [ApplicationController::class, 'destroyLand']);
    Route::post('applications/{id}/attachments', [ApplicationController::class, 'upload']);
    Route::delete('attachments/{attId}', [ApplicationController::class, 'destroyAttachment']);
    Route::get('attachments/{attId}/download', [ApplicationController::class, 'download']);
    Route::post('attachments/{attId}/verify', [ApplicationController::class, 'verify']);
    Route::get('applications/{id}/readiness', [ApplicationController::class, 'readiness']);
    Route::post('applications/{id}/submit', [ApplicationController::class, 'submit']);

    // Worklist ("পদক্ষেপ") and workflow actions
    Route::get('tasks', [TaskController::class, 'mine']);
    Route::get('tasks/history', [TaskController::class, 'history']);
    Route::get('tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
    Route::post('tasks/{id}/act', [TaskController::class, 'act']);

    // Estimate / roydad helpers (server-side money calculation) and notices
    Route::post('estimate/deed-rate', [EstimateController::class, 'deedRate']);
    Route::post('estimate/calc', [EstimateController::class, 'calc']);
    Route::get('applications/{id}/estimate-prefill', [EstimateController::class, 'prefill']);
    Route::get('applications/{id}/notice-preview/{type}', [EstimateController::class, 'noticePreview']);
    Route::get('records/{id}', [RecordController::class, 'show']);

    // Payment register
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/export', [PaymentController::class, 'export']);

    // Citizens
    Route::get('citizen/notices', [CitizenController::class, 'notices']);
    Route::get('citizen/mine', [CitizenController::class, 'mine']);
    Route::post('applications/{id}/claims', [CitizenController::class, 'claim']);
    Route::post('applications/{id}/compensation-applications', [CitizenController::class, 'compensation']);
    Route::post('applications/{id}/objections', [CitizenController::class, 'objection']);
});

Route::middleware('api.token:admin')->group(function () {
    Route::get('admin/users', [UserAdminController::class, 'index']);
    Route::post('admin/users', [UserAdminController::class, 'store']);
    Route::put('admin/users/{id}', [UserAdminController::class, 'update']);
    Route::post('admin/users/{id}/reset-password', [UserAdminController::class, 'resetPassword']);
});
