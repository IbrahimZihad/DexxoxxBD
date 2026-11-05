<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Models\AuditLog;

/**
 * Site settings, stored in the `settings` table and editable here. The route is
 * owner-gated (routes/web.php), so an admin is already confirmed.
 *
 * The screen is built from the keys that actually exist in the database (merged
 * with the in-code defaults), grouped by their real `group_name`, never from a
 * hardcoded list. On save, ONLY keys already in that allowed set are written, so:
 *  - a crafted form field cannot mass-assign anything unexpected;
 *  - APP_KEY and the DB credentials - which are never rows in `settings` - cannot
 *    be accepted or written from this form at all.
 *
 * Booleans follow how SettingsSeeder stores them: the string "1" (or "0").
 */
final class SettingController extends Controller
{
    /** Groups in the order the tabs are shown; anything unseen appends at the end. */
    private const GROUP_ORDER = ['general', 'whatsapp', 'payment', 'contact', 'trust', 'checkout'];

    /** Keys the storefront treats as on/off toggles. */
    private const BOOL_KEYS = ['whatsapp.enabled', 'announcement.active', 'checkout.allow_guest'];

    private const INT_KEYS = ['trust.delivery_minutes', 'trust.orders_delivered', 'order.pending_hold_minutes'];

    private const DECIMAL_KEYS = ['trust.rating'];

    private const TEXTAREA_KEYS = [
        'meta.description', 'hero.subtitle', 'footer.note', 'payment.instruction',
        'whatsapp.quick_replies', 'whatsapp.greeting', 'whatsapp.hours', 'announcement.text',
    ];

    /**
     * @var array<string, array{label: string, description: string}>
     */
    private const GROUP_META = [
        'general' => [
            'label' => 'General',
            'description' => 'The brand name, tagline and the marketing copy the home page and footer print.',
        ],
        'whatsapp' => [
            'label' => 'WhatsApp & chat',
            'description' => 'The floating chat head and every wa.me link read these. The number lives in this table, not in the code.',
        ],
        'payment' => [
            'label' => 'Payment',
            'description' => 'The bKash / Nagad / Rocket numbers and bank details customers send money to. A person verifies every transfer.',
        ],
        'contact' => [
            'label' => 'Contact',
            'description' => 'Where customers reach you: shown in the footer, the contact page and order emails.',
        ],
        'trust' => [
            'label' => 'Trust figures',
            'description' => 'The numbers in the social-proof strip: guarantee, delivery time, orders delivered, rating.',
        ],
        'checkout' => [
            'label' => 'Checkout',
            'description' => 'How long an unpaid order is held, and whether guests may check out.',
        ],
    ];

    public function index(Request $request): Response
    {
        unset($request);

        return $this->view('admin.settings', [
            'tabs' => $this->tabs(),
            'groupMeta' => self::GROUP_META,
        ]);
    }

    public function update(Request $request): Response
    {
        $allowed = $this->allowedKeys();

        $this->validate($request, $this->rulesFor($allowed), $this->labelsFor($allowed));

        $before = Settings::all();
        // Key presence, not value presence: a field submitted empty is the admin
        // deliberately clearing it, and that must still be written.
        $submitted = $request->all();
        $changed = [];
        $absent = [];

        foreach ($allowed as $key) {
            // A key the payload never mentions is left exactly as it stands.
            // Writing '' for it instead lets one partial submit - a hand-built
            // form, a request trimmed by PHP's max_input_vars - erase the site
            // copy field by field, which is how a bare POST came to blank 29 of
            // the 32 settings rows. The real form renders every allowed key
            // (booleans as hidden-0 plus checkbox), so a genuine save always
            // carries all of them and is unaffected by this guard.
            if (!array_key_exists($key, $submitted)) {
                $absent[] = $key;
                continue;
            }

            $value = $this->coerce($key, (string) $request->input($key, ''));

            if (($before[$key] ?? '') !== $value) {
                $changed[] = $key;
            }

            Settings::put($key, $value);
        }

        // Drop the in-request cache so the very next render (after the redirect)
        // reads the fresh rows instead of the values loaded during this request.
        Settings::forgetCache();

        $note = $changed === []
            ? 'Saved site settings; no values changed.'
            : sprintf('Saved site settings; %d key(s) updated: %s.', count($changed), implode(', ', $changed));

        if ($absent !== []) {
            $note = sprintf('Partial submit: %d of the %d settings were not posted and were left untouched (%s).',
                count($absent), count($allowed), implode(', ', array_slice($absent, 0, 12))
            ) . ($changed === [] ? '' : ' ' . $note);
        }

        AuditLog::record('settings.updated', 'settings', 0, $note);

        return $this->redirect(
            route('admin.settings'),
            $absent === [] ? 'success' : 'warning',
            $absent === []
                ? ($changed === [] ? 'Nothing had changed - settings saved as they were.' : 'Settings saved.')
                : sprintf('Saved %d field(s). %d setting(s) were not submitted and were left unchanged - reload the page and save again to edit those.',
                    count($changed), count($absent))
        );
    }

    /**
     * Every key the form may legitimately show or write: the rows in the
     * settings table plus the in-code defaults. APP_KEY and DB credentials are
     * never in either set, so they can never reach Settings::put() from here.
     *
     * @return list<string>
     */
    private function allowedKeys(): array
    {
        $storedGroups = $this->storedGroups();
        $keys = array_unique(array_merge(array_keys($storedGroups), array_keys(Settings::defaults())));
        sort($keys);

        return array_values($keys);
    }

