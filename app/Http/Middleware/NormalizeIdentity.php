<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeIdentity
{
    public function handle(Request $r, Closure $next)
    {
        $map = array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789'));
        foreach (['mobile', 'national_code', 'code'] as $key) {
            if (is_string($r->input($key))) {
                $r->merge([$key => strtr(trim($r->input($key)), $map)]);
            }
        }

        return $next($r);
    }
}
