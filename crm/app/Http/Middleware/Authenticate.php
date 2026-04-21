<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if ($request->expectsJson() || $request->isJson() || $request->is('api/*')) {
            // For JSON / API requests return null — the parent middleware raises
            // AuthenticationException, which the handler converts to 401 JSON.
            return null;
        }

        // Laravel's generic `login` route doesn't exist in this app; the admin
        // panel registers `admin.login.index`. Fall back to the login URL if
        // the named route isn't defined so we don't 500 on auth redirects.
        if (\Illuminate\Support\Facades\Route::has('admin.login.index')) {
            return route('admin.login.index');
        }

        return '/admin/login';
    }
}