    /**
     * Build the tab model the view renders: one entry per group that has fields,
     * in GROUP_ORDER, each holding its sorted fields with a value, an input type
     * and a human label.
     *
     * @return list<array{name: string, fields: list<array<string, string>>}>
     */
    private function tabs(): array
    {
        $storedGroups = $this->storedGroups();
        $values = Settings::all();

        $byGroup = [];
        foreach ($this->allowedKeys() as $key) {
            $group = $this->groupOf($key, $storedGroups);
            $byGroup[$group][] = [
                'key' => $key,
                'value' => (string) ($values[$key] ?? ''),
                'type' => $this->typeOf($key),
                'label' => $this->labelOf($key),
            ];
        }

        $ordered = [];
        foreach (self::GROUP_ORDER as $group) {
            if (isset($byGroup[$group])) {
                $ordered[] = ['name' => $group, 'fields' => $byGroup[$group]];
                unset($byGroup[$group]);
            }
        }
        ksort($byGroup);
        foreach ($byGroup as $group => $fields) {
            $ordered[] = ['name' => (string) $group, 'fields' => $fields];
        }

        foreach ($ordered as $index => $tab) {
            usort($ordered[$index]['fields'], static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));
        }

        return $ordered;
    }

    /** @return array<string, string> key => stored group_name */
    private function storedGroups(): array
    {
        $groups = [];

        foreach (Database::table('settings')->select('key', 'group_name')->get() as $row) {
            $key = (string) ($row['key'] ?? '');
            $group = trim((string) ($row['group_name'] ?? ''));

            if ($key !== '') {
                $groups[$key] = $group === '' ? 'general' : $group;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, string> $storedGroups
     */
    private function groupOf(string $key, array $storedGroups): string
    {
        if (isset($storedGroups[$key])) {
            return $storedGroups[$key];
        }

        // A default-only key: infer its group from the prefix so the tab still
        // appears even before the row has ever been saved.
        return match (explode('.', $key)[0]) {
            'whatsapp' => 'whatsapp',
            'payment' => 'payment',
            'contact' => 'contact',
            'trust' => 'trust',
            'order', 'checkout' => 'checkout',
            default => 'general',
        };
    }

    /**
     * @param list<string> $allowed
     * @return array<string, string>
     */
    private function rulesFor(array $allowed): array
    {
        $rules = [];

        foreach ($allowed as $key) {
            $rules[$key] = match (true) {
                in_array($key, self::BOOL_KEYS, true) => 'boolean',
                in_array($key, self::INT_KEYS, true) => 'integer|between:0,100000000',
                in_array($key, self::DECIMAL_KEYS, true) => 'numeric|between:0,10',
                default => 'max:2000',
            };
        }

        return $rules;
    }

    /**
     * @param list<string> $allowed
     * @return array<string, string>
     */
    private function labelsFor(array $allowed): array
    {
        $labels = [];

        foreach ($allowed as $key) {
            $labels[$key] = $this->labelOf($key);
        }

        return $labels;
    }

    private function typeOf(string $key): string
    {
        return match (true) {
            in_array($key, self::BOOL_KEYS, true) => 'bool',
            in_array($key, self::INT_KEYS, true) => 'int',
            in_array($key, self::DECIMAL_KEYS, true) => 'number',
            in_array($key, self::TEXTAREA_KEYS, true) => 'textarea',
            default => 'text',
        };
    }

    private function labelOf(string $key): string
    {
        $overrides = [
            'whatsapp.number' => 'WhatsApp number',
            'whatsapp.enabled' => 'Enable the chat widget',
            'whatsapp.agent_name' => 'Agent name',
            'whatsapp.agent_role' => 'Agent role',
            'whatsapp.greeting' => 'Greeting',
            'whatsapp.default_message' => 'Pre-filled message',
            'whatsapp.hours' => 'Opening hours',
            'whatsapp.quick_replies' => 'Quick replies',
            'announcement.active' => 'Show the announcement bar',
            'checkout.allow_guest' => 'Allow guest checkout',
            'order.pending_hold_minutes' => 'Hold unpaid orders (minutes)',
            'trust.delivery_minutes' => 'Delivery promise (minutes)',
            'trust.orders_delivered' => 'Orders delivered',
            'trust.rating' => 'Average rating',
        ];

        if (isset($overrides[$key])) {
            return $overrides[$key];
        }

        $suffix = str_contains($key, '.') ? substr($key, (int) strrpos($key, '.') + 1) : $key;

        return ucwords(str_replace('_', ' ', $suffix));
    }

    /**
     * Turn a raw submitted string into the exact form this key is stored in.
     * Booleans become "1"/"0" (matching the seeder); everything else is trimmed,
     * with an empty value stored as empty (a cleared setting).
     */
    private function coerce(string $key, string $raw): string
    {
        $raw = trim($raw);

        if (in_array($key, self::BOOL_KEYS, true)) {
            return in_array(strtolower($raw), ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
        }

        if (in_array($key, self::INT_KEYS, true) || in_array($key, self::DECIMAL_KEYS, true)) {
            return $raw;
        }

        return mb_substr($raw, 0, 2000);
    }
}
