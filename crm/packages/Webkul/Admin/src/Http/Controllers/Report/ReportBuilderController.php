<?php

namespace Webkul\Admin\Http\Controllers\Report;

use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class ReportBuilderController extends Controller
{
    /**
     * Display the report builder page.
     */
    public function index(): View
    {
        return view('admin::reports.builder');
    }
}
