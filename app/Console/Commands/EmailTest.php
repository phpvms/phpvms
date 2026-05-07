<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Events\NewsAdded;
use App\Listeners\NotificationsSubscriber;
use App\Models\News;
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
    public function handle(): void
    {
        /** @var NotificationsSubscriber $eventHandler */
        $eventHandler = app(NotificationsSubscriber::class);

        $news = new News();
        $news->user_id = 1;
        $news->subject = 'Test News';
        $news->body = 'Test Body';
        $news->save();

        $newsEvent = new NewsAdded($news);
        $eventHandler->handleNewsAdded($newsEvent);
    }
}
