<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Service;

/**
 * Catalogue services (Netflix, Spotify, ChatGPT ...) - the brand row every plan
 * hangs off.
 *
 * The write routes already run behind owner middleware in routes/web.php, so a
 * role check here would be dead code. What this controller is responsible for is
 * judgement about the data:
 *  - every column that reaches the row is validated first, and a slug is either
 *    honoured and proven unique or generated from the name and proven unique;
 *  - a service that still owns plans is never deleted, because the plans foreign
 *    key cascades - the honest answer is "deactivate it instead";
 *  - every create, update and delete is stamped in the audit trail.
 */
final class ServiceController extends Controller
{
    private const FALLBACK_COLOR = '#6366f1';

    private const FALLBACK_MARK = 'Z';

    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->queryValue('search', '')), 0, 80);

        $query = (new Service())->query();

        if ($search !== '') {
            $query->search(['name', 'category', 'tagline', 'description'], $search);
        }

        $services = $query->orderBy(['sort_order' => 'asc', 'name' => 'asc'])->get();

        return $this->view('admin.services', [
            'services' => $services,
            'planCounts' => $this->planCounts(),
            'categories' => (new Service())->categories(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, $this->rules(), $this->labels());
        $services = new Service();

        $slug = $this->resolveSlug((string) ($data['slug'] ?? ''), (string) $data['name'], 0);

        if ($slug === '') {
            return $this->refuse('Add a slug or a name we can turn into one.');
        }

        $id = $services->create($this->row($data, $slug));

        AuditLog::record('service.created', 'service', $id, sprintf(
            'Created service "%s" (%s) in category "%s".',
            (string) $data['name'],
            $slug,
            (string) $data['category']
        ));

        return $this->redirect(route('admin.services'), 'success', sprintf('"%s" was added to the catalogue.', (string) $data['name']));
    }

    public function update(Request $request, string $id): Response
    {
        $serviceId = (int) $id;
        $services = new Service();
        $service = $services->find($serviceId);

        if ($service === null) {
            throw new HttpException(404, 'That service does not exist.');
        }

        $data = $this->validate($request, $this->rules($serviceId), $this->labels());

        // An empty slug on an edit means "keep the one on file", which keeps the
        // public /plans/{slug} links pointing where customers already shared them.
        $slug = $this->resolveSlug((string) ($data['slug'] ?? ''), (string) $data['name'], $serviceId, (string) $service['slug']);

        if ($slug === '') {
            return $this->refuse('Add a slug or a name we can turn into one.');
        }

        $services->update($serviceId, $this->row($data, $slug));

        AuditLog::record('service.updated', 'service', $serviceId, sprintf(
            'Updated service "%s" (%s).',
            (string) $data['name'],
            $slug
        ));

        return $this->redirect(route('admin.services'), 'success', sprintf('"%s" was saved.', (string) $data['name']));
    }

    public function destroy(Request $request, string $id): Response
    {
        $serviceId = (int) $id;
        $services = new Service();
        $service = $services->find($serviceId);

        if ($service === null) {
            throw new HttpException(404, 'That service does not exist.');
        }

        // Destructive action: the form carries an in-page confirmation checkbox,
        // and nothing is touched until it is ticked.
        if ((string) $request->input('confirm', '') !== '1') {
            return $this->redirect(
                route('admin.services'),
                'error',
                'Tick the confirmation box inside the delete form before removing a service.'
            );
        }

        // plans.service_id is an ON DELETE CASCADE foreign key, so deleting a
        // service silently erases its plans (and detaches them from order history).
        // Refuse when any remain and tell the admin to deactivate instead.
        $planCount = (new Plan())->query()->where('service_id', $serviceId)->count();

        if ($planCount > 0) {
            AuditLog::record('service.delete_refused', 'service', $serviceId, sprintf(
                'Refused to delete "%s": %d plan(s) still hang off it.',
                (string) $service['name'],
                $planCount
            ));

            return $this->redirect(
                route('admin.services'),
                'error',
                sprintf(
                    '"%s" still has %d plan(s). Deleting it would cascade to those plans, so set it to inactive instead - '
                    . 'customers already hide it, and your order history keeps its links.',
                    (string) $service['name'],
                    $planCount
                )
            );
        }

        $services->delete($serviceId);

        AuditLog::record('service.deleted', 'service', $serviceId, sprintf(
            'Deleted service "%s" (%s); it had no plans left.',
            (string) $service['name'],
            (string) $service['slug']
        ));

        return $this->redirect(route('admin.services'), 'success', sprintf('"%s" was deleted.', (string) $service['name']));
    }

    /**
     * @param int $ignoreId service id excluded from the slug-unique rule (0 on create)
     * @return array<string, string>
     */
    private function rules(int $ignoreId = 0): array
    {
        $uniqueSlug = 'unique:services,slug' . ($ignoreId > 0 ? ',' . $ignoreId : '');

        return [
            'slug' => 'max:80|slug|' . $uniqueSlug,
            'name' => 'required|max:80',
            'category' => 'required|max:60',
            'brand_color' => 'max:20|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo_mark' => 'max:6',
            'tagline' => 'max:160',
            'description' => 'trim|max:20000',
            'popular' => 'integer|between:0,127',
            'sort_order' => 'integer',
            'show_in_home' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        return [
            'slug' => 'Slug',
            'name' => 'Name',
            'category' => 'Category',
            'brand_color' => 'Brand colour',
            'logo_mark' => 'Logo mark',
            'tagline' => 'Tagline',
            'popular' => 'Popularity',
            'sort_order' => 'Sort order',
        ];
    }

    /**
     * Only the whitelisted columns are written; the rest of the request never
     * reaches the row.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function row(array $data, string $slug): array
    {
        return [
            'slug' => $slug,
            'name' => (string) $data['name'],
            'category' => (string) $data['category'],
            'brand_color' => (string) ($data['brand_color'] ?? self::FALLBACK_COLOR) ?: self::FALLBACK_COLOR,
            'logo_mark' => (string) ($data['logo_mark'] ?? '') ?: self::FALLBACK_MARK,
            'tagline' => (string) ($data['tagline'] ?? '') ?: null,
            'description' => (string) ($data['description'] ?? '') ?: null,
            'popular' => (int) ($data['popular'] ?? 0),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'show_in_home' => (int) ($data['show_in_home'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];
    }

    /**
     * Honour a supplied slug (already proven unique by validation); otherwise
     * build one from the name and refuse loudly if it collides.
     */
    private function resolveSlug(string $given, string $name, int $ignoreId, string $keep = ''): string
    {
        $slug = $given !== '' ? $given : ($name !== '' ? $this->slugify($name) : $keep);

        if ($slug === '') {
            return '';
        }

        if ($slug === $keep) {
            return $slug;
        }

        $query = (new Service())->query()->where('slug', $slug);

        if ($ignoreId > 0) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists() ? '' : $slug;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return mb_substr($slug, 0, 80);
    }

    /** @return array<int, int> service_id => plan count */
    private function planCounts(): array
    {
        $counts = [];

        foreach ((new Plan())->query()->select('service_id')->selectRaw('count(*) as total')->groupBy('service_id')->get() as $row) {
            $counts[(int) $row['service_id']] = (int) $row['total'];
        }

        return $counts;
    }

    private function refuse(string $message): Response
    {
        Session::flash('error', $message);

        return $this->redirect(route('admin.services'));
    }
}
