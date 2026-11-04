<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Models\User;

/**
 * Language switcher. The resolver also accepts ?lang= so a shared link can open
 * in either locale; this route is the one the topbar uses and it persists the
 * choice for the session and, when signed in, on the account.
 */
final class LocaleController extends Controller
{
    public function set(Request $request, string $code): Response
    {
        unset($request);

        $available = (array) Config::get('app.locales', ['en', 'bn']);
        $code = strtolower(trim($code));

        if (!in_array($code, $available, true)) {
            throw new HttpException(404, 'That language is not available.');
        }

        Session::set('locale', $code);

        $user = Auth::instance()->user();
        if ($user !== null) {
            (new User())->updateLocale((int) $user['id'], $code);
        }

        return $this->back();
    }
}
