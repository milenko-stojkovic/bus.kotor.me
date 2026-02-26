<?php

namespace App\Http\Controllers;

use App\Helpers\LocaleHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guest can manually change UI language. Stores in session; SetLocale middleware uses it.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! LocaleHelper::isValid($locale)) {
            return redirect()->back()->with('error', __('Invalid language.'));
        }

        $request->session()->put('locale', $locale);

        return redirect()->back();
    }
}
