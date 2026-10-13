<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Permite el paso solo al rol indicado: ->middleware('role:admin').
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->role === $role, 403, 'No tienes permiso para acceder a esta sección.');

        return $next($request);
    }
}
