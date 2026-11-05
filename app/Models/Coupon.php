<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Coupon extends Model
{
    protected string $table = 'coupons';

    protected array $fillable = [
        'code', 'type', 'value', 'min_order', 'max_uses', 'used_count',
        'per_customer_limit', 'expires_at', 'is_active', 'created_at', 'updated_at',
    ];

    public function byCode(string $code): ?array
    {
        return $this->query()->where('code', strtoupper(trim($code)))->first();
    }

    /** @return list<array<string, mixed>> */
    public function activeList(): array
    {
        return $this->query()
            ->where('is_active', 1)
            ->orderBy(['id' => 'desc'])
            ->limit(100)
            ->get();
    }

    public function usable(string $code, float $subtotal, ?string $email): ?array
    {
        $coupon = $this->byCode($code);

        if ($coupon === null || (int) $coupon['is_active'] !== 1) {
            return null;
        }

        $expires = $coupon['expires_at'] ?? null;

        if ($expires !== null && strtotime((string) $expires) !== false && time() > strtotime((string) $expires)) {
            return null;
        }

        if ((int) $coupon['max_uses'] > 0 && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            return null;
        }

        if ((float) $coupon['min_order'] > 0 && $subtotal < (float) $coupon['min_order']) {
            return null;
        }

        if ($email !== null && (int) $coupon['per_customer_limit'] > 0) {
            $already = (int) Database::scalar(
                'select count(*) from orders where coupon_code = :code and email = :email and status <> :cancelled',
                [':code' => strtoupper(trim($code)), ':email' => mb_strtolower(trim($email)), ':cancelled' => 'cancelled']
            );

            if ($already >= (int) $coupon['per_customer_limit']) {
                return null;
            }
        }

        return $coupon;
    }

    /** Discount in taka for a given subtotal, never more than the subtotal. */
    public function discountFor(array $coupon, float $subtotal): float
    {
        $discount = (string) ($coupon['type'] ?? 'percent') === 'percent'
            ? $subtotal * ((float) $coupon['value'] / 100)
            : (float) $coupon['value'];

        return round(min($subtotal, max(0, $discount)), 2);
    }

    public function recordUsage(string $code): void
    {
        Database::execute(
            'update coupons set used_count = used_count + 1 where code = :code',
            [':code' => strtoupper(trim($code))]
        );
    }

    public function releaseUsage(string $code): void
    {
        Database::execute(
            'update coupons set used_count = greatest(used_count - 1, 0) where code = :code',
            [':code' => strtoupper(trim($code))]
        );
    }
}
