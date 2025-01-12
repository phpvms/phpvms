<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Change the charset only for mysql
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Check the current charset
        $query = DB::table('information_schema.SCHEMATA')
            ->select('default_character_set_name')
            ->where('schema_name', config('database.connections.mysql.database'))
            ->first();

        if ((property_exists($query, 'default_character_set_name') && $query->default_character_set_name === 'utf8mb4') || (property_exists($query, 'DEFAULT_CHARACTER_SET_NAME') && $query->DEFAULT_CHARACTER_SET_NAME === 'utf8mb4')) {
            return;
        }

        $this->changeDatabaseCharacterSetAndCollation('utf8mb4', 'utf8mb4_unicode_ci', 191, function ($column) {
            return $this->isStringTypeWithLength($column) && $column['type_brackets'] > 191;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->changeDatabaseCharacterSetAndCollation('utf8', 'utf8_unicode_ci', 255, function ($column) {
            return $this->isStringTypeWithLength($column) && $column['type_brackets'] == 191;
        });
    }

    /**
     * Change the database referred to by the connection (null is the default connection) to the provided character set
     * (e.g. utf8mb4) and collation (e.g. utf8mb4_unicode_ci). It may be necessary to change the length of some fixed
     * length columns such as char and varchar to work with the new encoding. In which case the new length of such
     * columns and a callback to determine whether or not that particular column should be altered may be provided. If a
     * connection other than the default connection is to be changed, the string referring to the connection may be
     * provided as the last parameter (This string will be passed to DB::connection(...) to retrieve an instance of that
     * connection).
     *
     * @param string       $charset
     * @param string       $collation
     * @param null|int     $newColumnLength
     * @param Closure|null $columnLengthCallback
     * @param string|null  $connection
     */
    protected function changeDatabaseCharacterSetAndCollation($charset, $collation, $newColumnLength = null, $columnLengthCallback = null, $connection = null)
    {
        $tables = $this->getTables($connection);

        foreach ($tables as $table) {
            $this->updateColumnsInTable($table, $charset, $collation, $newColumnLength, $columnLengthCallback, $connection);
            $this->convertTableCharacterSetAndCollation($table, $charset, $collation, $connection);
        }

        $this->alterDatabaseCharacterSetAndCollation($charset, $collation, $connection);
    }

    /**
     * Get an instance of the database connection provided with an optional string referring to the connection. This
     * should be null if referring to the default connection.
     *
     * @param  string|null                     $connection
     * @return \Illuminate\Database\Connection
     */
    protected function getDatabaseConnection($connection = null)
    {
        return DB::connection($connection);
    }

    /**
     * Get a list of tables on the provided connection.
     *
     * @param  null  $connection
     * @return array
     */
    protected function getTables($connection = null)
    {
        $tables = [];

        $results = $this->getDatabaseConnection($connection)->select('SHOW TABLES');
        foreach ($results as $result) {
            foreach ($result as $key => $value) {
                $tables[] = $value;
                break;
            }
        }

        return $tables;
    }

    /**
     * Given a stdClass representing the column, extract the required information in a more accessible format. The array
     * returned will contain the field name, the type of field (Without the length), the length where applicable (or
     * null), true/false indicating the column allowing null values and the default value.
     *
     * @param  stdClass $column
     * @return array
     */
    protected function extractInformationFromColumn($column)
    {
        $type = $column->Type;
        $typeBrackets = null;
        $typeEnd = null;

        if (preg_match('/^([a-z]+)(?:\\(([^\\)]+?)\\))?(.*)/i', $type, $matches)) {
            $type = strtolower(trim($matches[1]));

            if (isset($matches[2])) {
                $typeBrackets = trim($matches[2]);
            }

            if (isset($matches[3])) {
                $typeEnd = trim($matches[3]);
            }
        }

        return [
            'field'         => $column->Field,
            'type'          => $type,
            'type_brackets' => $typeBrackets,
            'type_end'      => $typeEnd,
            'null'          => strtolower($column->Null) == 'yes',
            'default'       => $column->Default,
            'charset'       => is_string($column->Collation) && ($pos = strpos($column->Collation, '_')) !== false ? substr($column->Collation, 0, $pos) : null,
            'collation'     => $column->Collation,
        ];
    }

    /**
     * Tell if the provided column is a string/character type and needs to have it's charset/collation changed.
     *
     * @param  string $column
     * @return bool
     */
    protected function isStringType($column)
    {
        return in_array(strtolower($column['type']), ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set']);
    }

    /**
     * Tell if the provided column is a string/character type with a length.
     *
     * @param  string $column
     * @return bool
     */
    protected function isStringTypeWithLength($column)
    {
        return in_array(strtolower($column['type']), ['char', 'varchar']);
    }

    /**
     * Update all of the string/character columns in the database to be the new collation. Additionally, modify the
     * lengths of those columns that have them to be the newLength provided, when the shouldUpdateLength callback passed
     * returns true.
     *
     * @param string      $table
     * @param string      $charset
     * @param string      $collation
     * @param int|null    $newLength
     * @param string|null $connection
     */
    protected function updateColumnsInTable($table, $charset, $collation, $newLength = null, ?Closure $shouldUpdateLength = null, $connection = null)
    {
        $columnsToChange = [];

        foreach ($this->getColumnsFromTable($table, $connection) as $column) {
            $column = $this->extractInformationFromColumn($column);

            if ($this->isStringType($column)) {
                $sql = 'CHANGE `%field%` `%field%` %type%%brackets% CHARACTER SET %charset% COLLATE %collation% %null% %default%';
                $search = ['%field%', '%type%', '%brackets%', '%charset%', '%collation%', '%null%', '%default%'];
                $replace = [
                    $column['field'],
                    $column['type'],
                    $column['type_brackets'] ? '('.$column['type_brackets'].')' : '',
                    $charset,
                    $collation,
                    $column['null'] ? 'NULL' : 'NOT NULL',
                    is_null($column['default']) ? ($column['null'] ? 'DEFAULT NULL' : '') : 'DEFAULT \''.$column['default'].'\'',
                ];

                if ($this->isStringTypeWithLength($column) && $shouldUpdateLength($column) && is_int($newLength) && $newLength > 0) {
                    $replace[2] = '('.$newLength.')';
                }

                $columnsToChange[] = trim(str_replace($search, $replace, $sql));
            }
        }

        if (count($columnsToChange) > 0) {
            $query = "ALTER TABLE `{$table}` ".implode(', ', $columnsToChange);

            $this->getDatabaseConnection($connection)->update($query);
        }
    }

    /**
     * Get a list of all the columns for the provided table. Returns an array of stdClass objects.
     *
     * @param  string      $table
     * @param  string|null $connection
     * @return array
     */
    protected function getColumnsFromTable($table, $connection = null)
    {
        return $this->getDatabaseConnection($connection)->select('SHOW FULL COLUMNS FROM '.$table);
    }

    /**
     * Convert a table's character set and collation.
     *
     * @param string      $table
     * @param string      $charset
     * @param string      $collation
     * @param string|null $connection
     */
    protected function convertTableCharacterSetAndCollation($table, $charset, $collation, $connection = null)
    {
        $query = "ALTER TABLE {$table} CONVERT TO CHARACTER SET {$charset} COLLATE {$collation}";
        $this->getDatabaseConnection($connection)->update($query);

        $query = "ALTER TABLE {$table} DEFAULT CHARACTER SET {$charset} COLLATE {$collation}";
        $this->getDatabaseConnection($connection)->update($query);
    }

    /**
     * Change the entire database's (The database represented by the connection) character set and collation.
     *
     * # Note: This must be done with the unprepared method, as PDO complains that the ALTER DATABASE command is not yet
     *         supported as a prepared statement.
     *
     * @param string      $charset
     * @param string      $collation
     * @param string|null $connection
     */
    protected function alterDatabaseCharacterSetAndCollation($charset, $collation, $connection = null)
    {
        $database = $this->getDatabaseConnection($connection)->getDatabaseName();

        $query = "ALTER DATABASE {$database} CHARACTER SET {$charset} COLLATE {$collation}";

        $this->getDatabaseConnection($connection)->unprepared($query);
    }
};
