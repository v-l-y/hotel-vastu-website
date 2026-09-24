<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('admin_user_id');
        $sessionVersion = $request->session()->get('admin_session_version');

        $admin = $adminId
            ? AdminUser::query()->whereKey($adminId)->where('is_active', true)->first()
            : null;

        if (
            $admin !== null
            && $sessionVersion === null
            && (int) $admin->session_version === 1
        ) {
            $sessionVersion = 1;
            $request->session()->put('admin_session_version', 1);
        }

        if (
            $admin === null
            || $sessionVersion === null
            || (int) $sessionVersion !== (int) $admin->session_version
        ) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('admin.login'));
        }

        $request->attributes->set('admin_user', $admin);

        return $next($request);
    }
}
