<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Social proof and the answer page. Written as a Bangladeshi customer would
 * phrase it, because the trust section is the part of a reseller storefront
 * people actually read before paying.
 */
final class ContentSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $testimonials = [
            ['Tanvir A.', 'Dhaka', 'Netflix Premium', 5, 'Paid at 9:40 PM, login was in my WhatsApp by 9:52. I have used three sellers before and nobody was this fast.'],
            ['Mehedi Hasan', 'Chattogram', 'Spotify Premium', 5, 'My playlists survived the switch, which is the only thing I cared about. Second month with them now.'],
            ['Farhana Akter', 'Sylhet', 'Canva Pro', 5, 'They added my own email to the Pro team, so all my designs are still mine. Took about fifteen minutes.'],
            ['Sabbir Rahman', 'Khulna', 'ChatGPT Plus', 4, 'GPT Plus works perfectly. Slightly long wait on Friday night because they had too many orders, and they told me the delay before I paid.'],
            ['Nusrat Jahan', 'Dhaka', 'YouTube Premium', 5, 'Bought the family plan with three flatmates. Each account is separate, nobody is fighting over a profile.'],
            ['Rakibul Islam', 'Rajshahi', 'Disney+ Hotstar', 5, 'Watched the whole Bangladesh cricket series without a single buffer. Worth every taka.'],
            ['Shahriar Kabir', 'Narayanganj', 'Microsoft 365', 4, 'Office installed on my laptop and phone within the hour. Support replied on WhatsApp in minutes.'],
            ['Ayesha Chowdhury', 'Dhaka', 'Google One 2TB', 5, 'Photos were about to run out of storage. 2TB landed on my account the same evening.'],
            ['Imran Hossain', 'Barishal', 'Netflix Premium', 5, 'The 7-day replacement promise is real. My first account got locked by Netflix, they swapped it the same day at no cost.'],
            ['Jannatul Ferdous', 'Comilla', 'Claude Pro', 4, 'Claude Pro on my own email. Price is much better than paying with a card I do not have.'],
        ];

        $faqs = [
            ['How is this cheaper than paying for the subscription myself?', 'We buy in larger volumes and on plans that Bangladeshi cards cannot access directly, then resell seats at a local price. Your account is a real subscription, not a shared password you have to argue over.', 'pricing'],
            ['How do I pay?', 'bKash, Nagad or Rocket personal transfer, or card on the checkout page. Send the exact total, paste the transaction ID, and a person matches it against our statement before anything is marked paid.', 'payment'],
            ['How long until I get my account?', 'Most orders go out within 10 to 15 minutes between 9 AM and 11 PM. Outside those hours the queue is worked first thing in the morning, and the order page tells you where you are.', 'delivery'],
            ['Do I get my own login?', 'Yes for every personal plan. You can change the password after the first sign-in. Family and multi-seat plans are separate accounts, each with its own profile.', 'delivery'],
            ['Can I use it on my TV?', 'Depends on the plan. Netflix Premium, Disney+ and Prime Video work on TV apps; the device list is written on each plan card before you add it to the cart.', 'delivery'],
            ['What if the account stops working?', 'Send us a WhatsApp message with the order number. Within the guarantee window we replace the account or refund the unused days. No forms, no arguing.', 'after-sales'],
            ['Is my money safe if you cannot deliver?', 'Yes. Nothing is marked paid until a human verifies the transaction, and if we cannot source the plan we refund the full amount the same day.', 'payment'],
            ['Do you keep my password?', 'No. Delivery notes are encrypted at rest and only the agent handling your order can open them. We ask you to change the password on first login.', 'security'],
            ['Can I upgrade in the middle of my term?', 'Message us the order number. You pay the difference for the remaining days and we move you to the higher plan.', 'after-sales'],
            ['Do you offer a student price?', 'Yes, use the coupon STUDENT15 on any order above 500 taka. One use per email address.', 'pricing'],
            ['Which Netflix region do I get?', 'Whichever plan you order is listed on the card. Region affects the catalogue, so read the plan note before checkout if a specific title matters to you.', 'delivery'],
            ['Can I cancel and get money back?', 'Before we deliver, cancel any time from the order page for a full refund. After delivery, the guarantee covers replacement and unused days.', 'after-sales'],
        ];

        $testimonialsTable = $this->table('testimonials');
        $testimonialsTable->truncate();

        $sort = 0;
        $rows = [];

        foreach ($testimonials as [$name, $location, $service, $rating, $quote]) {
            $sort++;
            $rows[] = [
                'name' => $name,
                'location' => $location,
                'service_label' => $service,
                'rating' => $rating,
                'quote' => $quote,
                'sort_order' => $sort,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $testimonialsTable->insert($rows)->saveData();

        $faqTable = $this->table('faqs');
        $faqTable->truncate();

        $sort = 0;
        $rows = [];

        foreach ($faqs as [$question, $answer, $category]) {
            $sort++;
            $rows[] = [
                'question' => $question,
                'answer' => $answer,
                'category' => $category,
                'sort_order' => $sort,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $faqTable->insert($rows)->saveData();
    }
}
