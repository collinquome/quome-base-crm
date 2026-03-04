<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\ActionStream\ActionStreamController;
use Webkul\Admin\Http\Controllers\ActionStream\TeamStreamController;

Route::controller(ActionStreamController::class)->prefix('action-stream')->group(function () {
    Route::get('', 'index')->name('admin.action-stream.index');
});

Route::controller(TeamStreamController::class)->prefix('team-stream')->group(function () {
    Route::get('', 'index')->name('admin.team-stream.index');
});
