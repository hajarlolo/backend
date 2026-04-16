<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentification requise.');
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            \Illuminate\Support\Facades\Log::warning('Role check failed', [
                'user_id' => $user->id_user,
                'user_role' => $user->role,
                'expected_roles' => $roles,
            ]);
            abort(403, 'Acces non autorise.');
        }

        return $next($request);
    }
}

