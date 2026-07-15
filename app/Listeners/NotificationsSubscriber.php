<?php

namespace App\Listeners;

use App\Enums\PirepStatus;
use App\Enums\UserState;
use App\Events\AwardAwarded;
use App\Events\NewsAdded;
use App\Events\NewsUpdated;
use App\Events\PirepAccepted;
use App\Events\PirepFiled;
use App\Events\PirepPrefiled;
use App\Events\PirepRejected;
use App\Events\PirepStatusChange;
use App\Events\UserStateChanged;
use App\Events\UserStatsChanged;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Messages;
use App\Notifications\Messages\AdminUserRegistered;
use App\Notifications\Messages\Broadcast\PirepStatusChanged;
use App\Notifications\Messages\Broadcast\UserRankChanged;
use App\Notifications\Messages\UserPending;
use App\Notifications\Messages\UserRegistered;
use App\Notifications\Messages\UserRejected;
use App\Notifications\Notifiables\PublicBroadcast;
use App\Notifications\Notifiables\StaffBroadcast;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Listen for different events and map them to different notifications
 */
class NotificationsSubscriber
{
    /**
     * Send a notification to all of the admins
     */
    protected function notifyAdmins(\App\Contracts\Notification $notification): void
    {
        $admin_users = User::whereHas('roles', function ($query): void {
            $query->where('name', Role::superAdminName());
        })->get();

        foreach ($admin_users as $user) {
            if (empty($user->email)) {
                continue;
            }

            try {
                // $this->notifyUser($user, $notification);
                Notification::send([$user], $notification);
            } catch (Exception $e) {
                Log::emergency('Error emailing admin ('.$user->email.'). Error='.$e->getMessage());
            }
        }
    }

