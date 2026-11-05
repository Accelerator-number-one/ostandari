<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Audit
{
    public static function record(string $action, ?Model $subject = null, array $changes = []): void
    {
        $safe = Arr::only($changes, ['title', 'name', 'status', 'is_active', 'priority', 'role_id', 'organization_id', 'type', 'kind', 'key', 'permissions']);
        AuditLog::create(['user_id' => auth('sanctum')->id(), 'action' => $action, 'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(), 'changes' => $safe ?: null, 'ip' => request()->ip()]);
    }
}
