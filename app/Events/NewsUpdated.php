<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\News;

class NewsUpdated extends Event
{
    public function __construct(
        public News $news
    ) {
    }
}
