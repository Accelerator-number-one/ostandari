<?php

namespace App\Services;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Solar Hijri year inputs backed by the existing Gregorian term dates. */
final class GovernorTerms
{
    public static function ready(): bool
    {
        $types = array_column(\Illuminate\Support\Facades\Schema::getColumns('members'), 'type_name', 'name');
        return ($types['term_start'] ?? null) === 'datetime' && ($types['term_end'] ?? null) === 'datetime';
    }

    public static function digits(string $value): string
    {
        return strtr($value, array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
    }

    private static function formatter(string $pattern): \IntlDateFormatter
    {
        $formatter = new \IntlDateFormatter('en_US@calendar=persian', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE,
            'Asia/Tehran', \IntlDateFormatter::TRADITIONAL, $pattern);
        $formatter->setLenient(false);
        return $formatter;
    }

    public static function year(mixed $date): ?int
    {
        if (!$date) return null;
        $formatted = self::formatter('yyyy')->format(CarbonImmutable::parse($date, 'Asia/Tehran'));
        return $formatted === false ? null : (int) self::digits($formatted);
    }

    public static function date(int $year, bool $end = false): string
    {
        $timestamp = self::formatter('yyyy-MM-dd HH:mm:ss')->parse(sprintf('%04d-01-01 00:00:00', $end ? $year + 1 : $year));
        if ($timestamp === false) throw ValidationException::withMessages(['term_start_year' => 'سال شمسی معتبر نیست.']);
        return CarbonImmutable::createFromTimestamp($end ? $timestamp - 1 : $timestamp, 'Asia/Tehran')->format('Y-m-d H:i:s');
    }

    public static function payload(array $data, ?Member $member): array
    {
        $yearInput = array_key_exists('term_start_year', $data) || array_key_exists('term_end_year', $data);
        abort_if($yearInput && !self::ready(), 503, 'آماده‌سازی دوره‌های تصدی در حال انجام است؛ لحظاتی دیگر دوباره ذخیره کنید.');
        foreach (['term_start_year' => 'term_start', 'term_end_year' => 'term_end'] as $yearField => $dateField) {
            if (!array_key_exists($yearField, $data)) continue;
            if (array_key_exists($dateField, $data)) throw ValidationException::withMessages([$yearField => 'سال شمسی و تاریخ کامل را هم‌زمان ارسال نکنید.']);
            $year = $data[$yearField];
            $previous = $member?->$dateField;
            // Editing a name or biography must not round an existing exact date to a year boundary.
            $data[$dateField] = $year === null ? null : (self::year($previous) === (int) $year ? $previous : self::date((int) $year, $dateField === 'term_end'));
            unset($data[$yearField]);
        }
        $effective = array_merge($member?->getAttributes() ?? [], $data);
        if ($yearInput && !empty($effective['term_start']) && !empty($effective['term_end']) && strtotime($effective['term_end']) <= strtotime($effective['term_start']))
            throw ValidationException::withMessages(['term_end_year' => 'سال پایان نباید قبل از سال شروع باشد.']);
        return $data;
    }

    public static function period(array $member): ?string
    {
        $start = self::year($member['term_start'] ?? null);
        $end = self::year($member['term_end'] ?? null);
        if (!$start && !$end) return null;
        $text = $start ? (string) $start : '';
        if (!empty($member['is_current'])) $text = $start ? $text.'–تاکنون' : 'تاکنون';
        elseif ($start && $end) $text .= '–'.$end;
        elseif ($end) $text = 'تا '.$end;
        else $text = 'از '.$start;
        return strtr($text, array_combine(range(0, 9), preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY)));
    }

    public static function order($query)
    {
        return $query->orderByDesc('is_current')->orderByDesc('term_start')->orderByDesc('term_end')->orderBy('priority');
    }
}
