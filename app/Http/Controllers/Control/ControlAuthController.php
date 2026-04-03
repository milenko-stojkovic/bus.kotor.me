<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\ControlLoginRequest;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ControlAuthController extends Controller
{
    public function create(): View
    {
        return view('control.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(ControlLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $admin = Admin::query()
            ->where('email', $request->validated('email'))
            ->where('control_access', true)
            ->first();

        if ($admin === null || $admin->password === null || $admin->password === '' || ! Hash::check($request->validated('password'), $admin->password)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Ovi podaci ne odgovaraju našim zapisima.',
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        Auth::guard('control')->login($admin, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('control.dashboard', [], false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('control')->logout();

        $request->session()->regenerateToken();

        return redirect()->to(route('control.login', [], false));
    }
}
