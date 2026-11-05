<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Every shop-editable string in one place. The app also has in-code defaults, so
 * this seeder is what makes the admin settings screen show something real.
 */
final class SettingsSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        $settings = [
            ['general', 'brand.name', 'DexxozzBD'],
            ['general', 'brand.tagline', 'Premium subscriptions, delivered in minutes.'],
            ['general', 'brand.logo', 'img/dexxoxxbd.jpeg'],
            ['general', 'meta.description', 'Netflix, Spotify, YouTube Premium, ChatGPT Plus and more at Bangladeshi prices, delivered on WhatsApp in minutes.'],
            ['general', 'hero.badge', '12,400+ orders delivered across Bangladesh'],
            ['general', 'hero.title', 'Every premium app you actually use, at a Bangladeshi price.'],
            ['general', 'hero.subtitle', 'Pick a plan, pay with bKash or Nagad, get your account in minutes. A real human answers on WhatsApp.'],
            ['general', 'announcement.active', '1'],
            ['general', 'announcement.text', 'Instant delivery before 11 PM - bKash, Nagad, Rocket or card accepted.'],
            ['general', 'footer.note', 'DexxozzBD is an independent reseller of publicly available subscription services. We are not affiliated with, or endorsed by, the brands listed here.'],

            ['whatsapp', 'whatsapp.enabled', '1'],
            ['whatsapp', 'whatsapp.number', '8801712345678'],
            ['whatsapp', 'whatsapp.agent_name', 'Rafi'],
            ['whatsapp', 'whatsapp.agent_role', 'Order desk, DexxozzBD'],
            ['whatsapp', 'whatsapp.greeting', 'Hi! Need a hand choosing a plan?'],
            ['whatsapp', 'whatsapp.default_message', 'Hello DexxozzBD, I want to order a subscription.'],
            ['whatsapp', 'whatsapp.hours', '9:00 AM - 11:00 PM, every day (Dhaka time)'],
            ['whatsapp', 'whatsapp.quick_replies', 'Which plan suits me?|How fast is delivery?|What if the account stops working?|Do you have a student price?'],

            ['payment', 'payment.bkash_number', '01712-345678'],
            ['payment', 'payment.nagad_number', '01812-345678'],
            ['payment', 'payment.rocket_number', '01912-345678-1'],
            ['payment', 'payment.bank_account', 'City Bank Ltd, Dhaka branch - Account 1234 5678 9012 (DexxozzBD)'],
            ['payment', 'payment.instruction', 'Send the exact total, then paste the transaction ID. A person checks it against our statement before anything is marked paid.'],

            ['contact', 'contact.email', 'support@dexxozzbd.test'],
            ['contact', 'contact.phone', '+880 1712-345678'],
            ['contact', 'contact.address', 'Level 4, Banani, Dhaka 1213'],

            ['trust', 'trust.guarantee', '7-day replacement on every personal plan'],
            ['trust', 'trust.delivery_minutes', '10'],
            ['trust', 'trust.orders_delivered', '12400'],
            ['trust', 'trust.rating', '4.8'],

            ['checkout', 'order.pending_hold_minutes', '90'],
            ['checkout', 'checkout.allow_guest', '1'],
        ];

        $table = $this->table('settings');
        $table->truncate();

        $rows = [];

        foreach ($settings as [$group, $key, $value]) {
            $rows[] = [
                'key' => $key,
                'value' => $value,
                'group_name' => $group,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $table->insert($rows)->saveData();
    }
}
