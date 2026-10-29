<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Installer\InstallerController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::prefix('install')->name('installer.')->group(function () {
    Route::get('/', [InstallerController::class, 'welcome'])->name('welcome');
    Route::get('/requirements', [InstallerController::class, 'requirements'])->name('requirements');
    Route::get('/env', [InstallerController::class, 'showEnv'])->name('env');
    Route::post('/env', [InstallerController::class, 'saveEnv'])->name('env.save');
    Route::get('/migrate', [InstallerController::class, 'showMigrate'])->name('migrate');
    Route::post('/migrate', [InstallerController::class, 'runMigrate'])->name('migrate.run');
    Route::get('/admin', [InstallerController::class, 'showAdmin'])->name('admin');
    Route::post('/admin', [InstallerController::class, 'saveAdmin'])->name('admin.save');
    Route::get('/complete', [InstallerController::class, 'complete'])->name('complete');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/password/change', [ForcePasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/password/change', [ForcePasswordChangeController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('password.change.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets/check-duplicates', [TicketController::class, 'checkDuplicates'])->name('tickets.check-duplicates');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/claim', [TicketController::class, 'claim'])->name('tickets.claim');
    Route::post('/tickets/{ticket}/start', [TicketController::class, 'start'])->name('tickets.start');
    Route::post('/tickets/{ticket}/request-info', [TicketController::class, 'requestInfo'])->name('tickets.request-info');
    Route::post('/tickets/{ticket}/respond-info', [TicketController::class, 'respondInfo'])->name('tickets.respond-info');
    Route::post('/tickets/{ticket}/complete', [TicketController::class, 'complete'])->name('tickets.complete');
    Route::post('/tickets/{ticket}/reject', [TicketController::class, 'reject'])->name('tickets.reject');
    Route::post('/tickets/{ticket}/comment', [TicketController::class, 'comment'])->name('tickets.comment');
    Route::post('/tickets/{ticket}/reassign', [TicketController::class, 'reassign'])->name('tickets.reassign');
    Route::get('/attachments/{attachment}', [TicketController::class, 'downloadAttachment'])->name('attachments.download');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('auditor.audit-logs');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('auditor.audit-logs.export');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/resend-welcome', [UserController::class, 'resendWelcome'])->name('users.resend-welcome');

        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
