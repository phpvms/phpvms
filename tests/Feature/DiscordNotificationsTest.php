<?php

use App\Enums\PirepStatus;
use App\Models\Award;
use App\Models\News;
use App\Models\Pirep;
use App\Models\User;
use App\Models\UserAward;
use App\Notifications\Messages\Broadcast\AwardAwarded;
use App\Notifications\Messages\Broadcast\NewsAdded;
use App\Notifications\Messages\Broadcast\PirepFiled;
use App\Notifications\Messages\Broadcast\PirepStatusChanged;
use App\Notifications\Messages\Broadcast\UserRegistered;
use App\Notifications\Notifiables\PublicBroadcast;
use App\Notifications\Notifiables\StaffBroadcast;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;

const WEBHOOK = 'https://discord.com/api/webhooks/123/abc';
const CHANNEL_ID = '987654321098765432';

beforeEach(function (): void {
    loadYamlIntoDb('fleet');
});

// --- Message content ---------------------------------------------------------

test('award announcement credits the recipient', function (): void {
    $user = User::factory()->create(['discord_id' => '555']);
    $award = Award::factory()->create(['name' => 'Century Club']);
    $userAward = UserAward::create(['user_id' => $user->id, 'award_id' => $award->id]);

    $embed = discordEmbed(new AwardAwarded($userAward)->toDiscord(app(PublicBroadcast::class)));

    // Regression: this used to read "Awarded by", crediting the recipient as
    // the giver.
    expect($embed['title'])->toContain('Century Club')
        ->and($embed['description'])->toContain('Awarded to')
        ->and($embed['description'])->toContain('<@555>')
        ->and($embed['description'])->not->toContain('Awarded by');
});

test('announcement omits the mention when the member has no discord linked', function (): void {
    $user = User::factory()->create(['discord_id' => '']);
    $award = Award::factory()->create();
    $userAward = UserAward::create(['user_id' => $user->id, 'award_id' => $award->id]);

    $embed = discordEmbed(new AwardAwarded($userAward)->toDiscord(app(PublicBroadcast::class)));

    // No mention to make, but the member is still identified by ident and name.
    expect($embed['description'] ?? null)->toBeNull()
        ->and($embed['author']['name'])->toContain($user->ident)
        ->and($embed['author']['name'])->toContain($user->name_private);
});

// --- Discord's limits --------------------------------------------------------

test('a news body over the embed limit is truncated and links onward', function (): void {
    $news = News::factory()->create(['body' => str_repeat('a very long news article. ', 500)]);

    $message = new NewsAdded($news)->toDiscord(app(PublicBroadcast::class));
    $payload = discordPayload($message);
    $embed = discordEmbed($message);

    expect(mb_strlen((string) $embed['description']))->toBeLessThanOrEqual(DiscordEmbed::MAX_DESCRIPTION)
        ->and($embed['description'])->toEndWith('…')
        ->and($payload['components'][0]['components'][0]['url'])->toBe(route('frontend.dashboard.index'));

    // The package validates limits on send; this must not throw.
    $message->validate();
});

test('a short news body is not truncated', function (): void {
    $news = News::factory()->create(['body' => 'Short and sweet.']);

    $embed = discordEmbed(new NewsAdded($news)->toDiscord(app(PublicBroadcast::class)));

    expect($embed['description'])->toBe('Short and sweet.')
        ->and($embed['description'])->not->toEndWith('…');
});

// --- Routing -----------------------------------------------------------------

test('a webhook route is delivered over the webhook transport', function (): void {
    Http::fake();
    updateSetting('notifications.discord_public_route', WEBHOOK);

    Notification::send([app(PublicBroadcast::class)], new PirepFiled(Pirep::factory()->create()));

    Http::assertSent(fn ($request): bool => $request->url() === WEBHOOK);
});

test('a channel id route is delivered over the bot transport', function (): void {
    Http::fake();
    config()->set('discord-notifier.bot.token', 'test-token');
    updateSetting('notifications.discord_public_route', CHANNEL_ID);

    Notification::send([app(PublicBroadcast::class)], new PirepFiled(Pirep::factory()->create()));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/v10/channels/'.CHANNEL_ID.'/messages'
        && $request->hasHeader('Authorization', 'Bot test-token'));
});

