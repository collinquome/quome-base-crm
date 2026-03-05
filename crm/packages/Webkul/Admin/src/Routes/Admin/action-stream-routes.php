<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\ActionStream\ActionStreamController;
use Webkul\Admin\Http\Controllers\ActionStream\TeamStreamController;

Route::controller(ActionStreamController::class)->prefix('action-stream')->group(function () {
    Route::get('', 'index')->name('admin.action-stream.index');
    Route::get('list', 'list')->name('admin.action-stream.list');
    Route::post('', 'store')->name('admin.action-stream.store');
    Route::post('{id}/complete', 'complete')->name('admin.action-stream.complete');
});

Route::controller(TeamStreamController::class)->prefix('team-stream')->group(function () {
    Route::get('', 'index')->name('admin.team-stream.index');
});
