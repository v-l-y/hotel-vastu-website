<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->attributes->get('admin_user');

        if (! $admin instanceof AdminUser || ! in_array($admin->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
