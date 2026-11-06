<?php

namespace App\Support;

/**
 * Builds links into the web app (Expo export served under "/").
 * Android opens the same https links in the app via App Links.
 */
final class FrontendUrl
{
    /**
     * @param  array<string, string>  $query
     */
    public static function to(string $path, array $query = []): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/'.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
