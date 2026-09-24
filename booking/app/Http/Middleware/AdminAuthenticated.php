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
        $admin = $adminId
            ? AdminUser::query()->whereKey($adminId)->where('is_active', true)->first()
            : null;

        if ($admin === null) {
            $request->session()->forget('admin_user_id');

            return redirect()->route('admin.login');
        }

        $request->attributes->set('admin_user', $admin);

        return $next($request);
    }
}