    protected function notifyUser(User $user, \App\Contracts\Notification $notification): void
    {
        if ($user->state === UserState::DELETED) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Exception $exception) {
            Log::emergency('Error emailing user, '.$user->ident.'='.$user->email.', error='.$exception->getMessage());
        }
    }

    /**
     * Send a notification to all users. Also can specify if a particular notification
     * requires an opt-in
     */
    protected function notifyAllUsers(\App\Contracts\Notification $notification): void
    {
        $where = [];
        if ($notification->requires_opt_in === true) {  // If the opt-in is required
            $where['opt_in'] = true;
        }

        /** @var Collection $users */
        $users = User::where($where)->whereIn('state', [UserState::ACTIVE, UserState::ON_LEAVE])->get();
        if ($users->count() === 0) {
            return;
        }

        Log::info('Sending notification to '.$users->count().' users');

        foreach ($users as $user) {
            $this->notifyUser($user, $notification);
        }
    }

    public function handleVerified(Verified $event): void
    {
        /** @var User $user */
        $user = $event->user;
        // Return if the user has any flights (email change / admin requests new verification)
        if ($user->flights > 0) {
            return;
        }

        Log::info('NotificationEvents::onUserRegister: '
            .$user->ident.' is '
            .$user->state->getLabel().', sending active email');

        /*
         * Send the user a confirmation email
         */
        if ($user->state === UserState::ACTIVE) {
            $this->notifyUser($user, new UserRegistered($user));
        } elseif ($user->state === UserState::PENDING) {
            $this->notifyUser($user, new UserPending($user));
        }

        /*
         * Send all of the admins a notification that a new user registered
         */
        $this->notifyAdmins(new AdminUserRegistered($user));

        /*
         * Broadcast notifications
         */
        Notification::send([app(StaffBroadcast::class)], new Messages\Broadcast\UserRegistered($user));
    }

    /**
     * When a user's state changes, send an email out
     */
    public function handleUserStateChanged(UserStateChanged $event): void
    {
        Log::info('NotificationEvents::onUserStateChange: New user state='.$event->user->state->value);

        if ($event->old_state === UserState::PENDING) {
            if ($event->user->state === UserState::ACTIVE) {
                $this->notifyUser($event->user, new UserRegistered($event->user));
            } elseif ($event->user->state === UserState::REJECTED) {
                $this->notifyUser($event->user, new UserRejected($event->user));
            }
        } elseif ($event->old_state === UserState::ACTIVE) {
            Log::info('User state change from active to ??');
        }
    }

    /**
     * Prefile notification. Intentionally announces nothing to Discord — a
     * prefiled flight is not news, and the flight is announced when it is filed.
     */
    public function handlePirepPrefiled(PirepPrefiled $event): void
    {
        Log::info('NotificationEvents::onPirepPrefile: '.$event->pirep->id.' prefiled');
    }

    /**
     * Status Change notification.
     * Reduced the messages (Boarding, Pushback, TakeOff, Landing and non-normals only)
     * If needed array can be tied to a setting at admin side for further customization
     *
     * PirepStatus::DIVERTED is deliberately absent from the list: a diversion is
     * announced by PirepService::handleDiversion() through Broadcast\PirepDiverted,
     * which carries the diversion airport and reason. Listing it here as well
     * announced every diversion twice.
     */
    public function handlePirepStatusChange(PirepStatusChange $event): void
    {
        Log::info('NotificationEvents::onPirepStatusChange: '.$event->pirep->id.' status changed');

        $message_types = [
            PirepStatus::BOARDING,
            PirepStatus::PUSHBACK_TOW,
            PirepStatus::GRND_RTRN,
            PirepStatus::TAKEOFF,
            PirepStatus::LANDED,
            PirepStatus::CANCELLED,
            PirepStatus::PAUSED,
            PirepStatus::EMERG_DESCENT,
        ];

        if (setting('notifications.discord_pirep_status', true) && in_array($event->pirep->status, $message_types,
            true)) {
            Notification::send([app(PublicBroadcast::class)], new PirepStatusChanged($event->pirep));
        }
    }

    /**
     * Notify the admins that a new PIREP has been filed
     */
    public function handlePirepFiled(PirepFiled $event): void
    {
        Log::info('NotificationEvents::onPirepFile: '.$event->pirep->id.' filed');
        if (setting('notifications.mail_pirep_admin', true)) {
            $this->notifyAdmins(new Messages\PirepFiled($event->pirep->withoutRelations()));
        }

        /*
         * Broadcast notifications
         */
        if (setting('notifications.discord_pirep_filed', true)) {
            Notification::send([app(PublicBroadcast::class)], new Messages\Broadcast\PirepFiled($event->pirep));
        }
    }

    /**
     * Notify the user that their PIREP has been accepted
     */
    public function handlePirepAccepted(PirepAccepted $event): void
    {
        if (setting('notifications.mail_pirep_user_ack', true)) {
            Log::info('NotificationEvents::onPirepAccepted: '.$event->pirep->id.' accepted');
            $this->notifyUser($event->pirep->user, new Messages\PirepAccepted($event->pirep->withoutRelations()));
        }
    }

    /**
     * Notify the user that their PIREP has been rejected
     */
    public function handlePirepRejected(PirepRejected $event): void
    {
        if (setting('notifications.mail_pirep_user_rej', true)) {
            Log::info('NotificationEvents::onPirepRejected: '.$event->pirep->id.' rejected');
            $this->notifyUser($event->pirep->user, new Messages\PirepRejected($event->pirep->withoutRelations()));
        }
    }

    /**
     * Notify all users of a news event, but only the users which have opted in
     */
    public function handleNewsAdded(NewsAdded $event): void
    {
        Log::info('NotificationEvents::onNewsAdded');
        if (setting('notifications.mail_news', true)) {
            $this->notifyAllUsers(new Messages\NewsAdded($event->news));
        }

        /*
         * Broadcast notifications
         */
        Notification::send([app(PublicBroadcast::class)], new Messages\Broadcast\NewsAdded($event->news));
    }

    /**
     * Notify all users of a news event, but only the users which have opted in
     */
    public function handleNewsUpdated(NewsUpdated $event): void
    {
        Log::info('NotificationEvents::onNewsAdded');
        if (setting('notifications.mail_news', true)) {
            $this->notifyAllUsers(new Messages\NewsAdded($event->news));
        }

        /*
         * Broadcast notifications
         */
        Notification::send([app(PublicBroadcast::class)], new Messages\Broadcast\NewsAdded($event->news));
    }

    /**
     * Notify all users that user has awarded a new award
     */
    public function handleAwardAwarded(AwardAwarded $event): void
    {
        /*
         * Broadcast notifications
         */
        if (setting('notifications.discord_award_awarded', true)) {
            Notification::send([app(PublicBroadcast::class)], new Messages\Broadcast\AwardAwarded($event->userAward));
        }
    }

    /**
     * Notify all users of a user rank change
     */
    public function handleUserStatsChanged(UserStatsChanged $event): void
    {
        /*
         * Broadcast notifications
         */
        if (setting('notifications.discord_user_rank_changed', true) && $event->stat_name === 'rank') {
            Notification::send([app(PublicBroadcast::class)], new UserRankChanged($event->user));
        }
    }
}
