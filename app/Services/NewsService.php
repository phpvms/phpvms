<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Events\NewsAdded;
use App\Events\NewsUpdated;
use App\Models\News;

class NewsService extends Service
{
    /**
     * Add a news item
     */
    public function addNews(array $attrs): News
    {
        $news = News::create($attrs);

        if (array_key_exists('send_notifications', $attrs) && get_truth_state($attrs['send_notifications'])) {
            event(new NewsAdded($news));
        }

        return $news;
    }

    /**
     * Update a news
     */
    public function updateNews(array $attrs): ?News
    {
        $news = News::find($attrs['id']);

        if (!$news) {
            return null;
        }

        $news->fill($attrs)->save();

        if (array_key_exists('send_notifications', $attrs) && get_truth_state($attrs['send_notifications'])) {
            event(new NewsUpdated($news));
        }

        return $news;
    }

    /**
     * Delete a news item. Idempotent — no-op if the id does not exist.
     *
     * @param int $id ID of the news row to delete
     */
    public function deleteNews(int $id): void
    {
        News::whereKey($id)->delete();
    }
}
