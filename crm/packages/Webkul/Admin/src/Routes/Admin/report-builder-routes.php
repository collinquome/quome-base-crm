<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Report\ReportBuilderController;

Route::controller(ReportBuilderController::class)->prefix('reports')->group(function () {
    Route::get('builder', 'index')->name('admin.reports.builder');
});
