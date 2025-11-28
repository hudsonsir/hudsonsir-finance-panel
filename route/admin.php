<?php

use think\facade\Route;

Route::group('admin', function () {
    // 系统配置
    Route::get('/system/get', 'System/get');
    Route::post('/system/set', 'System/set');
    Route::post('/system/setpwd', 'System/setpwd');
    Route::get('/system/geetest', 'System/geetest');
    Route::post('/system/setGeetest', 'System/setGeetest');
    Route::post('/system/clear', 'System/clear');
    Route::get('/system/emailConfig', 'System/getEmailConfig');
    Route::post('/system/setEmailConfig', 'System/setEmailConfig');
    Route::post('/system/testEmail', 'System/testEmail');
    Route::get('/system/telegramConfig', 'System/getTelegramConfig');
    Route::post('/system/setTelegramConfig', 'System/setTelegramConfig');
           Route::post('/system/testTelegram', 'System/testTelegram');
           Route::post('/system/testTelegramMessage', 'System/testTelegramMessage');
           Route::get('/system/runtimeInfo', 'System/getRuntimeInfo');
    Route::post('/system/sendExpireReminders', 'System/sendExpireReminders');

    // 续费管理 - 产品管理
    Route::get('/product/list', 'Product/list');
    Route::get('/product/get', 'Product/get');
    Route::post('/product/add', 'Product/add');
    Route::post('/product/edit', 'Product/edit');
    Route::get('/product/delete', 'Product/delete');
    Route::post('/product/batchUpdate', 'Product/batchUpdate');

    // 续费管理 - 续费记录
    Route::get('/renew_record/list', 'RenewRecord/list');
    Route::get('/renew_record/get', 'RenewRecord/get');
    Route::post('/renew_record/add', 'RenewRecord/add');
    Route::post('/renew_record/edit', 'RenewRecord/edit');
    Route::get('/renew_record/delete', 'RenewRecord/delete');
    Route::get('/renew_record/export', 'RenewRecord/export');

    // 续费管理 - 统计面板
    Route::get('/finance/dashboard', 'Finance/dashboard');

    // 续费管理 - 汇率管理
    Route::get('/exchange_rate/get', 'ExchangeRate/get');
    Route::post('/exchange_rate/set', 'ExchangeRate/set');
    Route::get('/exchange_rate/fetch', 'ExchangeRate/fetch');
    Route::post('/exchange_rate/test', 'ExchangeRate/test');
    
})->prefix('admin.')
    ->middleware(\app\middleware\LoadConfig::class)
    ->middleware(\app\middleware\AuthAdmin::class)
    ->middleware(\app\middleware\RefererCheck::class);

