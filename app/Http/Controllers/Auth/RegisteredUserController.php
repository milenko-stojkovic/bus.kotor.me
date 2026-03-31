<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'countries' => (array) config('countries', []),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $lang = app()->getLocale();
        if (! in_array($lang, ['cg', 'en'], true)) {
            $lang = 'en';
        }

        $user = User::create([
            'name' => $request->name,
            'country' => $request->country,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'lang' => $lang,
        ]);

        event(new Registered($user));

        Auth::login($user);

        // New users should verify email before accessing verified-only panel.
        return redirect()->to(route('verification.notice', [], false));
    }
}
