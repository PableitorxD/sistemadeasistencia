<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showStudentLogin(): View
    {
        return view('auth.login');
    }

    public function showAdminLogin(): View
    {
        return view('auth.admin-login');
    }

    /**
     * El estudiante entra con su correo o su número de registro.
     */
    public function studentLogin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['login' => 'correo o número de registro', 'password' => 'contraseña']);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'registration_number';

        return $this->attempt($request, [
            $field => $data['login'],
            'password' => $data['password'],
            'role' => User::ROLE_STUDENT,
        ], $data['login']);
    }

    /**
     * El administrador entra con su usuario (o correo) y contraseña propios.
     */
    public function adminLogin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'usuario', 'password' => 'contraseña']);

        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return $this->attempt($request, [
            $field => $data['username'],
            'password' => $data['password'],
            'role' => User::ROLE_ADMIN,
        ], $data['username']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $isAdmin = $request->user()->isAdmin();

        ActivityLog::record(ActivityLog::LOGOUT, 'Cerró sesión');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($isAdmin ? 'admin.login' : 'login');
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function attempt(Request $request, array $credentials, string $identifier): RedirectResponse
    {
        $throttleKey = Str::lower($identifier).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            ActivityLog::record(ActivityLog::LOGIN_FAILED, "Intento de acceso fallido con «{$identifier}»", [
                'user_id' => null,
            ]);

            throw ValidationException::withMessages([
                'login' => 'Las credenciales no son válidas.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        ActivityLog::record(ActivityLog::LOGIN, 'Inició sesión');

        return redirect()->intended(route('home'));
    }
}
