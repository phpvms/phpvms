<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;
use App\Models\News;

class NewsAdded extends Event
{
    public function __construct(public News $news) {}
}
