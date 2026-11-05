<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

final class OrderItem extends Model
{
    protected string $table = 'order_items';

    protected array $fillable = [
        'order_id', 'plan_id', 'service_name', 'plan_title', 'unit_price',
        'quantity', 'line_total', 'status', 'delivery_note', 'delivered_at',
        'created_at', 'updated_at',
    ];

    /** @return list<array<string, mixed>> */
    public function forOrder(int $orderId): array
    {
        return $this->query()->where('order_id', $orderId)->orderBy(['id' => 'asc'])->get();
    }

    /**
     * The delivery note is stored encrypted (see App\Core\Crypto); this decrypts
     * it for the customer's order page and for the admin detail screen.
     */
    public function withDecryptedNote(array $item): array
    {
        $item['delivery_note_plain'] = Crypto::decrypt($item['delivery_note'] ?? null);

        return $item;
    }

    public function markDelivered(int $itemId, string $note): void
    {
        Database::execute(
            'update order_items set status = :status, delivery_note = :note, delivered_at = now()
              where id = :id',
            [':status' => 'delivered', ':note' => Crypto::encrypt($note), ':id' => $itemId]
        );
    }

    public function pendingDeliveryCount(): int
    {
        return (int) Database::scalar(
            'select count(*) from order_items oi
              inner join orders o on o.id = oi.order_id
             where oi.status = :pending and o.payment_status = :paid',
            [':pending' => 'pending', ':paid' => 'paid']
        );
    }

    /** @return list<array<string, mixed>> */
    public function pendingDelivery(int $limit = 25): array
    {
        return Database::select(
            'select oi.*, o.order_no, o.customer_name, o.email, o.phone, o.created_at as ordered_at
               from order_items oi
               inner join orders o on o.id = oi.order_id
              where oi.status = :pending and o.payment_status = :paid
              order by oi.id asc
              limit ' . max(1, (int) $limit),
            [':pending' => 'pending', ':paid' => 'paid']
        );
    }

    /**
     * "delivered" once every line on the order has been handed over, otherwise
     * "assigned". The caller writes it back onto the order.
     */
    public function fulfillmentState(int $orderId): string
    {
        $row = Database::selectOne(
            'select count(*) as pending from order_items where order_id = :id and status <> :delivered',
            [':id' => $orderId, ':delivered' => 'delivered']
        );

        return (int) ($row['pending'] ?? 0) === 0 ? 'delivered' : 'assigned';
    }
}
