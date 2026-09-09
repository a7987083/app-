<?php

use App\Http\Controllers\Agent\AppTokenController;
use App\Http\Controllers\Agent\ChangePasswordController;
use App\Http\Controllers\Agent\CodeController;
use App\Http\Controllers\Agent\ConsoleController;
use App\Http\Controllers\Agent\FaqController;
use App\Http\Controllers\Agent\ForgotPasswordController;
use App\Http\Controllers\Agent\LoginController;
use App\Http\Controllers\Agent\LogoutController;
use App\Http\Controllers\Agent\RegisterController;
use App\Http\Controllers\Agent\ResetPasswordController;
use App\Http\Controllers\Agent\SourceCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')
    ->name('agent.')
    ->group(function () {
        Route::get('/', [ConsoleController::class, 'index'])->name('console');
        Route::get('console', [ConsoleController::class, 'index'])->name('console');
        Route::post('console', [ConsoleController::class, 'show'])->name('console.show');

        Route::get('login', [LoginController::class, 'index'])->name('login');
        Route::post('login', [LoginController::class, 'show'])->name('login.show');

        Route::post('logout', [LogoutController::class, 'index'])->name('logout');

        Route::get('register', [RegisterController::class, 'index'])->name('register');

        Route::get('reset-password', [ResetPasswordController::class, 'index'])->name('reset-password');

        Route::get('forgot-password', [ForgotPasswordController::class, 'index'])->name('forgot-password');

        Route::post('change-password', [ChangePasswordController::class, 'show'])->name('change-password');

        Route::get('code', [CodeController::class, 'index'])->name('code');
        Route::post('code', [CodeController::class, 'show'])->name('code.show');
        Route::match(['get', 'post'], 'code/create', [CodeController::class, 'create'])->name('code.create');
        Route::match(['get', 'post'], 'code/edit/{id}', [CodeController::class, 'edit'])->name('code.edit');
        Route::delete('code/delete', [CodeController::class, 'destroy'])->name('code.destroy');

        Route::get('faq', [FaqController::class, 'index'])->name('faq');

        Route::get('app-token', [AppTokenController::class, 'index'])->name('app-token');
        Route::post('app-token/generate', [AppTokenController::class, 'generate'])->name('app-token.generate');
        Route::post('app-token/regenerate-key', [AppTokenController::class, 'regenerateKey'])->name('app-token.regenerate-key');

        Route::post('theme-list', [SourceCodeController::class, 'index'])->name('theme-list');
        Route::post('source-code', [SourceCodeController::class, 'show'])->name('source-code');
    });
