<?php

namespace App\Listeners;

use App\Events\CronWeekly;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TLDUpdater
{
    public function handle(CronWeekly $event): void
    {
        $response = Http::get(config('phpvms.tld_list_url'));

        if ($response->successful()) {
            Log::info('Updating TLD list');

            $filePath = resource_path('tld/public_suffix_list.dat');

            file_put_contents($filePath, $response->body());
        } else {
            Log::error('Unable to update TLD list, Error: '.$response->body());
        }
    }
}
