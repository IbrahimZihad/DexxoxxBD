<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\MailService;

/**
 * Newsletter signup with double opt-in.
 *
 * A submitted address only ever becomes a "pending" row; the subscription is
 * activated by a link that carries a random token whose SHA-256 hash is what we
 * store. An attacker who reads the table cannot confirm their own address, and a
 * mistyped address simply never receives the email.
 */
final class NewsletterController extends Controller
{
    private const TOKEN_BYTES = 20;

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email|max:190',
        ]);

        $email = strtolower(trim((string) $data['email']));
        $existing = db()->selectOne(
            'SELECT id, status FROM newsletter_subscribers WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        if (is_array($existing) && (string) $existing['status'] === 'subscribed') {
            flash('info', t('page.newsletter.already'));
            return $this->back();
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        if (is_array($existing)) {
            db()->update('newsletter_subscribers', [
                'status' => 'pending',
                'token_hash' => $this->hash($token),
                'locale' => current_locale(),
                'unsubscribed_at' => null,
            ], 'id = :id', ['id' => (int) $existing['id']]);
        } else {
            db()->insert('newsletter_subscribers', [
                'email' => $email,
                'name' => $this->knownName(),
                'locale' => current_locale(),
                'status' => 'pending',
                'token_hash' => $this->hash($token),
                'ip_address' => $request->ip(),
            ]);
        }

        $subject = t('page.newsletter.confirm_subject');

        $this->mailer()->send(
            $subject,
            'emails.newsletter-confirm',
            [
                'subject' => $subject,
                'preheader' => t('page.newsletter.confirm_preheader'),
                'heading' => t('page.newsletter.confirm_heading'),
                'lines' => [t('page.newsletter.confirm_text')],
                'button' => [
                    'text' => t('page.newsletter.confirm_cta'),
                    'url' => url('newsletter/confirm/' . $token),
                ],
            ],
            $email,
            $this->knownName()
        );

        AuditService::recordForCurrentUser('newsletter.subscribe_requested', ['email' => $email]);

        flash('success', t('page.newsletter.check_inbox'));

        return $this->back();
    }

    public function confirm(Request $request, string $token): Response
    {
        unset($request);

        $token = strtolower(trim($token));
        if (preg_match('/^[a-f0-9]{40}$/', $token) !== 1) {
            flash('danger', t('page.newsletter.link_expired'));
            return $this->redirect('');
        }

        $row = db()->selectOne(
            'SELECT id, status FROM newsletter_subscribers WHERE token_hash = :hash LIMIT 1',
            ['hash' => $this->hash($token)]
        );

        if (!is_array($row)) {
            flash('danger', t('page.newsletter.link_expired'));
            return $this->redirect('');
        }

        if ((string) $row['status'] === 'subscribed') {
            flash('info', t('page.newsletter.already'));
            return $this->redirect('');
        }

        db()->update('newsletter_subscribers', [
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'token_hash' => null,
        ], 'id = :id', ['id' => (int) $row['id']]);

        AuditService::recordForCurrentUser('newsletter.subscribed', ['subscriber_id' => (int) $row['id']]);

        flash('success', t('page.newsletter.subscribed'));

        return $this->redirect('');
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Prefill the subscriber name from the signed-in account, if any. */
    private function knownName(): string
    {
        $user = Auth::instance()->user();

        return $user === null ? '' : trim((string) ($user['name'] ?? ''));
    }

    private function mailer(): MailService
    {
        /** @var MailService $mailer */
        $mailer = App::instance()->make('mailer');
        return $mailer;
    }
}
