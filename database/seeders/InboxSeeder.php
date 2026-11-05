<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Enquiries, so the admin inbox is not an empty table on a fresh install.
 * A few are pre-sales questions and a few are after-sales, which is what the
 * real queue looks like on a reseller storefront.
 */
final class InboxSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['UserSeeder'];
    }

    public function run(): void
    {
        $staffId = null;

        foreach ($this->fetchAll('select id, role from users') as $user) {
            if ((string) $user['role'] === 'staff') {
                $staffId = (int) $user['id'];
                break;
            }
        }

        $rows = [];

        foreach ($this->messages() as [$name, $email, $phone, $subject, $message, $channel, $status, $reply, $ageHours]) {
            $createdAt = date('Y-m-d H:i:s', time() - ($ageHours * 3600));

            $rows[] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
                'channel' => $channel,
                'status' => $status,
                'reply_note' => $reply,
                'answered_by' => $status === 'new' ? null : $staffId,
                'answered_at' => $status === 'new' ? null : date('Y-m-d H:i:s', strtotime($createdAt) + 1800),
                'ip_address' => '103.108.15.' . random_int(2, 250),
                'created_at' => $createdAt,
                'updated_at' => date('Y-m-d H:i:s', strtotime($createdAt) + ($status === 'new' ? 0 : 1800)),
            ];
        }

        $table = $this->table('contact_messages');
        $table->truncate();
        $table->insert($rows)->saveData();
    }

    /** @return list<array<int, string|null|int>> */
    private function messages(): array
    {
        return [
            [
                'Sadia Afrin', 'sadia@example.com', '01744556677', 'Netflix family plan',
                'Do you have a Netflix plan I can use on four TVs? Two of us in the flat want our own profiles.',
                'form', 'answered', 'Offered the Premium 4K 2-screen plus a second Standard account; total came to 1,596 taka for three months.', 3,
            ],
            [
                'Tariqul Islam', 'tariq@example.com', '01933221100', null,
                'Sent 1,296 taka from my bKash but the order page still says pending. My TrxID is BKS7H2K4Q9.',
                'whatsapp', 'answered', 'Statement showed the transfer at 21:14; marked paid and delivered the login inside ten minutes.', 26,
            ],
            [
                'Mim Akter', 'mim@example.com', '01655443322', 'Student discount',
                'I am a student at BRAC. Is there any discount for Spotify?',
                'chat', 'new', null, 1,
            ],
            [
                'Hasibul Hasan', null, '01877889900', 'Adobe all apps',
                'Can I pay in two parts? First half now, rest after my salary on the 15th.',
                'whatsapp', 'new', null, 5,
            ],
            [
                'Rubaiya Noor', 'rubaiya@example.com', '01511002233', 'ChatGPT Plus not working',
                'My Plus access shows as free again after two days. Order ZS-DEMO-01, email tanvir@example.com.',
                'form', 'answered', 'Re-issued on the same account within the guarantee window and apologised for the wait.', 50,
            ],
            [
                'Arif Mahmud', 'arif@example.com', '01799887766', 'Corporate quote',
                'We need eight Canva Pro seats for a small agency. Any bulk rate and a proper invoice with VAT?',
                'form', 'new', null, 9,
            ],
            [
                'Shamima Nasrin', 'shamima@example.com', null, 'Delivery timing',
                'If I order at 11:30 PM will I get the account that night or the next day?',
                'chat', 'answered', 'Orders after 11 PM are worked from 9 AM next morning; she ordered at 10:40 PM and got it before 11.', 74,
            ],
            [
                'Zubair Ahmed', 'zubair@example.com', '01322114455', 'Wrong plan ordered',
                'I bought the 1-month YouTube by mistake, I wanted 12 months. Can I just pay the difference?',
                'whatsapp', 'answered', 'Upgraded to 12 months on the same order, charged only the difference for the remaining days.', 98,
            ],
        ];
    }
}
