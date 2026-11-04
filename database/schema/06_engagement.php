<?php

declare(strict_types=1);

/**
 * Customer engagement schema: reviews, support tickets, in-app notifications
 * and the newsletter list.
 */

return [
    [
        'version' => '20261105003400',
        'class' => 'CreateReviewsTable',
        'table' => 'reviews',
        'comment' => 'Moderated product reviews; ratings are rolled up onto products.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['product_id', 'biginteger', ['signed' => false]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['order_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['author_name', 'string', ['limit' => 120]],
            ['rating', 'tinyinteger', ['signed' => false, 'default' => 5]],
            ['title', 'string', ['limit' => 190, 'null' => true]],
            ['body', 'text'],
            ['status', 'enum', ['values' => ['pending', 'approved', 'rejected'], 'default' => 'pending']],
            ['is_verified_purchase', 'boolean', ['default' => false]],
            ['helpful_count', 'integer', ['signed' => false, 'default' => 0]],
            ['owner_response', 'text', ['null' => true]],
            ['responded_at', 'datetime', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
            ['deleted_at', 'datetime', ['null' => true]],
        ],
        'indexes' => [
            ['reviews_product_status_index', ['product_id', 'status', 'created_at']],
            ['reviews_user_index', ['user_id']],
            ['reviews_order_index', ['order_id']],
            ['reviews_rating_index', ['rating']],
        ],
        'keys' => [
            ['name' => 'fk_reviews_product', 'column' => 'product_id', 'table' => 'products', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_reviews_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
            ['name' => 'fk_reviews_order', 'column' => 'order_id', 'table' => 'orders', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105003500',
        'class' => 'CreateSupportTicketsTable',
        'table' => 'support_tickets',
        'comment' => 'Customer support conversations.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['ticket_number', 'string', ['limit' => 24]],
            ['user_id', 'biginteger', ['signed' => false]],
            ['order_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['department', 'enum', ['values' => ['general', 'order', 'payment', 'delivery', 'technical'], 'default' => 'general']],
            ['subject', 'string', ['limit' => 190]],
            ['body', 'text'],
            ['priority', 'enum', ['values' => ['low', 'normal', 'high', 'urgent'], 'default' => 'normal']],
            ['status', 'enum', ['values' => ['open', 'answered', 'customer_reply', 'in_progress', 'resolved', 'closed'], 'default' => 'open']],
            ['assigned_to', 'biginteger', ['signed' => false, 'null' => true]],
            ['last_reply_at', 'datetime', ['null' => true]],
            ['closed_at', 'datetime', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
            ['deleted_at', 'datetime', ['null' => true]],
        ],
        'indexes' => [
            ['support_tickets_number_unique', ['ticket_number'], ['unique' => true]],
            ['support_tickets_user_status_index', ['user_id', 'status']],
            ['support_tickets_status_priority_index', ['status', 'priority']],
            ['support_tickets_assignee_index', ['assigned_to']],
            ['support_tickets_order_index', ['order_id']],
        ],
        'keys' => [
            ['name' => 'fk_tickets_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_tickets_order', 'column' => 'order_id', 'table' => 'orders', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
            ['name' => 'fk_tickets_assignee', 'column' => 'assigned_to', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105003600',
        'class' => 'CreateSupportMessagesTable',
        'table' => 'support_messages',
        'comment' => 'Replies inside a ticket, from customer or staff.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['ticket_id', 'biginteger', ['signed' => false]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['is_staff', 'boolean', ['default' => false]],
            ['body', 'text'],
            ['attachment', 'string', ['limit' => 255, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['support_messages_ticket_index', ['ticket_id', 'created_at']],
            ['support_messages_user_index', ['user_id']],
        ],
        'keys' => [
            ['name' => 'fk_messages_ticket', 'column' => 'ticket_id', 'table' => 'support_tickets', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_messages_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105003700',
        'class' => 'CreateNotificationsTable',
        'table' => 'notifications',
        'comment' => 'In-app notification inbox.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false]],
            ['type', 'string', ['limit' => 60]],
            ['title', 'string', ['limit' => 190]],
            ['message', 'text', ['null' => true]],
            ['link', 'string', ['limit' => 255, 'null' => true]],
            ['icon', 'string', ['limit' => 60, 'null' => true]],
            ['is_read', 'boolean', ['default' => false]],
            ['read_at', 'datetime', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['notifications_user_read_index', ['user_id', 'is_read', 'created_at']],
            ['notifications_type_index', ['type']],
        ],
        'keys' => [
            ['name' => 'fk_notifications_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105003800',
        'class' => 'CreateNewsletterSubscribersTable',
        'table' => 'newsletter_subscribers',
        'comment' => 'Double opt-in newsletter list.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['email', 'string', ['limit' => 190]],
            ['name', 'string', ['limit' => 120, 'null' => true]],
            ['locale', 'string', ['limit' => 5, 'default' => 'en']],
            ['status', 'enum', ['values' => ['pending', 'subscribed', 'unsubscribed'], 'default' => 'pending']],
            ['token_hash', 'char', ['limit' => 64, 'null' => true]],
            ['subscribed_at', 'datetime', ['null' => true]],
            ['unsubscribed_at', 'datetime', ['null' => true]],
            ['ip_address', 'string', ['limit' => 45, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['newsletter_email_unique', ['email'], ['unique' => true]],
            ['newsletter_status_index', ['status']],
        ],
    ],
];
