<?php

declare(strict_types=1);

namespace App\Features\Dashboard\Livewire;

use App\Models\News;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The latest news widget: the newest items, each with a short plain-text excerpt.
 *
 * Reads the same source as the `LatestNews` Laravel widget (`News::with('user')->latest()`).
 * The app has no public news page, so items are not linked.
 */
class LatestNews extends Component
{
    public const int LIMIT = 4;

    /**
     * @return Collection<int, News>
     */
    public static function items(int $limit = self::LIMIT): Collection
    {
        return News::with('user')->latest()->limit($limit)->get();
    }

    /**
     * The body as plain text, cut to a short excerpt.
     */
    public static function excerpt(string $body, int $length = 140): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $body)))), $length);
    }

    public function render(): View
    {
        return view('livewire.dashboard.latest-news', ['news' => self::items()]);
    }
}
