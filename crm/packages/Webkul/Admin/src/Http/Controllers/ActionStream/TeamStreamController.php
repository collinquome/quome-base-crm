<?php

namespace Webkul\Admin\Http\Controllers\ActionStream;

use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class TeamStreamController extends Controller
{
    public function index(): View
    {
        return view('admin::action-stream.team');
    }
}
