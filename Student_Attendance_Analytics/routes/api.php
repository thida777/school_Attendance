<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HistoryController;
use App\Http\Controllers\Api\V1\TeachingController;
use App\Http\Controllers\SchoolSettingsController;
use Illuminate\Support\Facades\Route;

/*
 * API for the teacher Android app. Every address starts with /api/v1.
 * After login, the app sends "Authorization: Bearer <token>" and "Accept: application/json".
 * "Accept-Language: km" gives messages in Khmer.
 * The rules are the same as on the website (App\Services\AttendanceRecorder).
 */
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 tries per minute

    // The school name for the app's login and start screens (no login needed)
    Route::get('/school', [SchoolSettingsController::class, 'show'])->middleware('throttle:60,1');

    // Forgot password: a code by Telegram, then a new password (same rules as the website)
    Route::post('/password/forgot', [AuthController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('/password/reset', [AuthController::class, 'reset'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'role:teacher'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'update']);
        Route::put('/me/password', [AuthController::class, 'password']);
        Route::post('/me/photo', [AuthController::class, 'photo']);
        Route::delete('/me/photo', [AuthController::class, 'removePhoto']);

        Route::get('/today', [TeachingController::class, 'today']);
        Route::get('/classes', [TeachingController::class, 'classes']);
        Route::get('/classes/{class}/students', [TeachingController::class, 'students']);

        Route::get('/attendance/roster', [AttendanceController::class, 'roster']);
        Route::post('/attendance', [AttendanceController::class, 'store']);

        Route::get('/history', [HistoryController::class, 'index']);
        Route::post('/records/{record}/correct', [HistoryController::class, 'correct']);
    });
});
