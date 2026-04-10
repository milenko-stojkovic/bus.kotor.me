<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\AdminPanelLoginRequest;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin-panel.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(AdminPanelLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $admin = Admin::query()
            ->where('email', $request->validated('email'))
            ->where('admin_access', true)
            ->where('control_access', false)
            ->first();

        if ($admin === null || $admin->password === null || $admin->password === '' || ! Hash::check($request->validated('password'), $admin->password)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Ovi podaci ne odgovaraju našim zapisima.',
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        Auth::guard('panel_admin')->login($admin, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('panel_admin.dashboard', [], false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('panel_admin')->logout();

        $request->session()->regenerateToken();

        return redirect()->to(route('panel_admin.login', [], false));
    }
}
