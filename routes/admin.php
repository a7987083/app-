<?php

use App\Http\Controllers\Admin\AboutUsController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\BlackListController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\ChangePasswordController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\CodeController;
use App\Http\Controllers\Admin\ConsoleController;
use App\Http\Controllers\Admin\DetectionController;
use App\Http\Controllers\Admin\DeveloperController;
use App\Http\Controllers\Admin\ForgotPasswordController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\LogoutController;
use App\Http\Controllers\Admin\RegisterController;
use App\Http\Controllers\Admin\ResetPasswordController;
use App\Http\Controllers\Admin\SelfUpdateController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UpdateAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('api.admin.path', 'admin'))
    ->name(config('api.admin.path', 'admin').'.')
    ->group(function () {

        Route::get('/', [ConsoleController::class, 'index'])->name('console');
        Route::get('console', [ConsoleController::class, 'index'])->name('console');
        Route::post('console', [ConsoleController::class, 'show'])->name('console.show');
        Route::delete('console/delete', [ConsoleController::class, 'destroy'])->name('console.destroy');
        Route::post('console/resign', [ConsoleController::class, 'resign'])->name('console.resign');

        Route::get('login', [LoginController::class, 'index'])->name('login');
        Route::post('login', [LoginController::class, 'show'])->name('login.show');

        Route::post('logout', [LogoutController::class, 'index'])->name('logout');

        Route::get('register', [RegisterController::class, 'index'])->name('register');

        Route::get('reset-password', [ResetPasswordController::class, 'index'])->name('reset-password');

        Route::get('forgot-password', [ForgotPasswordController::class, 'index'])->name('forgot-password');

        Route::post('change-password', [ChangePasswordController::class, 'show'])->name('change-password');

        Route::post('detection', [DetectionController::class, 'show'])->name('detection');

        Route::get('app', [AppController::class, 'index'])->name('app');
        Route::match(['get', 'post'], 'app/create', [AppController::class, 'create'])->name('app.create');
        Route::match(['get', 'post', 'put'], 'app/edit/{id}', [AppController::class, 'edit'])->name('app.edit');
        Route::post('app', [AppController::class, 'show'])->name('app.show');
        Route::delete('app/delete', [AppController::class, 'destroy'])->name('app.destroy');
        Route::post('app/update', [AppController::class, 'update'])->name('app.update');
        Route::match('post', 'app/update-status', [AppController::class, 'updateStatus'])->name('app.update-status');

        Route::get('code', [CodeController::class, 'index'])->name('code');
        Route::match(['get', 'post'], 'code/create', [CodeController::class, 'create'])->name('code.create');
        Route::match(['get', 'post'], 'code/edit/{id}', [CodeController::class, 'edit'])->name('code.edit');
        Route::post('code', [CodeController::class, 'show'])->name('code.show');
        Route::delete('code/delete', [CodeController::class, 'destroy'])->name('code.destroy');
        Route::delete('code/update', [CodeController::class, 'update'])->name('code.update');

        Route::get('help', [HelpController::class, 'index'])->name('help');
        Route::match(['get', 'post'], 'help/create', [HelpController::class, 'create'])->name('help.create');
        Route::match(['get', 'post'], 'help/edit/{id}', [HelpController::class, 'edit'])->name('help.edit');
        Route::post('help', [HelpController::class, 'show'])->name('help.show');
        Route::delete('help/delete', [HelpController::class, 'destroy'])->name('help.destroy');

        Route::get('class', [ClassController::class, 'index'])->name('class');
        Route::match(['get', 'post'], 'class/create', [ClassController::class, 'create'])->name('class.create');
        Route::match(['get', 'post'], 'class/edit/{id}', [ClassController::class, 'edit'])->name('class.edit');
        Route::post('class', [ClassController::class, 'show'])->name('class.show');
        Route::delete('class/delete', [ClassController::class, 'destroy'])->name('class.destroy');

        Route::get('about_us', [AboutUsController::class, 'index'])->name('about_us');
        Route::match(['get', 'post'], 'about_us/create', [AboutUsController::class, 'create'])->name('about_us.create');
        Route::match(['get', 'post'], 'about_us/edit/{id}', [AboutUsController::class, 'edit'])->name('about_us.edit');
        Route::post('about_us', [AboutUsController::class, 'show'])->name('about_us.show');
        Route::delete('about_us/delete', [AboutUsController::class, 'destroy'])->name('about_us.destroy');

        Route::get('agent', [AgentController::class, 'index'])->name('agent');
        Route::match(['get', 'post'], 'agent/create', [AgentController::class, 'create'])->name('agent.create');
        Route::match(['get', 'post'], 'agent/edit/{id}', [AgentController::class, 'edit'])->name('agent.edit');
        Route::post('agent', [AgentController::class, 'show'])->name('agent.show');
        Route::delete('agent/delete', [AgentController::class, 'destroy'])->name('agent.destroy');

        Route::get('blacklist', [BlackListController::class, 'index'])->name('blacklist');
        Route::match(['get', 'post'], 'blacklist/create', [BlackListController::class, 'create'])->name('blacklist.create');
        Route::match(['get', 'post'], 'blacklist/edit/{id}', [BlackListController::class, 'edit'])->name('blacklist.edit');
        Route::post('blacklist', [BlackListController::class, 'show'])->name('blacklist.show');
        Route::delete('blacklist/delete', [BlackListController::class, 'destroy'])->name('blacklist.destroy');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('certificate/{method}', [CertificateController::class, 'index'])->name('certificate');
        Route::post('certificate/{version}/{method}', [CertificateController::class, 'show'])->name('certificate.show');

        Route::post('developer/update', [DeveloperController::class, 'update'])->name('developer.update');

        Route::get('self_update', [SelfUpdateController::class, 'index'])->name('self_update');
        Route::post('self_update/show', [SelfUpdateController::class, 'show'])->name('self_update.show');
        Route::post('self_update/update', [SelfUpdateController::class, 'update'])->name('self_update.update');

        Route::post('update_auth', [UpdateAuthController::class, 'update_auth'])->name('update_auth');
        Route::post('get_config', [UpdateAuthController::class, 'get_config'])->name('get_config');

    });
