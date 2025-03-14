<?php

namespace App\Contracts;

use App\Models\Airline;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Common functionality used across all of the importers
 */
class ImportExport
{
    public $assetType;

    public $status = [
        'success' => [],
        'errors'  => [],
    ];

    /**
     * Hold the columns for the particular table
     */
    public static $columns = [];

    /**
     * @param mixed $row
     */
    public function export($row): array
    {
        throw new \RuntimeException('export not implemented');
    }

    /**
     * @param mixed $index
     *
     * @throws \RuntimeException
     */
    public function import(array $row, $index): bool
    {
        throw new \RuntimeException('import not implemented');
    }

    /**
     * Get the airline from the ICAO. Create it if it doesn't exist
     */
    public function getAirline($code): Airline
    {
        $airline = Airline::firstOrCreate([
            'icao' => $code,
        ], ['name' => $code]);

        return $airline;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return static::$columns;
    }

    /**
     * Do a basic check that the number of columns match
     */
    public function checkColumns($row): bool
    {
        return \count($row) === \count($this->getColumns());
    }

    /**
     * Bubble up an error to the interface that we need to stop
     *
     *
     * @throws ValidationException
     */
    protected function throwError($error, ?\Exception $e = null): void
    {
        Log::error($error);
        if ($e instanceof \Exception) {
            Log::error($e->getMessage());
        }

        $validator = Validator::make([], []);
        $validator->errors()->add('csv_file', $error);

        throw new ValidationException($validator);
    }

    /**
     * Add to the log messages for this importer
     */
    public function log($msg): void
    {
        $this->status['success'][] = $msg;
        Log::info($msg);
    }

    /**
     * Add to the error log for this import
     */
    public function errorLog($msg): void
    {
        $this->status['errors'][] = $msg;
        Log::error($msg);
    }

    /**
     * Set a key-value pair to an array
     */
    protected function kvpToArray($kvp_str, array &$arr)
    {
        $item = explode('=', $kvp_str);
        if (\count($item) === 1) {  // just a list?
            $arr[] = trim($item[0]);
        } else {  // actually a key-value pair
            $k = trim($item[0]);
            $v = trim($item[1]);
            $arr[$k] = $v;
        }
    }

    /**
     * Parse a multi column values field. E.g:
     * Y?price=200&cost=100; F?price=1200
     *    or
     * gate=B32;cost index=100
     *
     * Converted into a multi-dimensional array
     *
     *
     * @return array|string
     */
    public function parseMultiColumnValues($field)
    {
        $ret = [];
        $split_values = explode(';', $field);

        // No multiple values in here, just a straight value
        if (\count($split_values) === 1) {
            if (trim($split_values[0]) === '') {
                return [];
            }

            if (strpos($split_values[0], '?') !== false) {
                // This contains the query string, which turns it into a multi-level array
                $query_str = explode('?', $split_values[0]);
                $parent = trim($query_str[0]);

                $children = [];
                $kvp = explode('&', trim($query_str[1]));
                foreach ($kvp as $items) {
                    if ($items === '' || $items === '0') {
                        continue;
                    }

                    $this->kvpToArray($items, $children);
                }

                $ret[$parent] = $children;

                return $ret;
            }

            // This is not a query string, return it back untouched
            return [$split_values[0]];
        }

        foreach ($split_values as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }

            // This isn't in the query string format, so it's
            // just a straight key-value pair set
            if (strpos($value, '?') === false) {
                $this->kvpToArray($value, $ret);

                continue;
            }

            // This contains the query string, which turns it
            // into the multi-level array

            $query_str = explode('?', $value);
            $parent = trim($query_str[0]);

            $children = [];
            $kvp = explode('&', trim($query_str[1]));
            foreach ($kvp as $items) {
                if ($items === '' || $items === '0') {
                    continue;
                }

                $this->kvpToArray($items, $children);
            }

            $ret[$parent] = $children;
        }

        return $ret;
    }

    /**
     * @return mixed
     */
    public function objectToMultiString($obj)
    {
        if (!\is_array($obj)) {
            return $obj;
        }

        $ret_list = [];
        foreach ($obj as $key => $val) {
            if (is_numeric($key) && !\is_array($val)) {
                $ret_list[] = $val;

                continue;
            }

            $key = trim($key);

            if (!\is_array($val)) {
                $val = trim($val);
                $ret_list[] = "{$key}={$val}";
            } else {
                $q = [];
                foreach ($val as $subkey => $subval) {
                    $q[] = is_numeric($subkey) ? $subval : "{$subkey}={$subval}";
                }

                $q = implode('&', $q);
                $ret_list[] = $q === '' || $q === '0' ? $key : "{$key}?{$q}";
            }
        }

        return implode(';', $ret_list);
    }
}
