<?php

namespace App\Services\Risk;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/** Signed query-bound position; changing search conditions requires a fresh cursor. */
class RiskQueryCursor
{
    private static function fingerprint(array $filter): string
    {
        unset($filter['cursor'], $filter['include_count'], $filter['page'], $filter['limit']);
        $filter = array_filter($filter, fn ($value) => $value !== '' && $value !== null);
        $normalize = function ($value) use (&$normalize) {
            if (is_array($value)) {
                ksort($value);

                return array_map($normalize, $value);
            }

            return (string) $value;
        };
        $filter = array_map($normalize, $filter);
        ksort($filter);

        return hash('sha256', json_encode($filter));
    }

    public static function encode(array $filter, int $anchor, int $before): string
    {
        return Crypt::encryptString(json_encode(['anchor' => $anchor, 'before' => $before, 'query' => self::fingerprint($filter)]));
    }

    public static function decode(string $token, array $filter): ?array
    {
        if ($token === '') {
            return null;
        }
        try {
            $value = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($value) || ($value['query'] ?? '') !== self::fingerprint($filter)
                || ! is_int($value['anchor'] ?? null) || ! is_int($value['before'] ?? null)
                || $value['anchor'] < 0 || $value['before'] < 0 || $value['before'] > $value['anchor']) {
                throw new \UnexpectedValueException();
            }

            return $value;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['cursor' => '分页位置已失效，请重新查询']);
        }
    }
}
