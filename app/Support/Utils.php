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
     *
     * @param int [$length]
     */
    public static function generateNewId(?int $length = null): string
    {
        if (!$length) {
            $length = Model::ID_MAX_LENGTH;
        }

        $hashids = new Hashids(uniqid(), $length);
        $mt = str_replace('.', '', microtime(true));

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
        /** @var \Nwidart\Modules\Module $installer */
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
}
