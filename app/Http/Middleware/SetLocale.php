<?php

namespace App\Http\Middleware;

use App\Helpers\LocaleHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UI language: auth -> users.lang; guests -> session('locale') or Accept-Language (cg/hr/sr/bs -> cg, else en).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->lang) {
            $locale = $request->user()->lang;
        } elseif ($request->hasSession() && $request->session()->has('locale') && LocaleHelper::isValid($request->session()->get('locale'))) {
            $locale = $request->session()->get('locale');
        } else {
            $locale = LocaleHelper::fromAcceptLanguage($request->header('Accept-Language'));
        }

        if (LocaleHelper::isValid($locale)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
