<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller {
    public function showLogin(){
        if(Auth::check()) return redirect()->route('dashboard');
        return view('auth.login');
    }
    public function login(Request $r){
        $r->validate([
            'email'=>'required|email',
            'password'=>'required|string',
        ]);
        $cred = $r->only('email','password');
        $remember = $r->boolean('remember');
        if(Auth::attempt($cred, $remember)){
            $r->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }
        return back()->withErrors(['email'=>'Email atau password salah.'])->onlyInput('email');
    }
    public function logout(Request $r){
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('login');
    }
}
