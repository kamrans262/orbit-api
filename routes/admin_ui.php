<?php

declare(strict_types=1);

use App\Http\Middleware\AdminUiSecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::middleware(AdminUiSecurityHeaders::class)->group(function (): void {
    Route::view('/admin/login', 'admin.auth.login')->name('admin.ui.login');
    Route::view('/admin/mfa', 'admin.auth.mfa')->name('admin.ui.mfa');
    Route::view('/admin', 'admin.dashboard')->name('admin.ui.dashboard');
    Route::view('/admin/dashboard', 'admin.dashboard')->name('admin.ui.dashboard.alias');
});
