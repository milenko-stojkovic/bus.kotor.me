<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Agency panel: user profile (top navigation target /panel/user).
     */
    public function panel(Request $request): View
    {
        return view('panel.user', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $plainPassword = $validated['password'] ?? null;
        $payload = Arr::only($validated, ['name', 'email', 'lang', 'country']);

        $user->fill($payload);

        $emailChanged = $user->isDirty('email');
        $langChanged = $user->isDirty('lang');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if (filled($plainPassword)) {
            $user->password = $plainPassword;
        }

        $user->save();

        if ($langChanged && $request->hasSession()) {
            $request->session()->put('locale', $user->lang);
        }

        if ($emailChanged && $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }

        return Redirect::route('panel.user')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
