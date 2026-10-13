<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        ], $data['login'], 'portal de estudiantes');
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
        ], $data['username'], 'portal de administración');
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
    private function attempt(Request $request, array $credentials, string $identifier, string $portal): RedirectResponse
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

            ActivityLog::record(ActivityLog::LOGIN_FAILED, "Intento de acceso fallido con «{$identifier}» ({$portal})", [
                'user_id' => null,
            ]);

            throw ValidationException::withMessages([
                'login' => $this->wrongPortalMessage($identifier, $credentials['password'], $credentials['role'])
                    ?? 'Las credenciales no son válidas.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        ActivityLog::record(ActivityLog::LOGIN, 'Inició sesión');

        return redirect()->intended(route('home'));
    }

    /**
     * Si las credenciales son correctas pero de una cuenta del otro rol,
     * indica en qué página debe ingresar (solo se revela con la contraseña correcta).
     */
    private function wrongPortalMessage(string $identifier, string $password, string $expectedRole): ?string
    {
        $user = User::where('role', '!=', $expectedRole)
            ->where(fn ($q) => $q->where('email', $identifier)
                ->orWhere('username', $identifier)
                ->orWhere('registration_number', $identifier))
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user->isAdmin()
            ? 'Esta es una cuenta de administrador. Ingresa desde «Ingreso de administrador» ('.route('admin.login', absolute: false).').'
            : 'Esta es una cuenta de estudiante. Ingresa desde «Ingreso de estudiantes» ('.route('login', absolute: false).').';
    }
}
