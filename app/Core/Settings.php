<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Site settings stored in the database and editable from the admin panel, with
 * an in-code default for every key so a freshly migrated install still renders.
 */
final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $cached = null;

    private const DEFAULTS = [
        'brand.name' => 'DexxozzBD',
        'brand.tagline' => 'Premium subscriptions, delivered in minutes.',
        'meta.description' => 'Buy Netflix, Spotify, YouTube Premium, ChatGPT and more at Bangladeshi prices with instant WhatsApp support.',
        'whatsapp.enabled' => '1',
        'whatsapp.number' => '8801700000000',
        'whatsapp.agent_name' => 'DexxozzBD Support',
        'whatsapp.agent_role' => 'Order desk',
        'whatsapp.greeting' => 'Hi there! Need help choosing a plan?',
        'whatsapp.default_message' => 'Hello DexxozzBD, I would like to order a subscription.',
        'whatsapp.hours' => 'Open 9:00 AM - 11:00 PM (Dhaka time), every day',
        'whatsapp.quick_replies' => 'Which plan suits me?|How long does delivery take?|Do you offer a replacement if the account stops working?',
        'contact.email' => 'hello@dexxozzbd.example',
        'contact.phone' => '+880 1700-000000',
        'contact.address' => 'Dhaka, Bangladesh',
        'payment.bkash_number' => '01700-000000',
        'payment.nagad_number' => '01800-000000',
        'payment.rocket_number' => '01900-000000',
        'payment.bank_account' => 'City Bank 1234 5678 9012 (DexxozzBD)',
        'payment.instruction' => 'Send the exact amount with any personal number, then paste the TrxID below. We verify it manually and deliver within minutes.',
        'hero.badge' => 'Trusted by 12,000+ subscribers',
        'hero.title' => 'Every premium app you actually use, at a Bangladeshi price.',
        'hero.subtitle' => 'Pick a plan, pay with bKash or Nagad, and get your account details on WhatsApp within minutes.',
        'announcement.text' => 'Instant delivery on orders before 11 PM - pay with bKash, Nagad or card.',
        'announcement.active' => '1',
        'trust.guarantee' => '7-day replacement on every personal plan',
        'trust.delivery_minutes' => '10',
        'order.pending_hold_minutes' => '90',
        'checkout.allow_guest' => '1',
        'footer.note' => 'DexxozzBD is an independent reseller of publicly available subscription services. We are not affiliated with the brands we list.',
    ];

    public static function all(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $stored = [];

        try {
            foreach (Database::select('select `key`, `value` from settings') as $row) {
                $stored[(string) $row['key']] = (string) $row['value'];
            }
        } catch (\Throwable $exception) {
            // A missing table during a first-run install must not take the whole
            // page down; defaults are enough to render.
            Logger::warning('settings_unavailable', ['reason' => $exception->getMessage()]);
        }

        return self::$cached = array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        $value = $all[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function bool(string $key): bool
    {
        return in_array(strtolower(trim(self::get($key, ''))), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, (string) $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @return list<string> */
    public static function list(string $key): array
    {
        $raw = self::get($key);
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $raw)), static fn (string $v): bool => $v !== ''));
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public static function put(string $key, string $value): void
    {
        $existing = Database::selectOne('select `key` from settings where `key` = :key', [':key' => $key]);

        if ($existing === null) {
            Database::execute('insert into settings (`key`, `value`) values (:key, :value)', [':key' => $key, ':value' => $value]);
        } else {
            Database::execute('update settings set `value` = :value where `key` = :key', [':value' => $value, ':key' => $key]);
        }

        self::$cached = null;
    }

    public static function forgetCache(): void
    {
        self::$cached = null;
    }
}
