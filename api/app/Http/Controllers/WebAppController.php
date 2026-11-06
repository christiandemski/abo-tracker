<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the static Expo web export from public/app/.
 * In production Apache serves existing files directly (see public/.htaccess);
 * this controller covers the SPA fallback and local `php artisan serve`.
 */
class WebAppController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->is('api', 'api/*')) {
            abort(404);
        }

        $root = public_path('app');

        if ($file = $this->resolveStaticFile($root, $request->path())) {
            return response()->file($file);
        }

        $index = $root.DIRECTORY_SEPARATOR.'index.html';

        if (! File::exists($index)) {
            return response('Die Web-App ist noch nicht gebaut (npx expo export --platform web).', 503);
        }

        // index.html must never be cached, otherwise clients keep stale bundle names.
        return response()->file($index, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function assetLinks(): JsonResponse
    {
        $fingerprints = config('services.android.sha256_cert_fingerprints');

        if ($fingerprints === []) {
            return response()->json([]);
        }

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('services.android.package_name'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }

    /**
     * Returns the absolute path of an existing file inside $root, or null (no path traversal).
     */
    private function resolveStaticFile(string $root, string $path): ?string
    {
        if ($path === '/' || $path === '') {
            return null;
        }

        $realRoot = realpath($root);
        $candidate = realpath($root.DIRECTORY_SEPARATOR.$path);

        if ($realRoot === false || $candidate === false || ! is_file($candidate)) {
            return null;
        }

        return str_starts_with($candidate, $realRoot.DIRECTORY_SEPARATOR) ? $candidate : null;
    }
}
