<?php

use App\Http\Controllers\Web\InstallController;
use App\Http\Controllers\Web\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ThemeController::class, 'index']);

Route::get('install', [InstallController::class, 'index']);
