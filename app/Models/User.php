<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Query;

final class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'name', 'email', 'phone', 'password_hash', 'role', 'is_active',
        'last_login_at', 'marketing_opt_in', 'created_at', 'updated_at',
    ];

    public function findByEmail(string $email): ?array
    {
        return $this->query()->where('email', mb_strtolower(trim($email)))->first();
    }

    /** @return array<string, mixed>|null */
    public function findActive(int $id): ?array
    {
        return $this->query()->where('id', $id)->where('is_active', 1)->first();
    }

    public function emailExists(string $email): bool
    {
        return $this->query()->where('email', mb_strtolower(trim($email)))->exists();
    }

    public function register(string $name, string $email, ?string $phone, string $password): int
    {
        return $this->create([
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'phone' => $phone,
            'password_hash' => Auth::hash($password),
            'role' => 'customer',
            'is_active' => 1,
        ]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        Database::execute('update users set password_hash = :hash where id = :id', [':hash' => $hash, ':id' => $id]);
    }

    public function touchLastLogin(int $id): void
    {
        Database::execute('update users set last_login_at = now() where id = :id', [':id' => $id]);
    }

    public function staffQuery(): Query
    {
        return $this->query()->whereIn('role', ['admin', 'staff'])->orderBy(['created_at' => 'desc']);
    }

    /**
     * Paginated customer list with an optional search across name and email.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
     */
    public function paginateCustomers(array $filters, int $page, int $perPage = 20): array
    {
        $query = $this->query()->orderBy(['created_at' => 'desc']);

        if (($filters['role'] ?? '') !== '') {
            $query->where('role', (string) $filters['role']);
        }

        if (($filters['search'] ?? '') !== '') {
            $query->search(['name', 'email', 'phone'], (string) $filters['search']);
        }

        return $query->paginate($page, $perPage);
    }

    /** @return array{customers: int, staff: int, active: int} */
    public function counts(): array
    {
        return [
            'customers' => $this->query()->where('role', 'customer')->count(),
            'staff' => $this->query()->whereIn('role', ['admin', 'staff'])->count(),
            'active' => $this->query()->where('is_active', 1)->count(),
        ];
    }

    /** Order totals for the account page. */
    public function orderSummary(int $userId): array
    {
        $row = Database::selectOne(
            'select count(*) as orders,
                    coalesce(sum(case when payment_status = :paid then total else 0 end), 0) as spent
               from orders where user_id = :user',
            [':paid' => 'paid', ':user' => $userId]
        );

        return [
            'orders' => (int) ($row['orders'] ?? 0),
            'spent' => (float) ($row['spent'] ?? 0),
        ];
    }
}
