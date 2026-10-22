<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\Client\ConnectionException;
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
                    $response = Http::connectTimeout(2)->timeout(5)->get($url);
                } catch (ConnectionException) {
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

    private static function fail(string $body): void
    {
        Notification::make()
            ->danger()
            ->title(__('map.test_style_failed'))
            ->body($body)
            ->send();
    }
}
