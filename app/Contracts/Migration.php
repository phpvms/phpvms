<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Class Migration
 */
abstract class Migration extends \Illuminate\Database\Migrations\Migration
{
    abstract public function up();

    public function down() {}
}
