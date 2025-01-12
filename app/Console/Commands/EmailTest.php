<?php

namespace App\Console\Commands;

use App;
use App\Contracts\Command;

class EmailTest extends Command
{
    protected $signature = 'phpvms:email-test';

    protected $description = 'Send a test notification to admins';

    /**
     * Run dev related commands.
     *
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function handle()
    {
        /** @var App\Notifications\NotificationEventsHandler $eventHandler */
        $eventHandler = app(App\Notifications\NotificationEventsHandler::class);

        $news = new App\Models\News();
        $news->user_id = 1;
        $news->subject = 'Test News';
        $news->body = 'Test Body';
        $news->save();

        $newsEvent = new App\Events\NewsAdded($news);
        $eventHandler->onNewsAdded($newsEvent);
    }
}
