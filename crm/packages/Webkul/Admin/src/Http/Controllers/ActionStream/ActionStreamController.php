<?php

namespace Webkul\Admin\Http\Controllers\ActionStream;

use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class ActionStreamController extends Controller
{
    /**
     * Display the action stream page.
     */
    public function index(): View
    {
        return view('admin::action-stream.index');
    }
}
