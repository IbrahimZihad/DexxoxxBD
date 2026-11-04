<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\AuditService;

/**
 * Contact form.
 *
 * support_tickets.user_id is NOT NULL, so a message always belongs to a real,
 * email-verified account. A guest sees the support details and a sign-in prompt
 * instead of a form that could not be stored, which also removes the anonymous
 * inbox a spam bot would otherwise fill.
 */
final class ContactController extends Controller
{
    private const DEPARTMENTS = ['general', 'order', 'payment', 'delivery', 'technical'];

    public function index(): Response
    {
        $user = Auth::instance()->user();

        return $this->view('pages.contact', [
            'departments' => self::DEPARTMENTS,
            'canSubmit' => $user !== null && !empty($user['email_verified_at']),
            'needsVerification' => $user !== null && empty($user['email_verified_at']),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::instance()->user();
        if ($user === null) {
            throw new HttpException(401, 'Please sign in before sending a message.');
        }

        // Hidden field a bot is likely to fill. A quiet success keeps them from
        // learning that the trap exists.
        if ($request->string('website') !== '') {
            flash('success', t('page.contact.sent'));
            return $this->redirect('contact');
        }

        $data = $this->validate($request, [
            'subject' => 'required|string|min:5|max:190',
            'message' => 'required|string|min:20|max:4000',
            'department' => 'required|in:' . implode(',', self::DEPARTMENTS),
        ]);

        $subject = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $data['subject'])));
        $body = trim((string) $data['message']);
        $department = (string) $data['department'];

        $ticketId = db()->transaction(static function (Database $db) use ($user, $subject, $body, $department): int {
            // ticket_number is unique-indexed and NOT NULL, so the row is created
            // with a random placeholder and then renumbered from its own id -
            // TCK-000123 can never collide with another ticket.
            $id = $db->insert('support_tickets', [
                'ticket_number' => 'P-' . strtoupper(bin2hex(random_bytes(8))),
                'user_id' => (int) $user['id'],
                'department' => $department,
                'subject' => $subject,
                'body' => $body,
                'priority' => 'normal',
                'status' => 'open',
                'last_reply_at' => now(),
            ]);

            $db->update(
                'support_tickets',
                ['ticket_number' => sprintf('TCK-%06d', $id)],
                'id = :id',
                ['id' => $id]
            );

            $db->insert('support_messages', [
                'ticket_id' => $id,
                'user_id' => (int) $user['id'],
                'is_staff' => 0,
                'body' => $body,
            ]);

            return $id;
        });

        AuditService::recordForCurrentUser('support.ticket_created', [
            'ticket_id' => $ticketId,
            'department' => $department,
        ]);

        flash('success', t('page.contact.sent'));

        return $this->redirect('account/tickets');
    }
}
