<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[\x00-\x20\\\\]/', $value) || ! (preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL) || preg_match('#^/(?!/)#', $value))) {
            $fail('آدرس باید مسیر داخلی یا لینک معتبر http/https باشد.');
        }
    }
}
