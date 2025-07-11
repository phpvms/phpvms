<?php

namespace App\Utils;

use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;

/**
 * Real basic to interface with an importer
 */
class ImporterDB
{
    /**
     * @var int
     */
    public $batchSize;

    private ?\PDO $conn = null;

    private readonly string $dsn;

    /**
     * @param mixed[] $creds
     */
    public function __construct(private $creds)
    {
        $this->dsn = 'mysql:'.implode(';', [
            'host='.$this->creds['host'],
            'port='.$this->creds['port'],
            'dbname='.$this->creds['name'],
        ]);

        // Log::info('Using DSN: '.$this->dsn);

        $this->batchSize = config('installer.importer.batch_size', 20);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function connect(): void
    {
        try {
            $this->conn = new PDO($this->dsn, $this->creds['user'], $this->creds['pass']);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            Log::error($e);

            throw $e;
        }
    }

    public function close(): void
    {
        if ($this->conn instanceof \PDO) {
            $this->conn = null;
        }
    }

    /**
     * Return the table name with the prefix
     */
    public function tableName(string $table): string
    {
        if ($this->creds['table_prefix'] !== false) {
            return $this->creds['table_prefix'].$table;
        }

        return $table;
    }

    /**
     * Does a table exist? Try to get the column information on it.
     * The result will be 'false' if that table isn't there
     */
    public function tableExists(string $table): bool
    {
        $this->connect();

        $sql = 'SHOW COLUMNS FROM '.$this->tableName($table);
        $result = $this->conn->query($sql);

        return (bool) $result;
    }

    /**
     * Get the names of the columns for a particular table
     *
     *
     * @return list
     */
    public function getColumns(string $table): array
    {
        $this->connect();

        $sql = 'SHOW COLUMNS FROM '.$this->tableName($table);
        $result = $this->conn->query($sql)->fetchAll();

        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row->Field;
        }

        return $rows;
    }

    public function getTotalRows(string $table): int
    {
        $this->connect();

        $sql = 'SELECT COUNT(*) FROM '.$this->tableName($table);
        $rows = $this->conn->query($sql)->fetchColumn();

        Log::info('Found '.$rows.' rows in '.$table);

        return (int) $rows;
    }

    /**
     * Read rows from a table with a given assoc array. Simple
     */
    public function findBy(string $table, array $attrs): \PDOStatement|false
    {
        $this->connect();

        $where = [];
        foreach ($attrs as $col => $value) {
            $where[] = $col.'=\''.$value.'\'';
        }

        $where = implode(' AND ', $where);

        $sql = implode(' ', [
            'SELECT',
            '*',
            'FROM',
            $this->tableName($table),
            'WHERE',
            $where,
        ]);

        return $this->conn->query($sql);
    }

    /**
     * Read all the rows in a table, but read them in a batched manner
     *
     * @param string $table        The name of the table
     * @param string $order_by     Column to order by
     * @param int    $start_offset
     * @param string $fields
     */
    public function readRows($table, string $order_by = 'id', $start_offset = 0, $fields = '*'): array
    {
        $this->connect();

        $offset = $start_offset;
        // $total_rows = $this->getTotalRows($table);

        $rows = [];
        $result = $this->readRowsOffset($table, $this->batchSize, $offset, $order_by, $fields);
        if ($result === false || $result === null) {
            return [];
        }

        try {
            foreach ($result as $row) {
                $rows[] = $row;
            }
        } catch (\Exception $e) {
            Log::error('foreach rows error: '.$e->getMessage());
        }

        return $rows;
    }

    /**
     * @param  int                      $limit  Number of rows to read
     * @param  int                      $offset Where to start from
     * @param  string                   $fields
     * @return false|\PDOStatement|null
     */
    public function readRowsOffset(string $table, $limit, $offset, string $order_by, $fields = '*')
    {
        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }

        $sql = implode(' ', [
            'SELECT',
            $fields,
            'FROM',
            $this->tableName($table),
            'ORDER BY '.$order_by.' ASC',
            'LIMIT '.$limit,
            'OFFSET '.$offset,
        ]);

        try {
            $result = $this->conn->query($sql);
            if (!$result || $result->rowCount() === 0) {
                return null;
            }

            return $result;
        } catch (PDOException $e) {
            // Without incrementing the offset, it should re-run the same query
            Log::error('Error readRowsOffset: '.$e->getMessage());

            if (str_contains($e->getMessage(), 'server has gone away')) {
                $this->connect();
            }
        } catch (\Exception $e) {
            Log::error('Error readRowsOffset: '.$e->getMessage());
        }

        return null;
    }
}
