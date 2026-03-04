<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\ActionStream\ActionStreamController;

Route::controller(ActionStreamController::class)->prefix('action-stream')->group(function () {
    Route::get('', 'index')->name('admin.action-stream.index');
});
