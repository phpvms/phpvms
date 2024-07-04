<?php

namespace Tests;

use App\Models\User;
use App\Notifications\Messages\NewsAdded;
use App\Services\NewsService;
use Illuminate\Support\Facades\Notification;

final class NewsTest extends TestCase
{
    /** @var NewsService */
    private NewsService $newsSvc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsSvc = app(NewsService::class);
    }

    /**
     * Tests that news is added but mainly that notifications are sent out to all users
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function testNewsNotifications(): void
    {
        /** @var User[] $users */
        $users_opt_in = User::factory()->count(5)->create(['opt_in' => true]);

        /** @var User[] $users */
        $users_opt_out = User::factory()->count(5)->create(['opt_in' => false]);

        $this->newsSvc->addNews([
            'user_id'            => $users_opt_out[0]->id,
            'subject'            => 'News Item',
            'body'               => 'News!',
            'send_notifications' => true,
        ]);

        Notification::assertSentTo($users_opt_in, NewsAdded::class);
    }
}
