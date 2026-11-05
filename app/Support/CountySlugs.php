<?php

namespace App\Support;

final class CountySlugs
{
    private const ALIASES = [
        'nur' => ['noor', 'nour'],
        'qaemshahr' => ['ghaemshahr'],
        'chalus' => ['chalous'],
        'noshahr' => ['nowshahr'],
        'mahmudabad' => ['mahmoudabad', 'mahmoodabad'],
        'savadkuh-shomali' => ['north-savadkuh'],
        'tonekabon' => ['gov_tonekabon'],
        'juybar' => ['joybar'],
        'fereydunkenar' => ['fereydounkenar'],
    ];

    public static function variants(string $slug): array
    {
        foreach (self::ALIASES as $canonical => $aliases) {
            if ($canonical === $slug || in_array($slug, $aliases, true)) {
                return [$canonical, ...$aliases];
            }
        }

        return [$slug];
    }
}
