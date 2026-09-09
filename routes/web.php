<?php

use Illuminate\Support\Facades\Route;

Route::get('weui', function () {
    return view('theme/default');
});

Route::get('ifsign', function () {
    return view('theme/ifsign');
});

Route::get('newUI', function () {
    return view('theme/newUI');
});

Route::get('new-qnq', function () {
    return view('theme/new-qnq');
});

Route::get('nuosike-qnq', function () {
    return view('theme/nuosike-qnq');
});

Route::namespace('App\\Http\\Controllers\\Web\\')
    ->middleware([])
    ->group(function () {
        Route::get('/', 'ThemeController@index');
        Route::get('install', 'InstallController@index');
    });

Route::prefix(config('api.admin.path', 'admin'))
    ->name(config('api.admin.path', 'admin').'.')
    ->namespace('App\\Http\\Controllers\\Admin\\')
    ->group(function () {
        Route::get('/', 'ConsoleController@index')->name('console');
        Route::get('console', 'ConsoleController@index')->name('console');
        Route::post('console', 'ConsoleController@show')->name('console.show');
        Route::delete('console/delete', 'ConsoleController@destroy')->name('console.destroy');

        Route::get('login', 'LoginController@index')->name('login');
        Route::post('login', 'LoginController@show')->name('login.show');

        Route::post('logout', 'LogoutController@index')->name('logout');

        Route::get('register', 'RegisterController@index')->name('register');
        Route::post('register', 'RegisterController@show')->name('register.show');

        Route::get('reset-password', 'ResetPasswordController@index')->name('reset-password');
        Route::post('reset-password', 'ResetPasswordController@show')->name('reset-password.show');

        Route::get('forgot-password', 'ForgotPasswordController@index')->name('forgot-password');
        Route::post('forgot-password', 'ForgotPasswordController@show')->name('forgot-password.show');

        Route::post('change-password', 'ChangePasswordController@show')->name('change-password');

        Route::post('detection', 'DetectionController@show')->name('detection');

        Route::get('app', 'AppController@index')->name('app');
        Route::match(['get', 'post'], 'app/create', 'AppController@create')->name('app.create');
        Route::match(['get', 'post', 'put'], 'app/edit/{id}', 'AppController@edit')->name('app.edit');
        Route::post('app', 'AppController@show')->name('app.show');
        Route::delete('app/delete', 'AppController@destroy')->name('app.destroy');
        Route::post('app/update', 'AppController@update')->name('app.update');

        Route::get('code', 'CodeController@index')->name('code');
        Route::match(['get', 'post'], 'code/create', 'CodeController@create')->name('code.create');
        Route::match(['get', 'post'], 'code/edit/{id}', 'CodeController@edit')->name('code.edit');
        Route::post('code', 'CodeController@show')->name('code.show');
        Route::delete('code/delete', 'CodeController@destroy')->name('code.destroy');
        Route::delete('code/update', 'CodeController@update')->name('code.update');

        Route::get('help', 'HelpController@index')->name('help');
        Route::match(['get', 'post'], 'help/create', 'HelpController@create')->name('help.create');
        Route::match(['get', 'post'], 'help/edit/{id}', 'HelpController@edit')->name('help.edit');
        Route::post('help', 'HelpController@show')->name('help.show');
        Route::delete('help/delete', 'HelpController@destroy')->name('help.destroy');

        Route::get('class', 'ClassController@index')->name('class');
        Route::match(['get', 'post'], 'class/create', 'ClassController@create')->name('class.create');
        Route::match(['get', 'post'], 'class/edit/{id}', 'ClassController@edit')->name('class.edit');
        Route::post('class', 'ClassController@show')->name('class.show');
        Route::delete('class/delete', 'ClassController@destroy')->name('class.destroy');

        Route::get('about_us', 'AboutUsController@index')->name('about_us');
        Route::match(['get', 'post'], 'about_us/create', 'AboutUsController@create')->name('about_us.create');
        Route::match(['get', 'post'], 'about_us/edit/{id}', 'AboutUsController@edit')->name('about_us.edit');
        Route::post('about_us', 'AboutUsController@show')->name('about_us.show');
        Route::delete('about_us/delete', 'AboutUsController@destroy')->name('about_us.destroy');

        Route::get('agent', 'AgentController@index')->name('agent');
        Route::match(['get', 'post'], 'agent/create', 'AgentController@create')->name('agent.create');
        Route::match(['get', 'post'], 'agent/edit/{id}', 'AgentController@edit')->name('agent.edit');
        Route::post('agent', 'AgentController@show')->name('agent.show');
        Route::delete('agent/delete', 'AgentController@destroy')->name('agent.destroy');

        Route::get('blacklist', 'BlackListController@index')->name('blacklist');
        Route::match(['get', 'post'], 'blacklist/create', 'BlackListController@create')->name('blacklist.create');
        Route::match(['get', 'post'], 'blacklist/edit/{id}', 'BlackListController@edit')->name('blacklist.edit');
        Route::post('blacklist', 'BlackListController@show')->name('blacklist.show');
        Route::delete('blacklist/delete', 'BlackListController@destroy')->name('blacklist.destroy');

        Route::get('repair', 'DeveloperController@index')->name('repair');

        Route::get('settings', 'SettingsController@index')->name('settings');
        Route::post('settings', 'SettingsController@update')->name('settings.update');

        Route::get('certificate/{method}', 'CertificateController@index')->name('certificate');
        Route::post('certificate/{version}/{method}', 'CertificateController@show')->name('certificate.show');

        Route::post('developer/update', 'DeveloperController@update')->name('developer.update');

        Route::get('self_update', 'SelfUpdateController@index')->name('self_update');
        Route::post('self_update/show', 'SelfUpdateController@show')->name('self_update.show');
        Route::post('self_update/update', 'SelfUpdateController@update')->name('self_update.update');

        Route::post('update_auth', 'UpdateAuthController@update_auth')->name('update_auth');
        Route::post('get_config', 'UpdateAuthController@get_config')->name('get_config');

    });

Route::namespace('App\\Http\\Controllers\\Agent\\')
    ->prefix('agent')
    ->name('agent.')
    ->middleware([])
    ->group(function () {
        Route::get('/', 'ConsoleController@index')->name('console');
        Route::get('console', 'ConsoleController@index')->name('console');
        Route::post('console', 'ConsoleController@show')->name('console.show');

        Route::get('login', 'LoginController@index')->name('login');
        Route::post('login', 'LoginController@show')->name('login.show');

        Route::post('logout', 'LogoutController@index')->name('logout');

        Route::get('register', 'RegisterController@index')->name('register');
        Route::post('register', 'RegisterController@show')->name('register.show');

        Route::get('reset-password', 'ResetPasswordController@index')->name('reset-password');
        Route::post('reset-password', 'ResetPasswordController@show')->name('reset-password.show');

        Route::get('forgot-password', 'ForgotPasswordController@index')->name('forgot-password');
        Route::post('forgot-password', 'ForgotPasswordController@show')->name('forgot-password.show');

        Route::post('change-password', 'ChangePasswordController@show')->name('change-password');

        Route::get('code', 'CodeController@index')->name('code');
        Route::post('code', 'CodeController@show')->name('code.show');
        Route::match(['get', 'post'], 'code/create', 'CodeController@create')->name('code.create');
        Route::match(['get', 'post'], 'code/edit/{id}', 'CodeController@edit')->name('code.edit');
        Route::delete('code/delete', 'CodeController@destroy')->name('code.destroy');

        Route::get('faq', 'FaqController@index')->name('faq');

        Route::post('source-code', 'SourceCodeController@show')->name('source-code');
    });