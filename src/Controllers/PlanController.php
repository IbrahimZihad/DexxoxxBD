<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\PlanRepository;
use App\Security\Sanitizer;
use App\Security\Validator;

final class PlanController extends Controller
{
    public function index(): Response
    {
        $id = $this->request->int('id');
        $slug = (string) ($this->request->query('slug') ?? '');

        if ($id > 0 || $slug !== '') {
            return $this->show();
        }

        $page = PlanRepository::paginate([
            'q' => (string) ($this->request->query('q') ?? ''),
            'page' => $this->page(),
            'per_page' => $this->perPage(20, 50),
        ], Auth::isAdmin());

        return $this->json([
            'plans' => array_map($this->shape(...), $page['data']),
            'meta' => ['total' => $page['total'], 'page' => $page['page'], 'per_page' => $page['per_page']],
        ]);
    }

    public function show(): Response
    {
        $id = $this->request->int('id');
        $slug = (string) ($this->request->query('slug') ?? '');
        $activeOnly = !Auth::isAdmin();

        $plan = $id > 0 ? PlanRepository::find($id, $activeOnly) : PlanRepository::findBySlug($slug, $activeOnly);

        if ($plan === null) {
            throw new HttpException(404, 'Plan not found.');
        }

        return $this->json(['plan' => $this->shape($plan)]);
    }

    public function store(): Response
    {
        $data = $this->input(new: true);
        $data['slug'] = PlanRepository::uniqueSlug($data['name']);

        return $this->json(['id' => PlanRepository::create($data), 'slug' => $data['slug']], 201);
    }

    public function update(): Response
    {
        $id = $this->idFromQuery();
        $existing = PlanRepository::find($id);

        if ($existing === null) {
            throw new HttpException(404, 'Plan not found.');
        }

        $changed = $this->input(new: false);

        if (isset($changed['name'])) {
            $changed['slug'] = PlanRepository::uniqueSlug((string) $changed['name'], $id);
        }

        PlanRepository::update($id, $changed);

        return $this->json(['ok' => true]);
    }

    public function destroy(): Response
    {
        $id = $this->idFromQuery();

        if (PlanRepository::find($id) === null) {
            throw new HttpException(404, 'Plan not found.');
        }

        PlanRepository::delete($id);

        return $this->json(['ok' => true]);
    }

    /** @return array<string,mixed> */
    private function input(bool $new): array
    {
        $rules = [
            'name' => ($new ? 'required' : 'nullable') . '|string|min:2|max:120',
            'description' => 'nullable|string|max:2000',
            'price' => ($new ? 'required' : 'nullable') . '|numeric|min:1|max:10000000',
            'duration_days' => ($new ? 'required' : 'nullable') . '|integer|min:1|max:3650',
            'features' => 'nullable|array|max:12',
            'badge' => 'nullable|string|max:40',
            'status' => 'nullable|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ];

        $body = $this->request->body();
        $scoped = [];

        foreach ($rules as $field => $rule) {
            if ($new || $this->has($field)) {
                $scoped[$field] = $rule;
            }
        }

        $validated = Validator::make(array_intersect_key($body, $scoped), $scoped, [
            'duration_days' => 'duration',
            'sort_order' => 'sort order',
        ])->validated();

        $clean = [];

        if (array_key_exists('name', $validated)) {
            $clean['name'] = Sanitizer::text($validated['name'], 120);
        }

        if (array_key_exists('description', $validated)) {
            $clean['description'] = Sanitizer::multiline($validated['description'], 2000);
        }

        if (array_key_exists('price', $validated)) {
            $clean['price'] = round((float) $validated['price'], 2);
        }

        if (array_key_exists('duration_days', $validated)) {
            $clean['duration_days'] = (int) $validated['duration_days'];
        }

        if (array_key_exists('features', $validated)) {
            $clean['features'] = array_values(array_filter(
                array_map(static fn ($feature) => Sanitizer::text($feature, 160), (array) $validated['features']),
                static fn ($feature) => $feature !== ''
            ));
        }

        if (array_key_exists('badge', $validated)) {
            $clean['badge'] = $validated['badge'] === null ? null : Sanitizer::text($validated['badge'], 40);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $clean['status'] = (string) $validated['status'];
        }

        if (array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null) {
            $clean['sort_order'] = (int) $validated['sort_order'];
        }

        if ($new) {
            $clean += ['description' => '', 'features' => [], 'badge' => null, 'status' => 'active', 'sort_order' => 0];
        }

        return $clean;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function shape(array $plan): array
    {
        $days = (int) $plan['duration_days'];

        return [
            'id' => (int) $plan['id'],
            'name' => (string) $plan['name'],
            'slug' => (string) $plan['slug'],
            'description' => (string) ($plan['description'] ?? ''),
            'price' => round((float) $plan['price'], 2),
            'duration_days' => $days,
            'period_label' => $days >= 365 ? 'per year' : ($days >= 31 ? 'per ' . (int) round($days / 30) . ' months' : 'per ' . $days . ' days'),
            'features' => array_values(array_map('strval', (array) ($plan['features'] ?? []))),
            'badge' => $plan['badge'] ?? null,
            'status' => (string) $plan['status'],
            'sort_order' => (int) ($plan['sort_order'] ?? 0),
        ];
    }
}