test('the staff route is independent of the public route', function (): void {
    updateSetting('notifications.discord_public_route', WEBHOOK);
    updateSetting('notifications.discord_private_route', CHANNEL_ID);

    expect(app(PublicBroadcast::class)->routeNotificationForDiscord())->toBe(WEBHOOK)
        ->and(app(StaffBroadcast::class)->routeNotificationForDiscord())->toBe(CHANNEL_ID);
});

// --- Unconfigured installs ---------------------------------------------------

test('nothing is sent and nothing throws when discord is not configured', function (): void {
    Http::fake();
    updateSetting('notifications.discord_public_route', '');

    // The majority of self-hosted installs never configure Discord. This must
    // not throw, and must not fail a queued job.
    Notification::send([app(PublicBroadcast::class)], new PirepFiled(Pirep::factory()->create()));

    Http::assertNothingSent();
    expect(app(PublicBroadcast::class)->routeNotificationForDiscord())->toBeNull();
});

test('the staff announcement is skipped when only the public route is set', function (): void {
    Http::fake();
    updateSetting('notifications.discord_public_route', WEBHOOK);
    updateSetting('notifications.discord_private_route', '');

    Notification::send([app(StaffBroadcast::class)], new UserRegistered(User::factory()->create()));

    Http::assertNothingSent();
});

// --- Locale ------------------------------------------------------------------

test('an announcement renders in the app locale, not the triggering visitor locale', function (): void {
    Lang::addLines(['notifications.discord.pirep_filed' => 'VOL :ident deposé'], 'fr');
    config()->set('phpvms.default_locale', 'en');

    Http::fake();
    updateSetting('notifications.discord_public_route', WEBHOOK);

    // A French-speaking pilot files a flight: SetActiveLanguage has already put
    // the request into 'fr'. The channel is read by everyone, so the
    // announcement must still be the VA's language.
    App::setLocale('fr');

    Notification::send([app(PublicBroadcast::class)], new PirepFiled(Pirep::factory()->create()));

    Http::assertSent(function ($request): bool {
        $title = $request->data()['embeds'][0]['title'];

        return str_contains($title, 'Filed') && !str_contains($title, 'VOL');
    });
});

test('the broadcast audiences prefer the site locale', function (): void {
    config()->set('phpvms.default_locale', 'de');

    // App::setLocale() writes config('app.locale'), so a visitor's language
    // must not be able to masquerade as the site's.
    App::setLocale('fr');

    expect(app(PublicBroadcast::class)->preferredLocale())->toBe('de')
        ->and(app(StaffBroadcast::class)->preferredLocale())->toBe('de');
});

// --- One announcement per event ----------------------------------------------

test('a key status change announces once', function (): void {
    Notification::fake();
    updateSetting('notifications.discord_pirep_status', true);

    $pirep = Pirep::factory()->create(['status' => PirepStatus::INITIATED]);
    $pirep->status = PirepStatus::BOARDING;
    $pirep->save();

    // Proves the status-change announcement fires at all, which is what makes
    // the diverted test below meaningful rather than vacuous.
    Notification::assertSentTo([app(PublicBroadcast::class)], PirepStatusChanged::class);
});

test('a diverted status does not also announce a status change', function (): void {
    Notification::fake();
    updateSetting('notifications.discord_pirep_status', true);

    $pirep = Pirep::factory()->create(['status' => PirepStatus::INITIATED]);
    $pirep->status = PirepStatus::DIVERTED;
    $pirep->save();

    // PirepService::handleDiversion() announces a diversion via PirepDiverted,
    // which carries the diversion airport and reason. Listing DIVERTED in
    // $message_types too announced every diversion twice.
    Notification::assertNotSentTo([app(PublicBroadcast::class)], PirepStatusChanged::class);
});

// --- Per-user DM routing -----------------------------------------------------

test('a user routes discord notifications to their stored dm channel', function (): void {
    $user = User::factory()->create(['discord_private_channel_id' => CHANNEL_ID]);

    expect($user->routeNotificationForDiscord())->toBe(CHANNEL_ID);
});

test('a user who never linked discord has no route', function (): void {
    $user = User::factory()->create(['discord_private_channel_id' => '']);

    expect($user->routeNotificationForDiscord())->toBeNull();
});
