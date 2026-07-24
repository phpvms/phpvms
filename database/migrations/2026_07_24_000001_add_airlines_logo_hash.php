<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Cache-busting token for an uploaded logo. Uploads use a deterministic
        // filename (airlines/{id}.{ext}) so the URL never changes when a logo is
        // replaced; this crc32b of the file contents is what changes instead.
        // Empty for airlines whose logo is an external URL we do not host.
        Schema::table('airlines', function (Blueprint $table): void {
            $table->string('logo_hash', 8)->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table): void {
            $table->dropColumn('logo_hash');
        });
    }
};
