<?php

use App\Http\Controllers\Api\Agent\CodeController as AgentApiCodeController;
use App\Http\Controllers\Api\Agent\StatisticsController as AgentApiStatisticsController;
use App\Http\Controllers\Custom\DumpAppController;
use App\Http\Controllers\Web\AppBlacklistController;
use App\Http\Controllers\Web\AppFeedbackController;
use App\Http\Controllers\Web\AppGroupChatController;
use App\Http\Controllers\Web\AppInfoController;
use App\Http\Controllers\Web\AppMobileconfigController;
use App\Http\Controllers\Web\AppPlistController;
use App\Http\Controllers\Web\AppsController;
use App\Http\Controllers\Web\AppTutorialController;
use App\Http\Controllers\Web\AppUdidController;
use App\Http\Controllers\Web\AuthorizationController;
use App\Http\Controllers\Web\BuyController;
use App\Http\Controllers\Web\CalculateUDIDController;
use App\Http\Controllers\Web\ConfigController;
use App\Http\Controllers\Web\CustomerCreateController;
use App\Http\Controllers\Web\CustomerStatusController;
use App\Http\Controllers\Web\CustomerUdidController;
use App\Http\Controllers\Web\DownloadController;
use App\Http\Controllers\Web\GetUdidController;
use App\Http\Controllers\Web\InspectionCertificateController;
use App\Http\Controllers\Web\InstallAppController;
use App\Http\Controllers\Web\InstallController;
use App\Http\Controllers\Web\IosMobileconfigController;
use App\Http\Controllers\Web\IpaController;
use App\Http\Controllers\Web\IpadMobileconfigController;
use App\Http\Controllers\Web\MobileconfigController;
use App\Http\Controllers\Web\PlistController;
use App\Http\Controllers\Web\QueryCodeController;
use App\Http\Controllers\Web\QuerySignController;
use App\Http\Controllers\Web\SignAppController;
use App\Http\Controllers\Web\WebClipController;
use Illuminate\Support\Facades\Route;

Route::post('dumpapp/applist', [DumpAppController::class, 'applist']);
Route::post('dumpapp/query_sign', [DumpAppController::class, 'query_sign']);

Route::post('install', [InstallController::class, 'install']);
Route::post('config', [ConfigController::class, 'config']);
Route::post('apps', [AppsController::class, 'apps']);
Route::post('app_info', [AppInfoController::class, 'app_info']);
Route::post('sign_app', [SignAppController::class, 'sign_app'])
    ->middleware('redeem.code.atomic');
Route::post('query_sign', [QuerySignController::class, 'query_sign']);
Route::post('query_code', [QueryCodeController::class, 'query_code']);
Route::post('get_udid', [GetUdidController::class, 'get_udid']);
Route::post('authorization', [AuthorizationController::class, 'authorization']);
Route::post('customer/create', [CustomerCreateController::class, 'create']);
Route::post('customer/status', [CustomerStatusController::class, 'status']);
Route::post('customer/{model}/get_udid', [CustomerUdidController::class, 'get_udid']);
Route::post('inspection_certificate', [InspectionCertificateController::class, 'inspection_certificate']);
Route::post('download', [DownloadController::class, 'download'])
    ->middleware('redeem.code.atomic');
Route::post('web_clip', [WebClipController::class, 'web_clip']);
Route::post('calculate_udid_device_model', [CalculateUDIDController::class, 'calculate_udid_device_model']);
Route::post('buy', [BuyController::class, 'buy']);
Route::post('authorization/heartbeat', [AuthorizationController::class, 'heartbeat']);
Route::post('app/get_udid', [AppUdidController::class, 'get_udid']);
Route::post('app/source_blacklist', [AppBlacklistController::class, 'source_blacklist']);

Route::get('{en_code}.plist', [PlistController::class, 'plist']);
Route::get('{en_code}.ipa', [IpaController::class, 'ipa']);
Route::get('installApp/{en_code}', [InstallAppController::class, 'mobileconfig']);
Route::get('udid.mobileconfig', [MobileconfigController::class, 'mobileconfig']);
Route::get('customer/all/{qr_id}.mobileconfig', [IosMobileconfigController::class, 'all']);
Route::get('customer/ipad/{qr_id}.mobileconfig', [IpadMobileconfigController::class, 'ipad']);
Route::get('app/udid.mobileconfig', [AppMobileconfigController::class, 'mobileconfig']);
Route::get('app/tutorial', [AppTutorialController::class, 'tutorial']);
Route::get('app/feedback', [AppFeedbackController::class, 'feedback']);
Route::get('app/group_chat', [AppGroupChatController::class, 'group_chat']);

Route::any('app/{en_code}.plist', [AppPlistController::class, 'plist']);

Route::prefix('agent')->middleware(['agent.api', 'throttle:120,1'])->group(function () {
    Route::post('statistics', [AgentApiStatisticsController::class, 'index']);
    Route::post('code_lists', [AgentApiCodeController::class, 'index']);
    Route::post('create_code', [AgentApiCodeController::class, 'store']);
    Route::post('deit_code', [AgentApiCodeController::class, 'update']);
    Route::delete('delete_code', [AgentApiCodeController::class, 'destroy']);
    Route::post('codes_status', [AgentApiCodeController::class, 'status']);
});
