<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Payment extends Model
{
    protected string $table = 'payments';

    protected array $fillable = [
        'order_id', 'method', 'txn_id', 'amount', 'status', 'sender_number',
        'admin_note', 'verified_by', 'created_at', 'updated_at',
    ];

    /** @return list<array<string, mixed>> */
    public function forOrder(int $orderId): array
    {
        return $this->query()->where('order_id', $orderId)->orderBy(['id' => 'desc'])->get();
    }

    /**
     * A TrxID can only be used once. This is what stops one payment screenshot
     * being reused to claim several orders.
     */
    public function txnIdTaken(string $txnId, string $method): bool
    {
        return Database::selectOne(
            'select id from payments where txn_id = :txn and method = :method and status <> :rejected limit 1',
            [':txn' => strtoupper(trim($txnId)), ':method' => $method, ':rejected' => 'rejected']
        ) !== null;
    }

    public function pendingVerification(int $limit = 30): array
    {
        return Database::select(
            'select p.*, o.order_no, o.customer_name, o.email, o.phone
               from payments p
               inner join orders o on o.id = p.order_id
              where p.status = :pending
              order by p.id desc
              limit ' . max(1, (int) $limit),
            [':pending' => 'pending']
        );
    }

    public function verify(int $paymentId, int $staffId, string $note = ''): void
    {
        Database::execute(
            'update payments set status = :verified, verified_by = :who, admin_note = :note,
                    verified_at = now(), updated_at = now()
              where id = :id',
            [':verified' => 'verified', ':who' => $staffId, ':note' => $note, ':id' => $paymentId]
        );
    }

    public function reject(int $paymentId, int $staffId, string $note = ''): void
    {
        Database::execute(
            'update payments set status = :rejected, verified_by = :who, admin_note = :note, updated_at = now()
              where id = :id',
            [':rejected' => 'rejected', ':who' => $staffId, ':note' => $note, ':id' => $paymentId]
        );
    }
}
