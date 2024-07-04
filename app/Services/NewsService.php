<?php

namespace App\Services;

use App\Contracts\Service;
use App\Events\NewsAdded;
use App\Events\NewsUpdated;
use App\Models\News;
use App\Repositories\NewsRepository;
use Prettus\Validator\Exceptions\ValidatorException;

class NewsService extends Service
{
    private NewsRepository $newsRepo;

    public function __construct(NewsRepository $newsRepo)
    {
        $this->newsRepo = $newsRepo;
    }

    /**
     * Add a news item
     *
     * @param array $attrs
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return mixed
     */
    public function addNews(array $attrs)
    {
        $news = $this->newsRepo->create($attrs);

        if (array_key_exists('send_notifications', $attrs) && get_truth_state($attrs['send_notifications'])) {
            event(new NewsAdded($news));
        }

        return $news;
    }

    /**
     * Update a news
     *
     * @param array $attrs
     *
     * @throws ValidatorException
     *
     * @return ?News
     */
    public function updateNews(array $attrs): ?News
    {
        $news = $this->newsRepo->find($attrs['id']);

        if (!$news) {
            return null;
        }

        $news = $this->newsRepo->update($attrs, $attrs['id']);

        if (array_key_exists('send_notifications', $attrs) && get_truth_state($attrs['send_notifications'])) {
            event(new NewsUpdated($news));
        }

        return $news;
    }

    /**
     * Delete something from the news items
     *
     * @param int $id ID of the news row to delete
     */
    public function deleteNews($id)
    {
        $this->newsRepo->delete($id);
    }
}
