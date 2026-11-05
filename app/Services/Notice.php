<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class Notice
{
    public static function send(?int $userId, string $title, array $data = []): void
    {
        if (! $userId || ! $user = User::find($userId)) {
            return;
        }
        $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'portal', 'data' => ['title' => $title, ...$data]]);
    }
}
