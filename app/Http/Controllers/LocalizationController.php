<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class LocalizationController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys();
        if (!in_array($locale, $supported, true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);
        LaravelLocalization::setLocale($locale);

        $redirect = (string) $request->query('redirect', '');
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return redirect()->to($redirect);
        }

        return redirect()->back();
    }
}
