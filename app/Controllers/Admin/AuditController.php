<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;

/**
 * The read-only ledger of everything staff have changed.
 *
 * This is the one admin surface with no writes at all: the audit table is
 * append-only (rows are never edited or deleted by the app), so there is a single
 * GET listing here and nothing else. The view says so plainly.
 */
final class AuditController extends Controller
{
    private const PER_PAGE = 40;

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->queryValue('page', 1));

        $logs = new AuditLog();

        return $this->view('admin.audit', [
            'paging' => $logs->paginateLogs($page, self::PER_PAGE),
        ]);
    }
}
