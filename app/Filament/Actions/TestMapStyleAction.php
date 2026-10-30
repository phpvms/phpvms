<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fetches a custom map style URL and reports whether it looks like a valid
 * maplibre style document, without saving anything — an operator can try a
 * URL before committing it. Attach via ->hintAction() on the field holding
 * the URL (spec.md "Style loading is fail-soft").
 *
 * The field path is a PARAMETER, not hardcoded: this action is attached to
 * the light and dark custom-URL fields of the "Base Maps" drawer, which are
 * separate fields. It previously hardcoded `map.custom_style_url`, the
 * Settings page path it was written for — reading a path that does not exist
 * silently yields "no URL entered" and reports failure, which looks identical
 * to a genuinely unreachable style.
 */
class TestMapStyleAction
{
    /**
     * Keys a maplibre StyleSpecification document must carry. Checking for
     * these (rather than a full schema validator) is enough to catch both
     * "not JSON" and "JSON that isn't a style" responses.
     *
     * @var list<string>
     */
    private const array REQUIRED_STYLE_KEYS = ['version', 'sources', 'layers'];

    private const int MAX_REDIRECTS = 3;

    public static function make(string $urlField = 'map.custom_style_url'): Action
    {
        return Action::make('testMapStyle')
            ->label(__('map.test_style'))
            ->icon(Phosphor::TestTubeLight)
            ->action(function (Get $get) use ($urlField): void {
                $url = trim((string) $get($urlField));

                if ($url === '') {
                    self::fail(__('map.test_style_no_url'));

                    return;
                }

                try {
                    $response = self::fetch($url);
                } catch (ConnectionException) {
                    $response = null;
                }

                if (!$response instanceof Response) {
                    self::fail(__('map.test_style_unreachable'));

                    return;
                }

                if (!$response->successful()) {
                    self::fail(__('map.test_style_bad_status', ['status' => (string) $response->status()]));

                    return;
                }

                $style = $response->json();

                if (!is_array($style) || array_diff(self::REQUIRED_STYLE_KEYS, array_keys($style)) !== []) {
                    self::fail(__('map.test_style_invalid'));

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('map.test_style_success'))
                    ->send();
            });
    }

    /**
     * Fetch the style server-side, following up to three redirects by hand.
     * Every hop must resolve only to public addresses, and the connection is
     * pinned to the address that was checked (CURLOPT_RESOLVE) so a DNS answer
     * that changes between the check and the connect cannot reach a private
     * service. Returns null when a hop is not public or redirects run out.
     *
     * @throws ConnectionException
     */
    public static function fetch(string $url): ?Response
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $addresses = self::publicAddresses($url);
            if ($addresses === null) {
                return null;
            }

            $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
            $options = ['allow_redirects' => false];
            if (filter_var($host, FILTER_VALIDATE_IP) === false) {
                $port = parse_url($url, PHP_URL_PORT) ?? (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
                $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
                $options['curl'] = [CURLOPT_RESOLVE => [$host.':'.$port.':'.$address]];
            }

            $response = Http::connectTimeout(2)->timeout(5)->withOptions($options)->get($url);
            if (!$response->redirect()) {
                return $response;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));
        }

        return null;
    }

    /**
     * True when the URL is http(s) and its host resolves only to globally
     * routable addresses - never loopback, private, link-local (cloud
     * metadata), shared/CGNAT or other reserved ranges.
     */
    public static function isPublicHttpUrl(string $url): bool
    {
        return self::publicAddresses($url) !== null;
    }

    /**
     * @return non-empty-list<string>|null the host's addresses, or null if any is not public
     */
    private static function publicAddresses(string $url): ?array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $addresses = gethostbynamel($host) ?: [];
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                $addresses[] = $record['ipv6'];
            }
        }

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                return null;
            }
        }

        return $addresses;
    }

    private static function fail(string $body): void
    {
        Notification::make()
            ->danger()
            ->title(__('map.test_style_failed'))
            ->body($body)
            ->send();
    }
}
