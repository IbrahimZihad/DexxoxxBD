<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Decides which locale a request should render in. Precedence: explicit
 * ?lang= switch (also persisted), then the user's stored preference, then the
 * session, then the configured default.
 */
final class LocaleResolver
{
    public static function resolve(Request $request): string
    {
        $available = (array) Config::get('app.locales', ['en', 'bn']);
        $default = (string) Config::get('app.locale', 'en');

        $requested = $request->queryValue('lang');
        if (is_string($requested) && in_array($requested, $available, true)) {
            Session::set('locale', $requested);
            $user = Auth::instance()->user();
            if ($user !== null) {
                (new \App\Models\User())->updateLocale((int) $user['id'], $requested);
            }
            return $requested;
        }

        $user = Auth::instance()->user();
        if ($user !== null && !empty($user['locale']) && in_array($user['locale'], $available, true)) {
            return (string) $user['locale'];
        }

        $fromSession = Session::get('locale');
        if (is_string($fromSession) && in_array($fromSession, $available, true)) {
            return $fromSession;
        }

        return in_array($default, $available, true) ? $default : 'en';
    }
}
