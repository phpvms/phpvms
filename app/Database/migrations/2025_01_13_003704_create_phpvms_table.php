<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('migrations_data')) {
            Schema::create('migrations_data', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedBigInteger('pilot_id')->nullable()->unique();
                $table->string('callsign')->nullable();
                $table->string('name')->nullable();
                $table->string('email')->index();
                $table->string('password');
                $table->string('api_key', 40)->nullable()->index();
                $table->unsignedInteger('airline_id');
                $table->unsignedInteger('rank_id')->nullable();
                $table->string('discord_id')->default('');
                $table->string('discord_private_channel_id')->default('');
                $table->string('vatsim_id')->default('');
                $table->string('ivao_id')->default('');
                $table->string('country', 2)->nullable();
                $table->string('home_airport_id', 5)->nullable();
                $table->string('curr_airport_id', 5)->nullable();
                $table->string('last_pirep_id', 36)->nullable();
                $table->unsignedBigInteger('flights')->default(0);
                $table->unsignedBigInteger('flight_time')->nullable()->default(0);
                $table->unsignedBigInteger('transfer_time')->nullable()->default(0);
                $table->string('avatar')->nullable();
                $table->string('timezone', 64)->nullable();
                $table->unsignedTinyInteger('status')->nullable()->default(0);
                $table->unsignedTinyInteger('state')->nullable()->default(0);
                $table->boolean('toc_accepted')->nullable();
                $table->boolean('opt_in')->nullable();
                $table->boolean('active')->nullable();
                $table->string('last_ip', 45)->nullable();
                $table->timestamp('lastlogin_at')->nullable();
                $table->rememberToken();
                $table->mediumText('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->timestamp('email_verified_at')->nullable();

                $table->unique(['email']);
            });
        }

        if (!Schema::hasTable('acars')) {
            Schema::create('acars', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id', 36)->primary();
                $table->string('pirep_id', 36)->index();
                $table->unsignedTinyInteger('type');
                $table->unsignedInteger('nav_type')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->string('name')->nullable();
                $table->char('status', 3)->default('SCH');
                $table->string('log')->nullable();
                $table->decimal('lat', 10, 5)->nullable()->default(0);
                $table->decimal('lon', 11, 5)->nullable()->default(0);
                $table->unsignedInteger('distance')->nullable();
                $table->unsignedInteger('heading')->nullable();
                $table->decimal('altitude_agl')->nullable()->default(0);
                $table->decimal('altitude_msl')->nullable()->default(0);
                $table->double('vs', null, 0)->nullable()->default(0);
                $table->integer('gs')->nullable();
                $table->integer('ias')->nullable();
                $table->unsignedInteger('transponder')->nullable();
                $table->string('autopilot')->nullable();
                $table->decimal('fuel')->nullable();
                $table->decimal('fuel_flow')->nullable();
                $table->string('sim_time')->nullable();
                $table->timestamp('created_at')->nullable()->index();
                $table->timestamp('updated_at')->nullable();
                $table->string('source', 5)->collation('latin1_swedish_ci')->nullable();
            });
        }

        if (!Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('log_name')->nullable()->index();
                $table->text('description');
                $table->string('subject_type')->nullable();
                $table->string('event')->nullable();
                $table->char('subject_id', 36)->nullable();
                $table->string('causer_type')->nullable();
                $table->unsignedBigInteger('causer_id')->nullable();
                $table->json('properties')->collation('utf8mb4_bin')->nullable();
                $table->char('batch_uuid', 36)->nullable();
                $table->timestamps();

                $table->index(['causer_type', 'causer_id'], 'causer');
                $table->index(['subject_type', 'subject_id'], 'subject');
            });
        }

        if (!Schema::hasTable('aircraft')) {
            Schema::create('aircraft', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedInteger('subfleet_id');
                $table->string('icao', 4)->nullable();
                $table->string('iata', 4)->nullable();
                $table->string('airport_id', 5)->nullable()->index();
                $table->string('hub_id', 5)->nullable();
                $table->timestamp('landing_time')->nullable();
                $table->string('name', 50);
                $table->string('registration', 10)->nullable()->unique();
                $table->string('fin', 5)->nullable()->unique();
                $table->string('hex_code', 10)->nullable();
                $table->string('selcal', 5)->nullable();
                $table->decimal('dow', 10)->unsigned()->nullable();
                $table->decimal('mtow', 10)->unsigned()->nullable()->default(0);
                $table->decimal('mlw', 10)->unsigned()->nullable();
                $table->decimal('zfw', 10)->unsigned()->nullable()->default(0);
                $table->string('simbrief_type', 25)->nullable();
                $table->decimal('fuel_onboard')->unsigned()->nullable()->default(0);
                $table->unsignedBigInteger('flight_time')->nullable()->default(0);
                $table->char('status', 1)->default('A');
                $table->unsignedTinyInteger('state')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('airlines')) {
            Schema::create('airlines', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('icao', 5)->index();
                $table->string('iata', 5)->nullable()->index();
                $table->string('name', 50);
                $table->string('callsign')->nullable();
                $table->string('country', 2)->nullable();
                $table->string('logo', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('total_flights')->nullable()->default(0);
                $table->unsignedBigInteger('total_time')->nullable()->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['icao']);
            });
        }

        if (!Schema::hasTable('airports')) {
            Schema::create('airports', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id', 4)->primary();
                $table->string('iata', 5)->nullable()->index();
                $table->string('icao', 5)->index();
                $table->string('name', 100);
                $table->string('location', 100)->nullable();
                $table->string('region', 150)->nullable();
                $table->string('country', 64)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->boolean('hub')->default(false)->index();
                $table->mediumText('notes')->nullable();
                $table->decimal('lat', 10, 5)->nullable()->default(0);
                $table->decimal('lon', 11, 5)->nullable()->default(0);
                $table->integer('elevation')->nullable();
                $table->decimal('ground_handling_cost')->unsigned()->nullable()->default(0);
                $table->decimal('fuel_100ll_cost')->unsigned()->nullable()->default(0);
                $table->decimal('fuel_jeta_cost')->unsigned()->nullable()->default(0);
                $table->decimal('fuel_mogas_cost')->unsigned()->nullable()->default(0);
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('awards')) {
            Schema::create('awards', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('image_url')->nullable();
                $table->string('ref_model')->nullable()->index();
                $table->text('ref_model_params')->nullable();
                $table->boolean('active')->nullable()->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('bids')) {
            Schema::create('bids', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->string('flight_id', 36);
                $table->unsignedInteger('aircraft_id')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'flight_id']);
            });
        }

        if (!Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->integer('id')->primary();
                $table->unsignedInteger('type')->default(0);
                $table->string('name', 250);
                $table->text('description')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('active')->nullable()->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedInteger('airline_id')->nullable();
                $table->string('name');
                $table->unsignedInteger('amount');
                $table->char('type');
                $table->string('flight_type', 50)->nullable();
                $table->boolean('charge_to_user')->nullable()->default(false);
                $table->boolean('multiplier')->nullable()->default(false);
                $table->boolean('active')->nullable()->default(true);
                $table->string('ref_model')->nullable();
                $table->string('ref_model_id', 36)->nullable();
                $table->timestamps();

                $table->index(['ref_model', 'ref_model_id']);
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('fares')) {
            Schema::create('fares', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('code', 50)->unique();
                $table->string('name', 50);
                $table->decimal('price')->unsigned()->nullable()->default(0);
                $table->decimal('cost')->unsigned()->nullable()->default(0);
                $table->unsignedInteger('capacity')->nullable()->default(0);
                $table->unsignedTinyInteger('type')->nullable()->default(0);
                $table->string('notes')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id', 16)->primary();
                $table->string('name');
                $table->string('description')->nullable();
                $table->mediumText('disk')->nullable();
                $table->mediumText('path')->nullable();
                $table->boolean('public')->default(true);
                $table->unsignedInteger('download_count')->default(0);
                $table->string('ref_model', 50)->nullable();
                $table->string('ref_model_id', 36)->nullable();
                $table->timestamps();

                $table->index(['ref_model', 'ref_model_id']);
            });
        }

        if (!Schema::hasTable('flight_fare')) {
            Schema::create('flight_fare', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('flight_id', 36);
                $table->unsignedInteger('fare_id');
                $table->string('price', 10)->nullable();
                $table->string('cost', 10)->nullable();
                $table->string('capacity', 10)->nullable();
                $table->timestamps();

                $table->primary(['flight_id', 'fare_id']);
            });
        }

        if (!Schema::hasTable('flight_field_values')) {
            Schema::create('flight_field_values', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('flight_id', 36)->index();
                $table->string('name', 50);
                $table->string('slug', 50)->nullable();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('flight_fields')) {
            Schema::create('flight_fields', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name', 50);
                $table->string('slug', 50)->nullable();
            });
        }

        if (!Schema::hasTable('flight_subfleet')) {
            Schema::create('flight_subfleet', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->unsignedInteger('subfleet_id');
                $table->string('flight_id', 36);

                $table->index(['flight_id', 'subfleet_id']);
                $table->index(['subfleet_id', 'flight_id']);
            });
        }

        if (!Schema::hasTable('flights')) {
            Schema::create('flights', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id', 36)->primary();
                $table->unsignedInteger('airline_id');
                $table->unsignedInteger('flight_number')->index();
                $table->string('callsign', 4)->nullable();
                $table->string('route_code', 5)->nullable();
                $table->unsignedInteger('route_leg')->nullable();
                $table->string('dpt_airport_id', 4)->index();
                $table->string('arr_airport_id', 4)->index();
                $table->string('alt_airport_id', 4)->nullable();
                $table->string('dpt_time', 10)->nullable();
                $table->string('arr_time', 10)->nullable();
                $table->unsignedInteger('level')->nullable()->default(0);
                $table->decimal('distance')->unsigned()->nullable()->default(0);
                $table->unsignedInteger('flight_time')->nullable();
                $table->char('flight_type', 1)->default('J');
                $table->decimal('load_factor', 5)->nullable();
                $table->decimal('load_factor_variance', 5)->nullable();
                $table->text('route')->nullable();
                $table->decimal('pilot_pay')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('scheduled')->nullable()->default(false);
                $table->unsignedTinyInteger('days')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('has_bid')->default(false);
                $table->boolean('active')->default(true);
                $table->boolean('visible')->default(true);
                $table->unsignedInteger('event_id')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->string('owner_type')->nullable();
                $table->string('owner_id', 36)->nullable();

                $table->index(['owner_type', 'owner_id']);
            });
        }

        if (!Schema::hasTable('invites')) {
            Schema::create('invites', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('email')->nullable();
                $table->string('token');
                $table->integer('usage_count')->default(0);
                $table->integer('usage_limit')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('journal_transactions')) {
            Schema::create('journal_transactions', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->char('id', 36)->unique();
                $table->string('transaction_group')->nullable()->index();
                $table->integer('journal_id')->index();
                $table->unsignedBigInteger('credit')->nullable();
                $table->unsignedBigInteger('debit')->nullable();
                $table->char('currency', 5);
                $table->text('memo')->nullable();
                $table->string('tags')->nullable();
                $table->string('ref_model', 50)->nullable();
                $table->string('ref_model_id', 36)->nullable();
                $table->timestamps();
                $table->date('post_date');

                $table->index(['ref_model', 'ref_model_id']);
                $table->primary(['id']);
            });
        }

        if (!Schema::hasTable('journals')) {
            Schema::create('journals', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedInteger('ledger_id')->nullable();
                $table->unsignedTinyInteger('type')->default(0);
                $table->bigInteger('balance')->default(0);
                $table->string('currency', 5);
                $table->string('morphed_type')->nullable();
                $table->unsignedBigInteger('morphed_id')->nullable();
                $table->timestamps();

                $table->index(['morphed_type', 'morphed_id']);
            });
        }

        if (!Schema::hasTable('kvp')) {
            Schema::create('kvp', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('key')->index();
                $table->string('value');
            });
        }

        if (!Schema::hasTable('ledgers')) {
            Schema::create('ledgers', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name');
                $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name');
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('navdata')) {
            Schema::create('navdata', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id', 5)->index();
                $table->string('name', 24)->index();
                $table->unsignedInteger('type');
                $table->double('lat', 7, 4)->nullable()->default(0);
                $table->double('lon', 7, 4)->nullable()->default(0);
                $table->string('freq', 7)->nullable();

                $table->primary(['id', 'name']);
            });
        }

        if (!Schema::hasTable('news')) {
            Schema::create('news', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('subject');
                $table->text('body');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->char('id', 36)->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->unsignedBigInteger('notifiable_id');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['notifiable_type', 'notifiable_id']);
            });
        }

        if (!Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('name');
                $table->string('slug')->index();
                $table->string('icon')->nullable();
                $table->unsignedSmallInteger('type')->default(0);
                $table->boolean('public');
                $table->boolean('enabled');
                $table->mediumText('body')->nullable();
                $table->string('link')->nullable()->default('');
                $table->timestamps();
                $table->boolean('new_window')->default(false);
            });
        }

        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('email')->index();
                $table->string('token')->index();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedInteger('permission_id');
                $table->unsignedInteger('role_id')->index('permission_role_role_id_foreign');

                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedInteger('permission_id')->index(
                    'permission_user_permission_id_foreign'
                );
                $table->unsignedInteger('user_id');
                $table->string('user_type');

                $table->primary(['user_id', 'permission_id', 'user_type']);
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name')->unique();
                $table->string('display_name')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pirep_comments')) {
            Schema::create('pirep_comments', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('pirep_id', 36);
                $table->unsignedInteger('user_id');
                $table->text('comment');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pirep_fares')) {
            Schema::create('pirep_fares', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('pirep_id', 36)->index();
                $table->unsignedBigInteger('fare_id')->nullable();
                $table->unsignedInteger('count')->nullable()->default(0);
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->decimal('price')->unsigned()->nullable()->default(0);
                $table->decimal('cost')->unsigned()->nullable()->default(0);
                $table->unsignedInteger('capacity')->nullable()->default(0);
                $table->unsignedTinyInteger('type')->nullable()->default(0);
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('pirep_field_values')) {
            Schema::create('pirep_field_values', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->string('pirep_id', 36)->index();
                $table->string('name', 50);
                $table->string('slug', 50)->nullable();
                $table->string('value')->nullable();
                $table->unsignedTinyInteger('source');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pirep_fields')) {
            Schema::create('pirep_fields', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name', 50);
                $table->string('slug', 50)->nullable();
                $table->string('description')->nullable();
                $table->boolean('required')->nullable()->default(false);
                $table->tinyInteger('pirep_source')->nullable()->default(3);
            });
        }

        if (!Schema::hasTable('pireps')) {
            Schema::create('pireps', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id', 36)->primary();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('airline_id');
                $table->unsignedInteger('aircraft_id')->nullable();
                $table->unsignedInteger('event_id')->nullable();
                $table->string('flight_id', 36)->nullable();
                $table->string('flight_number', 10)->nullable()->index();
                $table->string('route_code', 5)->nullable();
                $table->string('route_leg', 5)->nullable();
                $table->char('flight_type', 1)->default('J');
                $table->string('dpt_airport_id', 5)->index();
                $table->string('arr_airport_id', 5)->index();
                $table->string('alt_airport_id', 5)->nullable();
                $table->unsignedInteger('level')->nullable();
                $table->decimal('distance')->unsigned()->nullable();
                $table->decimal('planned_distance')->unsigned()->nullable();
                $table->unsignedInteger('flight_time')->nullable();
                $table->unsignedInteger('planned_flight_time')->nullable();
                $table->decimal('zfw')->unsigned()->nullable();
                $table->decimal('block_fuel')->unsigned()->nullable();
                $table->decimal('fuel_used')->unsigned()->nullable();
                $table->decimal('landing_rate')->nullable();
                $table->smallInteger('score')->nullable();
                $table->text('route')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedTinyInteger('source')->nullable()->default(0);
                $table->string('source_name', 50)->nullable();
                $table->unsignedSmallInteger('state')->default(1);
                $table->char('status', 3)->default('SCH');
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('block_off_time')->nullable();
                $table->dateTime('block_on_time')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('ranks')) {
            Schema::create('ranks', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name', 50)->unique();
                $table->string('image_url')->nullable();
                $table->unsignedInteger('hours')->default(0);
                $table->decimal('acars_base_pay_rate')->unsigned()->nullable()->default(0);
                $table->decimal('manual_base_pay_rate')->unsigned()->nullable()->default(0);
                $table->boolean('auto_approve_acars')->nullable()->default(false);
                $table->boolean('auto_approve_manual')->nullable()->default(false);
                $table->boolean('auto_promote')->nullable()->default(true);
                $table->boolean('auto_approve_above_score')->nullable()->default(false);
                $table->smallInteger('auto_approve_score')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedInteger('role_id')->index('role_user_role_id_foreign');
                $table->unsignedInteger('user_id');
                $table->string('user_type');

                $table->primary(['user_id', 'role_id', 'user_type']);
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name')->unique();
                $table->string('display_name')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
                $table->boolean('read_only')->default(false);
                $table->boolean('disable_activity_checks')->default(false);
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id')->unique();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id')->primary();
                $table->unsignedInteger('offset')->default(0);
                $table->unsignedInteger('order')->default(99);
                $table->string('key')->index();
                $table->string('name');
                $table->string('value');
                $table->string('default')->nullable();
                $table->string('group')->nullable();
                $table->string('type')->nullable();
                $table->text('options')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('simbrief')) {
            Schema::create('simbrief', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id', 36)->primary();
                $table->unsignedInteger('user_id');
                $table->string('flight_id', 36)->nullable();
                $table->string('pirep_id', 36)->nullable()->index();
                $table->unsignedInteger('aircraft_id')->nullable();
                $table->mediumText('acars_xml');
                $table->mediumText('ofp_xml');
                $table->mediumText('fare_data')->nullable();
                $table->timestamps();

                $table->unique(['pirep_id']);
                $table->index(['user_id', 'flight_id']);
            });
        }

        if (!Schema::hasTable('simbrief_aircraft')) {
            Schema::create('simbrief_aircraft', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('icao');
                $table->string('name');
                $table->mediumText('details')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('simbrief_airframes')) {
            Schema::create('simbrief_airframes', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('icao');
                $table->string('name');
                $table->string('airframe_id')->nullable();
                $table->unsignedTinyInteger('source')->nullable();
                $table->mediumText('details')->nullable();
                $table->mediumText('options')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('simbrief_layouts')) {
            Schema::create('simbrief_layouts', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id');
                $table->string('name');
                $table->string('name_long');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stats')) {
            Schema::create('stats', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->string('id')->primary();
                $table->string('value');
                $table->unsignedInteger('order');
                $table->string('type')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subfleet_fare')) {
            Schema::create('subfleet_fare', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedInteger('subfleet_id');
                $table->unsignedInteger('fare_id');
                $table->string('price')->nullable();
                $table->string('cost')->nullable();
                $table->string('capacity')->nullable();
                $table->timestamps();

                $table->primary(['subfleet_id', 'fare_id']);
                $table->index(['fare_id', 'subfleet_id']);
            });
        }

        if (!Schema::hasTable('subfleet_rank')) {
            Schema::create('subfleet_rank', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedInteger('rank_id');
                $table->unsignedInteger('subfleet_id');
                $table->string('acars_pay')->nullable();
                $table->string('manual_pay')->nullable();

                $table->primary(['rank_id', 'subfleet_id']);
                $table->index(['subfleet_id', 'rank_id']);
            });
        }

        if (!Schema::hasTable('subfleets')) {
            Schema::create('subfleets', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedInteger('airline_id')->nullable();
                $table->string('hub_id', 4)->nullable();
                $table->string('type', 50);
                $table->string('simbrief_type', 20)->nullable();
                $table->string('name', 50);
                $table->decimal('cost_block_hour')->unsigned()->nullable()->default(0);
                $table->decimal('cost_delay_minute')->unsigned()->nullable()->default(0);
                $table->unsignedTinyInteger('fuel_type')->nullable();
                $table->decimal('ground_handling_multiplier')->unsigned()->nullable()->default(100);
                $table->decimal('cargo_capacity')->unsigned()->nullable();
                $table->decimal('fuel_capacity')->unsigned()->nullable();
                $table->decimal('gross_weight')->unsigned()->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('typerating_subfleet')) {
            Schema::create('typerating_subfleet', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedInteger('typerating_id');
                $table->unsignedInteger('subfleet_id');

                $table->primary(['typerating_id', 'subfleet_id']);
                $table->index(['typerating_id', 'subfleet_id']);
            });
        }

        if (!Schema::hasTable('typerating_user')) {
            Schema::create('typerating_user', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedInteger('typerating_id');
                $table->unsignedInteger('user_id');

                $table->primary(['typerating_id', 'user_id']);
                $table->index(['typerating_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('typeratings')) {
            Schema::create('typeratings', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name')->unique();
                $table->string('type');
                $table->string('description')->nullable();
                $table->string('image_url')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['id']);
            });
        }

        if (!Schema::hasTable('user_awards')) {
            Schema::create('user_awards', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('award_id');
                $table->timestamps();

                $table->index(['user_id', 'award_id']);
            });
        }

        if (!Schema::hasTable('user_field_values')) {
            Schema::create('user_field_values', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_field_id');
                $table->string('user_id', 16);
                $table->text('value')->nullable();
                $table->timestamps();

                $table->index(['user_field_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('user_fields')) {
            Schema::create('user_fields', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->increments('id');
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->boolean('show_on_registration')->nullable()->default(false);
                $table->boolean('required')->nullable()->default(false);
                $table->boolean('private')->nullable()->default(false);
                $table->boolean('internal')->default(false);
                $table->boolean('active')->nullable()->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_oauth_tokens')) {
            Schema::create('user_oauth_tokens', function (Blueprint $table) {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->unsignedInteger('user_id');
                $table->string('provider');
                $table->text('token');
                $table->text('refresh_token');
                $table->dateTime('last_refreshed_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('permission_role')) {
            Schema::table('permission_role', function (Blueprint $table) {
                $table->foreign(['permission_id'])->references(['id'])->on('permissions')->onUpdate(
                    'cascade'
                )->onDelete('cascade');
                $table->foreign(['role_id'])->references(['id'])->on('roles')->onUpdate(
                    'cascade'
                )->onDelete('cascade');
            });
        }

        if (Schema::hasTable('permission_user')) {
            Schema::table('permission_user', function (Blueprint $table) {
                $table->foreign(['permission_id'])->references(['id'])->on('permissions')->onUpdate(
                    'cascade'
                )->onDelete('cascade');
            });
        }

        if (Schema::hasTable('role_user')) {
            Schema::table('role_user', function (Blueprint $table) {
                $table->foreign(['role_id'])->references(['id'])->on('roles')->onUpdate(
                    'cascade'
                )->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
