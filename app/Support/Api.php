<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class Api
{
    public static function ok(mixed $data = null, string $message = 'عملیات با موفقیت انجام شد.', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function page(LengthAwarePaginator $page, ?callable $transform = null): JsonResponse
    {
        $items = collect($page->items());

        return response()->json(['success' => true, 'data' => $transform ? $items->map($transform)->values() : $items,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public static function error(string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => (object) $errors], $status, $headers);
    }
}
