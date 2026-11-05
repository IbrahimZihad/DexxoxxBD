<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Models\ContactMessage;

/**
 * The contact inbox. Every enquiry the storefront, the chat head or WhatsApp
 * forwards lands here, and staff work it down to "answered".
 *
 * The message body and the phone numbers are customer data: this controller only
 * ever hands rows to the view (which escapes them with e()); nothing here puts a
 * message or a number into a URL, a query string or an audit line beyond the
 * fixed action wording.
 */
final class MessageController extends Controller
{
    /** The three states the contact_messages.status enum accepts. */
    private const STATUSES = ['new', 'answered', 'archived'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $messages = new ContactMessage();

        $status = mb_substr(trim((string) $request->queryValue('status', '')), 0, 20);
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $page = max(1, (int) $request->queryValue('page', 1));

        $paging = $messages->inbox($status, $page, self::PER_PAGE);

        return $this->view('admin.messages', [
            'paging' => $paging,
            'status' => $status,
            'statuses' => self::STATUSES,
            'unread' => $messages->unreadCount(),
        ]);
    }

    public function answer(Request $request, string $id): Response
    {
        $messageId = (int) $id;
        $messages = new ContactMessage();

        if ($messages->find($messageId) === null) {
            throw new HttpException(404, 'That message does not exist.');
        }

        $data = $this->validate($request, [
            // reply_note is a varchar(500) column; the cap keeps a paste from
            // throwing at the database.
            'reply_note' => 'required|max:500',
            'status' => 'in:' . implode(',', self::STATUSES),
        ], [
            'reply_note' => 'Reply note',
            'status' => 'Status',
        ]);

        $staffId = (int) (Auth::id() ?? 0);

        $messages->markAnswered($messageId, $staffId, (string) $data['reply_note']);

        // Only the row id is logged - never the customer's message or number.
        AuditLog::record('message.answered', 'contact_message', $messageId, sprintf(
            'Marked enquiry #%d answered and saved a reply note.',
            $messageId
        ));

        $filter = $this->statusFilter($request);

        return $this->redirect(
            route('admin.messages', [], $filter === '' ? [] : ['status' => $filter]),
            'success',
            'Marked as answered. The reply note is stored with the enquiry.'
        );
    }

    /**
     * The inbox filter to return to. The Referer carries it when the form is
     * posted from a filtered list; a hand-crafted or absent Referer falls back to
     * the status field the reply form also submits, then to the unfiltered inbox.
     */
    private function statusFilter(Request $request): string
    {
        $refererStatus = '';
        $referer = $request->referer();

        if ($referer !== '') {
            $query = (string) parse_url($referer, PHP_URL_QUERY);

            if ($query !== '') {
                parse_str($query, $parsed);
                $candidate = trim((string) ($parsed['status'] ?? ''));

                if (in_array($candidate, self::STATUSES, true)) {
                    $refererStatus = $candidate;
                }
            }
        }

        if ($refererStatus !== '') {
            return $refererStatus;
        }

        $posted = trim((string) $request->input('status', ''));

        return in_array($posted, self::STATUSES, true) ? $posted : '';
    }
}
