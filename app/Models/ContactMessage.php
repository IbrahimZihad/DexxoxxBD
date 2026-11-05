<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ContactMessage extends Model
{
    protected string $table = 'contact_messages';

    protected array $fillable = [
        'name', 'email', 'phone', 'subject', 'message', 'channel', 'status',
        'ip_address', 'reply_note', 'answered_by', 'answered_at', 'created_at', 'updated_at',
    ];

    /** @return array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} */
    public function inbox(string $status = '', int $page = 1, int $perPage = 25): array
    {
        $query = $this->query()->orderBy(['id' => 'desc']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        return $query->paginate($page, $perPage);
    }

    public function markAnswered(int $id, int $staffId, string $note = ''): void
    {
        Database::execute(
            'update contact_messages set status = :status, answered_by = :who, answered_at = now(),
                    reply_note = :note, updated_at = now()
              where id = :id',
            [':status' => 'answered', ':who' => $staffId, ':note' => $note, ':id' => $id]
        );
    }

    public function unreadCount(): int
    {
        return $this->query()->where('status', 'new')->count();
    }
}
