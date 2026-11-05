<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Service;

/**
 * The duration tiers a service is sold in (1 / 3 / 6 / 12 months, different seat
 * counts, quality and device rails).
 *
 * The list joins its service through the model method Plan::allForAdmin(); the
 * service and active/inactive filters are applied to that result so nothing here
 * re-implements the join in raw SQL. Two money rules are enforced before a write:
 * a plan must cost more than zero, and its compare-at (list) price can never be
 * below the price actually charged. Deleting a plan that already sits on an order
 * is refused - the history snapshots its name, but keeping the row preserves the
 * live order_items link, so deactivating is the safe action.
 */
final class PlanController extends Controller
{
    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->queryValue('search', '')), 0, 80);
        $serviceId = (int) $request->queryValue('service_id', 0);
        $active = trim((string) $request->queryValue('active', ''));

        $plans = $this->filterPlans((new Plan())->allForAdmin($search), $serviceId, $active);

        return $this->view('admin.plans', [
            'plans' => $plans,
            'services' => (new Service())->query()->orderBy(['name' => 'asc'])->get(),
            'filters' => ['search' => $search, 'service_id' => $serviceId, 'active' => $active],
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, $this->rules(), $this->labels());
        $problem = $this->compareIsBelowPrice($data);

        if ($problem !== '') {
            return $this->refuse($problem);
        }

        $plans = new Plan();
        $id = $plans->create($this->row($data));

        AuditLog::record('plan.created', 'plan', $id, sprintf(
            'Created plan "%s" (%d months) on service #%d for %s.',
            (string) $data['title'],
            (int) $data['duration_months'],
            (int) $data['service_id'],
            (string) number_format((float) $data['price'], 2)
        ));

        return $this->redirect(route('admin.plans'), 'success', sprintf('"%s" was added.', (string) $data['title']));
    }

    public function update(Request $request, string $id): Response
    {
        $planId = (int) $id;
        $plans = new Plan();
        $plan = $plans->find($planId);

        if ($plan === null) {
            throw new HttpException(404, 'That plan does not exist.');
        }

        $data = $this->validate($request, $this->rules(), $this->labels());
        $problem = $this->compareIsBelowPrice($data);

        if ($problem !== '') {
            return $this->refuse($problem);
        }

        $plans->update($planId, $this->row($data));

        AuditLog::record('plan.updated', 'plan', $planId, sprintf(
            'Updated plan "%s" (%d months).',
            (string) $data['title'],
            (int) $data['duration_months']
        ));

        return $this->redirect(route('admin.plans'), 'success', sprintf('"%s" was saved.', (string) $data['title']));
    }

    public function destroy(Request $request, string $id): Response
    {
        $planId = (int) $id;
        $plans = new Plan();
        $plan = $plans->find($planId);

        if ($plan === null) {
            throw new HttpException(404, 'That plan does not exist.');
        }

        if ((string) $request->input('confirm', '') !== '1') {
            return $this->redirect(
                route('admin.plans'),
                'error',
                'Tick the confirmation box inside the delete form before removing a plan.'
            );
        }

        // order_items.plan_id is ON DELETE SET NULL and each line keeps a copy of
        // the service and plan name, so old orders stay readable either way. The
        // safe choice that keeps the live link intact is to deactivate instead.
        $usedIn = (new OrderItem())->query()->where('plan_id', $planId)->count();

        if ($usedIn > 0) {
            AuditLog::record('plan.delete_refused', 'plan', $planId, sprintf(
                'Refused to delete "%s": it appears on %d order line(s).',
                (string) $plan['title'],
                $usedIn
            ));

            return $this->redirect(
                route('admin.plans'),
                'error',
                sprintf(
                    '"%s" is on %d order line(s). Those orders keep their copied name and price, but deleting would blank the '
                    . 'link back to this plan - switch it to inactive instead so history stays traceable.',
                    (string) $plan['title'],
                    $usedIn
                )
            );
        }

        $plans->delete($planId);

        AuditLog::record('plan.deleted', 'plan', $planId, sprintf(
            'Deleted plan "%s"; no order lines referenced it.',
            (string) $plan['title']
        ));

        return $this->redirect(route('admin.plans'), 'success', sprintf('"%s" was deleted.', (string) $plan['title']));
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        return [
            'service_id' => 'required|integer|exists:services,id',
            'title' => 'required|max:120',
            'duration_months' => 'required|integer|between:1,600',
            'seats' => 'integer|between:1,99',
            'quality' => 'max:40',
            'device' => 'max:60',
            'price' => 'required|numeric|min:0.01',
            'compare_at_price' => 'numeric|min:0',
            'stock' => 'integer|between:-1,9999',
            'badge' => 'max:40',
            'description' => 'max:500',
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        return [
            'service_id' => 'Service',
            'title' => 'Title',
            'duration_months' => 'Duration (months)',
            'seats' => 'Seats',
            'price' => 'Price',
            'compare_at_price' => 'Compare-at price',
            'stock' => 'Stock',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function row(array $data): array
    {
        return [
            'service_id' => (int) $data['service_id'],
            'title' => (string) $data['title'],
            'duration_months' => (int) $data['duration_months'],
            'seats' => (int) ($data['seats'] ?? 1),
            'quality' => (string) ($data['quality'] ?? '') ?: null,
            'device' => (string) ($data['device'] ?? '') ?: null,
            'price' => (float) $data['price'],
            'compare_at_price' => isset($data['compare_at_price']) ? (float) $data['compare_at_price'] : null,
            'stock' => (int) ($data['stock'] ?? -1),
            'badge' => (string) ($data['badge'] ?? '') ?: null,
            'description' => (string) ($data['description'] ?? '') ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_featured' => (int) ($data['is_featured'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $data */
    private function compareIsBelowPrice(array $data): string
    {
        if (!array_key_exists('compare_at_price', $data)) {
            return '';
        }

        if ((float) $data['compare_at_price'] < (float) $data['price']) {
            return 'The compare-at (list) price cannot be lower than the price you are actually charging.';
        }

        return '';
    }

    /**
     * Apply the service + active/inactive filter to the joined rows the model
     * already returned.
     *
     * @param list<array<string, mixed>> $plans
     * @return list<array<string, mixed>>
     */
    private function filterPlans(array $plans, int $serviceId, string $active): array
    {
        return array_values(array_filter(
            $plans,
            static function (array $plan) use ($serviceId, $active): bool {
                if ($serviceId > 0 && (int) $plan['service_id'] !== $serviceId) {
                    return false;
                }

                if ($active === '0' || $active === '1') {
                    return (int) $plan['is_active'] === (int) $active;
                }

                return true;
            }
        ));
    }

    private function refuse(string $message): Response
    {
        Session::flash('error', $message);

        return $this->redirect(route('admin.plans'));
    }
}
