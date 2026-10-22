<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailFailureReporter
{
    public function report(Throwable $exception, ?string $recipient = null): void
    {
        $message = $recipient === null
            ? 'Email delivery failed: '.$exception->getMessage()
            : 'Email to '.$recipient.' failed: '.$exception->getMessage();

        Log::emergency($message, ['exception' => $exception]);

        $admins = User::whereHas('roles', function ($query): void {
            $query->where('name', Role::superAdminName());
        })->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Email delivery failed')
            ->body($message)
            ->danger()
            ->sendToDatabase($admins);
    }
}
