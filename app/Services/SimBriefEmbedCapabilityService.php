<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class SimBriefEmbedCapabilityService
{
    public const PROVIDER_URL = 'https://www.simbrief.com/ofp/ofp.loader.api.php';

    private const CACHE_KEY = 'simbrief:generation-embed-capability:v2';

    public function allowed(): bool
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHours(6),
            fn (): bool => $this->probe(),
        );
    }

    private function probe(): bool
    {
        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->maxRedirects(5)
                ->get(self::PROVIDER_URL);

            return $this->allowsFraming($response);
        } catch (Throwable) {
            return true;
        }
    }

    private function allowsFraming(Response $response): bool
    {
        $frameOptions = strtolower(trim($response->header('X-Frame-Options')));
        if ($frameOptions !== '' && $frameOptions !== 'allowall') {
            return false;
        }

        $contentSecurityPolicy = strtolower($response->header('Content-Security-Policy'));
        if (!preg_match('/(?:^|;)\s*frame-ancestors\s+([^;]+)/', $contentSecurityPolicy, $matches)) {
            return true;
        }

        return in_array('*', preg_split('/\s+/', trim($matches[1])) ?: [], true);
    }
}
