<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Per-fare auto-price inputs. base_price/per_nm default to 0 and
        // multiplier to 1 so existing fares are inert until auto-pricing is
        // configured and enabled.
        Schema::table('fares', function (Blueprint $table): void {
            $table->decimal('base_price', 8, 2)->unsigned()->nullable()->default(0)->after('price');
            $table->decimal('per_nm', 10, 4)->unsigned()->nullable()->default(0)->after('base_price');
            $table->decimal('multiplier', 8, 4)->unsigned()->nullable()->default(1)->after('per_nm');
        });

        // Per-subfleet overrides of the auto-price inputs. Nullable with no
        // default: NULL means "inherit the fare's own value". Absolute values
        // only (unlike price/cost/capacity these are not percentage strings).
        Schema::table('subfleet_fare', function (Blueprint $table): void {
            $table->decimal('base_price', 8, 2)->unsigned()->nullable()->after('capacity');
            $table->decimal('per_nm', 10, 4)->unsigned()->nullable()->after('base_price');
            $table->decimal('multiplier', 8, 4)->unsigned()->nullable()->after('per_nm');
        });

        Schema::table('airlines', function (Blueprint $table): void {
            $table->boolean('low_cost')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('fares', function (Blueprint $table): void {
            $table->dropColumn(['base_price', 'per_nm', 'multiplier']);
        });

        Schema::table('subfleet_fare', function (Blueprint $table): void {
            $table->dropColumn(['base_price', 'per_nm', 'multiplier']);
        });

        Schema::table('airlines', function (Blueprint $table): void {
            $table->dropColumn('low_cost');
        });
    }
};
