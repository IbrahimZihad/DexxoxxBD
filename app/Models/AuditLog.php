<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use Throwable;

/**
 * Append-only record of everything staff changed. Rows are never updated or
 * deleted by the app.
 */
final class AuditLog extends Model
{
    protected string $table = 'admin_audit_logs';

    protected bool $timestamps = false;

    protected array $fillable = ['user_id', 'user_name', 'action', 'entity', 'entity_id', 'description', 'ip_address', 'created_at'];

    public static function record(string $action, string $entity = '', int $entityId = 0, string $description = ''): void
    {
        $user = Auth::user();

        try {
            $ip = App::instance()->request()->ip();
        } catch (Throwable) {
            $ip = 'cli';
        }

        Database::execute(
            'insert into admin_audit_logs
                (user_id, user_name, action, entity, entity_id, description, ip_address, created_at)
             values (:user_id, :user_name, :action, :entity, :entity_id, :description, :ip, :created)',
            [
                ':user_id' => $user === null ? null : (int) $user['id'],
                ':user_name' => (string) ($user['name'] ?? 'system'),
                ':action' => $action,
                ':entity' => mb_substr($entity, 0, 40),
                ':entity_id' => $entityId,
                ':description' => mb_substr($description, 0, 500),
                ':ip' => mb_substr($ip, 0, 45),
                ':created' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /** @return array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} */
    public function paginateLogs(int $page, int $perPage = 40): array
    {
        return $this->query()->orderBy(['id' => 'desc'])->paginate($page, $perPage);
    }
}
