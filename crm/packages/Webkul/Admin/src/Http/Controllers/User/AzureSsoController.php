<?php

namespace Webkul\Admin\Http\Controllers\User;

use App\Services\PostHogService;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Models\User;

class AzureSsoController extends Controller
{
    /**
     * Redirect to Microsoft login.
     */
    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            session()->flash('error', 'SSO is not configured. Please set the AZURE_* environment variables.');

            return redirect()->route('admin.session.create');
        }

        return Socialite::driver('microsoft')
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->redirect();
    }

    /**
     * Handle the callback from Microsoft.
     */
    public function callback(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            session()->flash('error', 'SSO is not configured.');

            return redirect()->route('admin.session.create');
        }

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (\Exception $e) {
            session()->flash('error', 'Microsoft authentication failed. Please try again.');

            return redirect()->route('admin.session.create');
        }

        $email = $microsoftUser->getEmail();

        if (! $email) {
            session()->flash('error', 'Could not retrieve email from Microsoft account.');

            return redirect()->route('admin.session.create');
        }

        // Check allowed domain
        $allowedDomain = config('services.microsoft.allowed_domain');

        if ($allowedDomain) {
            $emailDomain = strtolower(substr($email, strpos($email, '@') + 1));

            if ($emailDomain !== strtolower($allowedDomain)) {
                session()->flash('error', "Email domain @{$emailDomain} is not allowed. Only @{$allowedDomain} accounts can sign in.");

                return redirect()->route('admin.session.create');
            }
        }

        // Find or create the user
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Auto-create user from Microsoft account
            $user = User::create([
                'name'     => $microsoftUser->getName() ?? explode('@', $email)[0],
                'email'    => $email,
                'password' => bcrypt(str()->random(32)),
                'status'   => 1,
                'role_id'  => $this->getDefaultRoleId(),
            ]);

            PostHogService::identify('user_' . $user->id, [
                'email' => $user->email,
                'name'  => $user->name,
            ]);

            PostHogService::capture('user_' . $user->id, 'user_created_via_sso', [
                'email'      => $user->email,
                'login_type' => 'azure_sso',
            ]);
        }

        if ($user->status == 0) {
            session()->flash('warning', trans('admin::app.users.activate-warning'));

            return redirect()->route('admin.session.create');
        }

        auth()->guard('user')->login($user);

        PostHogService::identify('user_' . $user->id, [
            'email' => $user->email,
            'name'  => $user->name,
        ]);

        PostHogService::capture('user_' . $user->id, 'user_logged_in_sso', [
            'email'      => $user->email,
            'login_type' => 'azure_sso',
        ]);

        return redirect()->route('admin.dashboard.index');
    }

    /**
     * Check if Azure SSO is fully configured.
     */
    private function isConfigured(): bool
    {
        return config('services.microsoft.client_id')
            && config('services.microsoft.client_secret')
            && config('services.microsoft.tenant');
    }

    /**
     * Get the default role ID for auto-created users.
     */
    private function getDefaultRoleId(): int
    {
        // Use the lowest-privilege role available, or fall back to role ID 1
        $role = \Webkul\User\Models\Role::orderBy('permission_type', 'asc')->first();

        return $role ? $role->id : 1;
    }
}
