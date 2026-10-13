<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    /**
     * Cambia la contraseña del usuario autenticado (estudiante o administrador).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password.min' => 'La nueva contraseña debe tener al menos :min caracteres.',
            'password.letters' => 'La nueva contraseña debe incluir al menos una letra.',
            'password.numbers' => 'La nueva contraseña debe incluir al menos un número.',
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);

        // Nuevo identificador de sesión tras un cambio de credenciales.
        $request->session()->regenerate();

        ActivityLog::record(ActivityLog::PASSWORD_CHANGED, 'Cambió su contraseña', [
            'student_id' => $user->isStudent() ? $user->id : null,
        ]);

        return redirect()->route('password.edit')->with('success', 'Tu contraseña se actualizó correctamente.');
    }
}
