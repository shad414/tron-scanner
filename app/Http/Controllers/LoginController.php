<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function form(Request $request)
    {
        $admin = $request->boolean('admin') || $request->session()->get('scanner_ok');

        if (! config('services.tron.password') && ! config('services.tron.admin_password')) {
            return redirect()->route('scan');
        }

        return view('login', ['admin' => $admin || ! config('services.tron.password')]);
    }

    /** One form for both passwords: the admin password also opens the site. */
    public function login(Request $request)
    {
        $given = (string) $request->input('password');
        $site = (string) config('services.tron.password');
        $admin = (string) config('services.tron.admin_password');

        $isAdmin = $admin !== '' && hash_equals($admin, $given);
        $isUser = $isAdmin || ($site !== '' && hash_equals($site, $given));

        if (! $isUser) {
            return back()->withErrors(['password' => 'وشەی نهێنی هەڵەیە.']);
        }

        $request->session()->regenerate();
        $request->session()->put('scanner_ok', true);
        if ($isAdmin) {
            $request->session()->put('admin_ok', true);
        }

        return redirect()->intended(route('scan'));
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();

        return redirect()->route('scan');
    }
}
