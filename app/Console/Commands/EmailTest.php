<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Events\NewsAdded;
use App\Models\News;
use App\Notifications\NotificationEventsHandler;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class EmailTest extends Command
{
    protected $signature = 'phpvms:email-test';

    protected $description = 'Send a test notification to admins';

    /**
     * Run dev related commands
     *
     * @throws FileException
     */
    public function handle()
    {
        /** @var NotificationEventsHandler $eventHandler */
        $eventHandler = app(NotificationEventsHandler::class);

        $news = new News();
        $news->user_id = 1;
        $news->subject = 'Test News';
        $news->body = 'Test Body';
        $news->save();

        $newsEvent = new NewsAdded($news);
        $eventHandler->onNewsAdded($newsEvent);
    }
}
