<?php

use App\Contracts\Migration;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;

return new class() extends Migration
{
    public function up()
    {
        Pirep::where('status', PirepStatus::ON_BLOCK)->update(['status' => PirepStatus::ARRIVED]);
    }
};
