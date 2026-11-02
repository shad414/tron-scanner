<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function form()
    {
        return config('services.tron.password') ? view('login') : redirect()->route('scan');
    }

    public function login(Request $request)
    {
        $password = (string) config('services.tron.password');
        if ($password !== '' && hash_equals($password, (string) $request->input('password'))) {
            $request->session()->regenerate();
            $request->session()->put('scanner_ok', true);

            return redirect()->intended(route('scan'));
        }

        return back()->withErrors(['password' => 'وشەی نهێنی هەڵەیە.']);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();

        return redirect()->route('login');
    }
}
