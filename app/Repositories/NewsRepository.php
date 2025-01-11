<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\News;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

/**
 * Class NewsRepository
 */
class NewsRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    public function model()
    {
        return News::class;
    }

    /**
     * Latest news items
     *
     * @param int $count
     *
     * @return mixed
     */
    public function getLatest($count = 5)
    {
        return $this->orderBy('created_at', 'desc')
            ->with(['user'])
            ->paginate($count);
    }
}
