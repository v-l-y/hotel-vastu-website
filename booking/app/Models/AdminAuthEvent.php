<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Throwable;

class AdminAuthEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'event_type',
        'email_hash',
        'ip_address',
        'user_agent_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public static function record(
        Request $request,
        string $eventType,
        ?AdminUser $admin = null,
        ?string $email = null
    ): void {
        try {
            $normalizedEmail = strtolower(trim((string) $email));
            $userAgent = trim((string) $request->userAgent());

            self::query()->create([
                'admin_user_id' => $admin?->id,
                'event_type' => $eventType,
                'email_hash' => $normalizedEmail !== ''
                    ? hash('sha256', $normalizedEmail)
                    : null,
                'ip_address' => $request->ip(),
                'user_agent_hash' => $userAgent !== ''
                    ? hash('sha256', $userAgent)
                    : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
