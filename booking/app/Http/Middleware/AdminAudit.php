<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $admin = $request->attributes->get('admin_user');
        if (! $admin instanceof AdminUser) {
            return $response;
        }

        $subjectType = null;
        $subjectId = null;

        foreach (($request->route()?->parameters() ?? []) as $parameter) {
            if ($parameter instanceof Model) {
                $subjectType = $parameter::class;
                $subjectId = $parameter->getKey();
                break;
            }
        }

        AdminAuditLog::query()->create([
            'admin_user_id' => $admin->id,
            'route_name' => $request->route()?->getName(),
            'method' => strtoupper($request->method()),
            'path' => '/'.$request->path(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status_code' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return $response;
    }
}
