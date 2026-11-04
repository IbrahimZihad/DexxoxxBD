<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Request;

/**
 * Turns a listing URL's query string into the filter array ProductRepository
 * expects, and back into the shape the filter panel renders.
 *
 * Only whitelisted keys survive: a stray query parameter must never reach the
 * SQL builder, and the "keep the current filters" links in the toolbar are built
 * from this normalised copy rather than from raw input.
 */
final class Filters
{
    /** @var list<string> */
    private const SORTS = ['newest', 'oldest', 'price_asc', 'price_desc', 'popular', 'rating', 'name', 'discount'];

    /** @var list<string> */
    private const DURATIONS = ['1m', '3m', '6m', '12m', 'lifetime'];

    /** @var list<string> */
    private const AVAILABILITY = ['in_stock', 'out_of_stock'];

    /** @var list<string> */
    private const FLAGS = ['featured', 'bestseller', 'new_arrival', 'on_sale', 'digital_delivery'];

    /**
     * @return array<string, mixed> repository filters plus display state
     */
    public static function fromRequest(Request $request): array
    {
        $query = $request->query();

        $filters = [
            'search' => self::text($query, 'q', ['q', 'search']),
            'category' => self::slug($query, 'category'),
            'brand' => self::slugs($query, 'brand'),
            'min_price' => self::number($query, 'min_price'),
            'max_price' => self::number($query, 'max_price'),
            'duration' => self::oneOf($query, 'duration', self::DURATIONS),
            'availability' => self::oneOf($query, 'availability', self::AVAILABILITY),
            'rating' => self::number($query, 'rating'),
            'sort' => self::oneOf($query, 'sort', self::SORTS) ?: 'newest',
            'page' => max(1, (int) ($query['page'] ?? 1)),
        ];

        foreach (self::FLAGS as $flag) {
            $filters[$flag] = isset($query[$flag])
                && filter_var($query[$flag], FILTER_VALIDATE_BOOLEAN);
        }

        $perPage = (int) ($query['per_page'] ?? 0);
        if ($perPage >= 6 && $perPage <= 48) {
            $filters['per_page'] = $perPage;
        }

        return $filters;
    }

    /**
     * A copy with one key forced, used by a shelf or brand page that must keep
     * the visitor inside its own scope while the filter panel still works.
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function with(array $filters, array $overrides): array
    {
        $merged = array_merge($filters, $overrides);
        $merged['page'] = 1;
        return $merged;
    }

    /**
     * Query parameters that describe the current state, for hidden inputs that
     * keep filters alive when only the sort or page changes.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function toQuery(array $filters, array $omit = []): array
    {
        $omit[] = 'page';

        $query = [];
        foreach ($filters as $key => $value) {
            if (in_array($key, $omit, true) || $value === '' || $value === [] || $value === false) {
                continue;
            }
            $query[$key] = is_bool($value) ? '1' : $value;
        }

        if (($query['search'] ?? '') !== '') {
            $query['q'] = $query['search'];
        }
        unset($query['search']);

        return $query;
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $keys
     */
    private static function text(array $query, string $key, array $keys, int $limit = 120): string
    {
        foreach ($keys as $candidate) {
            $value = $query[$candidate] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $limit);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $query */
    private static function slug(array $query, string $key): string
    {
        $value = self::text($query, $key, [$key], 190);
        return preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $value) === 1 ? $value : '';
    }

    /**
     * @param array<string, mixed> $query
     * @return list<string>
     */
    private static function slugs(array $query, string $key): array
    {
        $raw = $query[$key] ?? [];
        $slugs = [];
        foreach ((array) $raw as $value) {
            if (!is_string($value)) {
                continue;
            }
            $candidate = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($value))) ?? '';
            if ($candidate !== '' && mb_strlen($candidate) <= 190) {
                $slugs[] = $candidate;
            }
        }

        return array_values(array_unique($slugs));
    }

    /** @param array<string, mixed> $query */
    private static function number(array $query, string $key): ?float
    {
        $value = $query[$key] ?? null;
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        return $number >= 0 && $number <= 10000000 ? round($number, 2) : null;
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $allowed
     */
    private static function oneOf(array $query, string $key, array $allowed): string
    {
        $value = $query[$key] ?? null;
        return is_string($value) && in_array($value, $allowed, true) ? $value : '';
    }
}
