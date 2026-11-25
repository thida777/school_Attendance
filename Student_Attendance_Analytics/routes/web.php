<?php

use App\Http\Controllers\AtRiskController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\InterventionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\MyClassesController;
use App\Http\Controllers\ParentMessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPhotoController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeachingAssignmentController;
use Illuminate\Support\Facades\Route;

/*
 * Who can open what (agreed rules):
 *  - admin:   everything
 *  - teacher: own dashboard, take attendance, history, own classes, profile
 *  Students and parents do not log in: parents receive Telegram or SMS messages.
 */

// English / Khmer switch, for everyone
Route::post('/locale/{locale}', LocaleController::class)->name('locale');

// A student's photo: only with a signed link, which only staff pages and the teacher app receive
Route::get('/students/{student}/photo', StudentPhotoController::class)->middleware(['signed', 'throttle:300,1'])->name('students.photo');

// Not logged in
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Forgot password: a 6-digit code by Telegram, then a new password
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

// Logged in
Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

        Route::get('/attendance/corrections', [CorrectionController::class, 'index'])->name('attendance.corrections');
        Route::post('/attendance/corrections/{correction}/approve', [CorrectionController::class, 'approve'])->name('attendance.corrections.approve');
        Route::post('/attendance/corrections/{correction}/reject', [CorrectionController::class, 'reject'])->name('attendance.corrections.reject');
        Route::get('/insights', [InsightsController::class, 'index'])->name('insights');
        Route::get('/at-risk', [AtRiskController::class, 'index'])->name('at-risk');
        Route::get('/interventions', [InterventionController::class, 'index'])->name('interventions');
        Route::post('/interventions', [InterventionController::class, 'store'])->name('interventions.store');
        Route::post('/interventions/{intervention}/close', [InterventionController::class, 'close'])->name('interventions.close');
        Route::delete('/interventions/{intervention}', [InterventionController::class, 'destroy'])->name('interventions.destroy');
        Route::get('/messages', [ParentMessageController::class, 'index'])->name('messages');
        Route::post('/students/{student}/messages', [ParentMessageController::class, 'store'])->name('messages.store');
        Route::post('/messages/{message}/resend', [ParentMessageController::class, 'resend'])->name('messages.resend');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');

        // School settings: the school name (website, app, reports, Telegram)
        Route::get('/settings/school', [SchoolSettingsController::class, 'edit'])->name('settings.school');
        Route::put('/settings/school', [SchoolSettingsController::class, 'update'])->name('settings.school.update');

        // School data: list, add, edit and delete on one page with pop-up forms
        $crud = ['index', 'store', 'update', 'destroy'];
        Route::resource('students', StudentController::class)->only([...$crud, 'show']);
        Route::resource('classes', SchoolClassController::class)->only($crud);
        Route::resource('subjects', SubjectController::class)->only($crud);
        Route::resource('teachers', TeacherController::class)->only([...$crud, 'show']);
        // Printable Telegram invites with QR codes (one parent, a class, or everyone not on Telegram yet)
        Route::get('/parents/invites', [GuardianController::class, 'invites'])->name('parents.invites');
        Route::resource('parents', GuardianController::class)->only($crud);
        Route::delete('/parents/{parent}/students/{student}', [GuardianController::class, 'unlink'])->name('parents.unlink');
        // Copy a class's subjects and teachers to other classes (e.g. a whole grade at the start of the year)
        Route::post('/assignments/copy', [TeachingAssignmentController::class, 'copy'])->name('assignments.copy');
        Route::resource('assignments', TeachingAssignmentController::class)->only($crud);
    });

    // Teacher only
    Route::middleware('role:teacher')->group(function () {
        Route::get('/my-dashboard', [DashboardController::class, 'teacher'])->name('teacher.dashboard');
        Route::get('/my-classes', MyClassesController::class)->name('teacher.classes');
    });

    // Admin and teacher
    Route::middleware('role:admin,teacher')->group(function () {
        // Take attendance: 1 today's lessons → 2 mark (3 review pop-up) → 4 done
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.take');
        Route::get('/attendance/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/attendance/sessions/{session}/done', [AttendanceController::class, 'done'])->name('attendance.done');
        Route::get('/attendance/history', [MonitoringController::class, 'index'])->name('attendance.monitoring');
        Route::post('/attendance/records/{record}/correct', [MonitoringController::class, 'correct'])->name('attendance.records.correct');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::post('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
        Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.destroy');
    });
});
