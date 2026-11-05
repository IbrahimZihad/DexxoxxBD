<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Models\Coupon;

/**
 * Discount codes. Owner-only across the board - routes/web.php already gates every
 * verb here with owner middleware, so no role check belongs in this class.
 *
 * A code is stored uppercase and proven unique through the model's own lookup
 * (byCode() already uppercases before matching), a percentage can never exceed a
 * hundred, and an expiry is parsed to a real datetime or the row is left without
 * one. used_count is read-only here: only the checkout flow moves it.
 */
final class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.coupons', [
            'coupons' => (new Coupon())->query()->orderBy(['id' => 'desc'])->get(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, $this->rules(), $this->labels());
        $coupons = new Coupon();

        $code = $this->normaliseCode((string) $data['code']);

        if ($code === '') {
            return $this->refuse('A coupon code may use letters, numbers, hyphens and underscores only.');
        }

        $clash = $this->percentIsTooHigh($data);

        if ($clash !== '') {
            return $this->refuse($clash);
        }

        if ($coupons->byCode($code) !== null) {
            return $this->refuse(sprintf('The code "%s" already exists.', $code));
        }

        $id = $coupons->create($this->row($data, $code));

        AuditLog::record('coupon.created', 'coupon', $id, sprintf(
            'Created coupon "%s" (%s %s).',
            $code,
            (string) $data['type'],
            (string) number_format((float) $data['value'], 2)
        ));

        return $this->redirect(route('admin.coupons'), 'success', sprintf('Coupon "%s" was created.', $code));
    }

    public function update(Request $request, string $id): Response
    {
        $couponId = (int) $id;
        $coupons = new Coupon();
        $coupon = $coupons->find($couponId);

        if ($coupon === null) {
            throw new HttpException(404, 'That coupon does not exist.');
        }

        $data = $this->validate($request, $this->rules(), $this->labels());
        $code = $this->normaliseCode((string) $data['code']);

        if ($code === '') {
            return $this->refuse('A coupon code may use letters, numbers, hyphens and underscores only.');
        }

        $clash = $this->percentIsTooHigh($data);

        if ($clash !== '') {
            return $this->refuse($clash);
        }

        $existing = $coupons->byCode($code);

        if ($existing !== null && (int) $existing['id'] !== $couponId) {
            return $this->refuse(sprintf('Another coupon already uses the code "%s".', $code));
        }

        $coupons->update($couponId, $this->row($data, $code));

        AuditLog::record('coupon.updated', 'coupon', $couponId, sprintf(
            'Updated coupon "%s".',
            $code
        ));

        return $this->redirect(route('admin.coupons'), 'success', sprintf('Coupon "%s" was saved.', $code));
    }

    public function destroy(Request $request, string $id): Response
    {
        $couponId = (int) $id;
        $coupons = new Coupon();
        $coupon = $coupons->find($couponId);

        if ($coupon === null) {
            throw new HttpException(404, 'That coupon does not exist.');
        }

        if ((string) $request->input('confirm', '') !== '1') {
            return $this->redirect(
                route('admin.coupons'),
                'error',
                'Tick the confirmation box inside the delete form before removing a coupon.'
            );
        }

        $coupons->delete($couponId);

        AuditLog::record('coupon.deleted', 'coupon', $couponId, sprintf(
            'Deleted coupon "%s" (%d use(s) recorded).',
            (string) $coupon['code'],
            (int) $coupon['used_count']
        ));

        return $this->redirect(route('admin.coupons'), 'success', sprintf('Coupon "%s" was deleted.', (string) $coupon['code']));
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        return [
            'code' => 'required|max:32',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'min_order' => 'numeric|min:0',
            'max_uses' => 'integer|min:0',
            'per_customer_limit' => 'integer|min:0',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        return [
            'code' => 'Code',
            'type' => 'Type',
            'value' => 'Value',
            'min_order' => 'Minimum order',
            'max_uses' => 'Max uses',
            'per_customer_limit' => 'Per-customer limit',
            'expires_at' => 'Expires at',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function row(array $data, string $code): array
    {
        $expires = null;

        if (array_key_exists('expires_at', $data)) {
            $expires = (new \DateTimeImmutable((string) $data['expires_at']))->format('Y-m-d H:i:s');
        }

        return [
            'code' => $code,
            'type' => (string) $data['type'],
            'value' => (float) $data['value'],
            'min_order' => (float) ($data['min_order'] ?? 0),
            'max_uses' => (int) ($data['max_uses'] ?? 0),
            'per_customer_limit' => (int) ($data['per_customer_limit'] ?? 0),
            'expires_at' => $expires,
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];
    }

    /**
     * Uppercase a code and reject anything the column could not sanely hold. The
     * value is what uniqueness and storage both use, so the two never disagree.
     */
    private function normaliseCode(string $code): string
    {
        $code = mb_strtoupper(trim($code));

        return preg_match('/^[A-Z0-9][A-Z0-9_-]{0,31}$/', $code) === 1 ? $code : '';
    }

    /** @param array<string, mixed> $data */
    private function percentIsTooHigh(array $data): string
    {
        if ((string) $data['type'] === 'percent' && (float) $data['value'] > 100) {
            return 'A percentage discount cannot be more than 100.';
        }

        return '';
    }

    private function refuse(string $message): Response
    {
        Session::flash('error', $message);

        return $this->redirect(route('admin.coupons'));
    }
}
