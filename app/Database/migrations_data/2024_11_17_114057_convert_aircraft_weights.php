<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class() extends Migration {
    public function up(): void
    {
        if (setting('units.weight') == 'kg') {
            // Get all aircraft data, directly from database which had weights defined
            $aircraft = DB::table('aircraft')->whereNotNull('dow')->orWhereNotNull('zfw')->orWhereNotNull('mtow')->orWhereNotNull('mlw')->orderBy('id')->get();
            Log::debug('Begin weight conversion for '.$aircraft->count().' aircraft records');
            foreach ($aircraft as $ac) {
                Log::debug('Converting and Updating Weights for '.$ac->registration);
                DB::table('aircraft')->where('id', $ac->id)->update([
                    'dow'  => $this->PoundsConversion($ac->dow),
                    'zfw'  => $this->PoundsConversion($ac->zfw),
                    'mtow' => $this->PoundsConversion($ac->mtow),
                    'mlw'  => $this->PoundsConversion($ac->mlw),
                ]);
            }
        }
    }

    public function PoundsConversion($value)
    {
        if ($value > 0) {
            return round($value / 0.45359237, 2);
        }

        return null;
    }
};
