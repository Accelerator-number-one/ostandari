<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\In;

class QueryInput
{
    public static function filters(Request $r, array $definition): void
    {
        $rules = [];
        foreach ($definition['filters'] ?? [] as $field) {
            $rules[$field] = ['sometimes', str_ends_with($field, '_id') ? 'integer' : (str_starts_with($field, 'is_') ? 'boolean' : 'string')];
            if (! str_ends_with($field, '_id') && ! str_starts_with($field, 'is_')) {
                $rules[$field][] = 'max:100';
            }
            foreach ($definition['rules'][$field] ?? [] as $rule) {
                if ($rule instanceof In) {
                    $rules[$field][] = $rule;
                }
            }
        }
        $r->validate($rules);
    }
}
