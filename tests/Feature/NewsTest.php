<?php

use App\Models\User;
use App\Notifications\Messages\NewsAdded;
use App\Services\NewsService;
use Illuminate\Support\Facades\Notification;

test('news notifications', function () {
    Notification::fake();

    $users_opt_in = User::factory()->count(5)->create(['opt_in' => true]);
    $users_opt_out = User::factory()->count(5)->create(['opt_in' => false]);

    app(NewsService::class)->addNews([
        'user_id'            => $users_opt_out[0]->id,
        'subject'            => 'News Item',
        'body'               => 'News!',
        'send_notifications' => true,
    ]);

    Notification::assertSentTo($users_opt_in, NewsAdded::class);
    Notification::assertNotSentTo($users_opt_out, NewsAdded::class);
});
