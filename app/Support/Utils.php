<?php

namespace App\Support;

use App\Contracts\Model;
use Hashids\Hashids;
use Illuminate\Contracts\Container\BindingResolutionException;
use Nwidart\Modules\Facades\Module;
use Pdp\Rules;

/**
 * Global utilities
 */
class Utils
{
    /**
     * Generate a new ID with a given length
     */
    public static function generateNewId(?int $length = null): string
    {
        if (!$length) {
            $length = Model::ID_MAX_LENGTH;
        }

        $hashids = new Hashids(uniqid(), $length);
        $mt = str_replace('.', '', (string) microtime(true));

        return $hashids->encode($mt);
    }

    /**
     * Returns a 40 character API key that a user can use
     */
    public static function generateApiKey(): string
    {
        return substr(sha1(time().mt_rand()), 0, 20);
    }

    /**
     * Simple check on the first character if it's an object or not
     */
    public static function isObject($obj): bool
    {
        if (!$obj) {
            return false;
        }

        return $obj[0] === '{' || $obj[0] === '[';
    }

    /**
     * Enable the debug toolbar
     */
    public static function enableDebugToolbar()
    {
        try {
            app('debugbar')->enable();
        } catch (BindingResolutionException $e) {
        }
    }

    /**
     * Disable the debug toolbar
     */
    public static function disableDebugToolbar()
    {
        try {
            app('debugbar')->disable();
        } catch (BindingResolutionException $e) {
        }
    }

    /**
     * Is the installer enabled?
     *
     * @return bool
     */
    public static function installerEnabled()
    {
        /** @var ?\Nwidart\Modules\Module $installer */
        $installer = Module::find('installer');
        if (!$installer) {
            return false;
        }

        return $installer->isEnabled();
    }

    /**
     * Get the domain from a URL
     */
    public static function getRootDomain(string $url): string
    {
        if (!str_starts_with($url, 'http')) {
            $url = 'http://'.$url;
        }

        $parsed_url = parse_url($url, PHP_URL_HOST);
        if (empty($parsed_url)) {
            return '';
        }

        if (str_ends_with($parsed_url, 'localhost')) {
            return 'localhost';
        }

        if (str_ends_with($parsed_url, '/')) {
            $parsed_url = substr($parsed_url, 0, strlen($parsed_url) - 1);
        }

        $rules = Rules::fromPath(resource_path('tld/public_suffix_list.dat'));
        $domain = $rules->resolve($parsed_url);

        $val = $domain->registrableDomain()->toString();
        if ($val !== '' && $val !== '0') {
            return $val;
        }

        // Couldn't validate a domain, see if this is an IP address?
        if (filter_var($parsed_url, FILTER_VALIDATE_IP)) {
            return $parsed_url;
        }

        return '';
    }

    /**
     * Parse a multi column values field. E.g:
     * Y?price=200&cost=100; F?price=1200
     *    or
     * gate=B32;cost index=100
     *
     * Converted into a multi-dimensional array
     */
    public static function parseMultiColumnValues(string $field): array|string
    {
        $ret = [];
        $split_values = explode(';', $field);

        // No multiple values in here, just a straight value
        if (\count($split_values) === 1) {
            if (trim($split_values[0]) === '') {
                return [];
            }

            if (str_contains($split_values[0], '?')) {
                // This contains the query string, which turns it into a multi-level array
                $query_str = explode('?', $split_values[0]);
                $parent = trim($query_str[0]);

                $children = [];
                $kvp = explode('&', trim($query_str[1]));
                foreach ($kvp as $items) {
                    if ($items === '' || $items === '0') {
                        continue;
                    }

                    self::kvpToArray($items, $children);
                }

                $ret[$parent] = $children;

                return $ret;
            }

            if (str_contains($split_values[0], '=')) {
                $ret = [];
                self::kvpToArray($split_values[0], $ret);

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
            if (!str_contains($value, '?')) {
                self::kvpToArray($value, $ret);

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

                self::kvpToArray($items, $children);
            }

            $ret[$parent] = $children;
        }

        return $ret;
    }

    public static function kvpToArray($kvp_str, array &$arr): void
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

    public static function objectToMultiString(object|array $obj): object|string
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
