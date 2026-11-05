<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

final class RegistrationTerms
{
    public const VERSION = '1.0';

    public static function documentHash(): string
    {
        $hash = hash_file('sha256', resource_path('legal/citizen-registration-terms-1.0.json'));
        if ($hash === false) {
            throw new \LogicException('The citizen registration terms document is missing.');
        }

        return $hash;
    }

    public static function acceptance(): array
    {
        return ['version' => self::VERSION, 'document_sha256' => self::documentHash(), 'accepted_at' => now()->toIso8601String()];
    }

    public static function isCurrent(?array $acceptance): bool
    {
        return $acceptance !== null
            && ($acceptance['version'] ?? null) === self::VERSION
            && is_string($acceptance['document_sha256'] ?? null)
            && hash_equals(self::documentHash(), $acceptance['document_sha256'])
            && is_string($acceptance['accepted_at'] ?? null);
    }

    public static function record(User $user, array $acceptance): void
    {
        AuditLog::create([
            'user_id' => $user->getKey(),
            'action' => 'auth.registration_terms_accepted',
            'subject_type' => 'User',
            'subject_id' => $user->getKey(),
            'changes' => $acceptance,
            'ip' => request()->ip(),
        ]);
    }
}
