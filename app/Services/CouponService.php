<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Coupon;

/**
 * Coupon arithmetic in one place: the cart, the checkout page and the order
 * writer all call evaluate() so a discount can never be counted differently in
 * two spots.
 */
final class CouponService
{
    /**
     * @return array{ok: bool, code: string, discount: float, message: string}
     */
    public static function evaluate(string $code, float $subtotal, ?string $email = null): array
    {
        $code = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));

        if ($code === '') {
            return self::fail('Enter a coupon code first.');
        }

        if (strlen($code) > 32) {
            return self::fail('That coupon code is not recognised.');
        }

        $couponModel = new Coupon();
        $coupon = $couponModel->usable($code, $subtotal, $email);

        if ($coupon === null) {
            return self::fail('That coupon is expired, used up, or your cart does not qualify.');
        }

        $discount = $couponModel->discountFor($coupon, $subtotal);

        if ($discount <= 0) {
            return self::fail('That coupon does not reduce this order.');
        }

        $unit = (string) ($coupon['type'] ?? 'percent') === 'percent'
            ? rtrim(rtrim(number_format((float) $coupon['value'], 1), '0'), '.') . '% off'
            : '৳' . rtrim(rtrim(number_format((float) $coupon['value'], 2), '0'), '.') . ' off';

        return [
            'ok' => true,
            'code' => (string) $coupon['code'],
            'discount' => $discount,
            'message' => $unit . ' applied. You saved ৳' . number_format($discount, 2) . '.',
        ];
    }

    /** @return array{ok: bool, code: string, discount: float, message: string} */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'code' => '', 'discount' => 0.0, 'message' => $message];
    }
}
