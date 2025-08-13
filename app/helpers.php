<?php

use App\Exceptions\SettingNotFound;
use App\Repositories\KvpRepository;
use App\Repositories\SettingRepository;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Nwidart\Modules\Facades\Module;

/*
 * array_key_first only exists in PHP 7.3+
 */
if (!function_exists('array_key_first')) {
    function array_key_first(array $arr)
    {
        foreach (array_keys($arr) as $key) {
            return $key;
        }

        return null;
    }
}

if (!function_exists('in_mask')) {
    /**
     * Return true/false if a value exists in a mask
     *
     *
     * @return bool
     */
    function in_mask($mask, $value)
    {
        if (empty($mask)) {
            $mask = 0;
        }

        return ($mask & $value) === $value;
    }
}

if (!function_exists('get_truth_state')) {
    /**
     * Check if the passed state matches any of the states that
     * we regard as being true or false
     *
     *
     * @return bool
     */
    function get_truth_state($state)
    {
        $enabledStates = [
            'yes',
            'y',
            'on',
            'true',
            '1',
            true,
        ];

        if (is_string($state)) {
            $state = strtolower($state);
        }

        return in_array($state, $enabledStates, false);
    }
}

if (!function_exists('list_to_assoc')) {
    /**
     * Converts a straight list into an assoc array with
     * key and value being the same. Mainly for a select box
     *
     * e.g.:
     *    [ 0 => 'item1', 1 => 'item2']
     * to:
     *    ['item1' => 'item1', 'item2' => 'item2']
     *
     *
     * @return array
     */
    function list_to_assoc(array $list)
    {
        $ret = [];
        foreach ($list as $item) {
            if (substr_count($item, '=') !== 0) {
                [$item, $title] = explode('=', $item);
            } else {
                $title = $item;
            }

            $item = trim($item);
            $title = trim($title);

            $ret[$item] = $title;
        }

        return $ret;
    }
}

if (!function_exists('list_to_editable')) {
    /**
     * Convert a list (select box) into an editable list
     * https://vitalets.github.io/x-editable/docs.html#select
     * Takes a list of:
     *    [value => text, valueN => textN, ...]
     * Return:
     *    [{value: 1, text: "text1"}, {value: 2, text: "text2"}, ...]
     *
     *
     * @return array
     */
    function list_to_editable(array $list)
    {
        $editable = [];
        foreach ($list as $value => $key) {
            $editable[] = [
                'text'  => $key,
                'value' => $value,
            ];
        }

        return $editable;
    }
}

if (!function_exists('skin_view')) {
    /**
     * Render a skin
     *
     *
     * @return Factory|\Illuminate\View\View
     */
    function skin_view($template, array $vars = [], array $merge_data = [])
    {
        // Add the current skin name so we don't need to hardcode it in the templates
        // Makes it a bit easier to create a new skin by modifying an existing one
        if (View::exists($template)) {
            return view($template, $vars, $merge_data);
        }

        $tpl = 'layouts/'.setting('general.theme', 'default').'/'.$template;

        return view($tpl, $vars, $merge_data);
    }
}

/*
 * Shortcut for retrieving a setting value
 */
if (!function_exists('setting')) {
    /**
     * Read a setting from the settings table
     *
     * @param  mixed      $default
     * @return mixed|null
     */
    function setting($key, $default = null)
    {
        /** @var SettingRepository $settingRepo */
        $settingRepo = app(SettingRepository::class);

        try {
            if (app()->environment('production')) {
                $cache = config('cache.keys.SETTINGS');

                $value = Cache::remember($cache['key'].$key, $cache['time'], function () use ($key, $settingRepo) {
                    return $settingRepo->retrieve($key);
                });
            } else {
                $value = $settingRepo->retrieve($key);
            }
        } catch (SettingNotFound $e) {
            return $default;
        } catch (Exception $e) {
            return $default;
        }

        return $value;
    }
}

/*
 * Shortcut for retrieving a setting value
 */
if (!function_exists('setting_save')) {
    function setting_save($key, $value)
    {
        /** @var SettingRepository $settingRepo */
        $settingRepo = app(SettingRepository::class);
        $settingRepo->save($key, $value);

        return $value;
    }
}

/*
 * Shortcut for retrieving a KVP
 */
if (!function_exists('kvp')) {
    /**
     * Read a setting from the KVP repository
     *
     * @param  string|null $default
     * @return mixed|null
     */
    function kvp(string $key, $default = null)
    {
        /** @var KvpRepository $kvpRepo */
        $kvpRepo = app(KvpRepository::class);

        try {
            $value = $kvpRepo->get($key, $default);
        } catch (Exception $e) {
            return $default;
        }

        return $value;
    }
}

/*
 * Shortcut for retrieving a KVP
 */
if (!function_exists('kvp_save')) {
    /**
     * Read a setting from the KVP repository
     *
     *
     * @return mixed|null
     */
    function kvp_save(string $key, string $value)
    {
        /** @var KvpRepository $kvpRepo */
        $kvpRepo = app(KvpRepository::class);
        $kvpRepo->save($key, $value);
    }
}

/*
 * Wrap the asset URL in the publicBaseUrl that's been
 * set
 */
if (!function_exists('public_asset')) {
    function public_asset($path, array $parameters = [])
    {
        $publicBaseUrl = app()->publicUrlPath();
        $path = $publicBaseUrl.$path;
        $path = str_replace('//', '/', $path);

        return url($path, $parameters);
    }
}

/*
 * Call mix() and then prepend the proper public URL
 */
if (!function_exists('public_mix')) {
    function public_mix($path, array $parameters = [])
    {
        try {
            $path = mix($path);
        } catch (Exception $e) {
        }

        return public_asset($path, $parameters);
    }
}

/**
 * Wrap a call to url() and append the public folder before it
 */
if (!function_exists('public_url')) {
    function public_url($path, array $parameters = [])
    {
        $publicBaseUrl = app()->publicUrlPath();
        $path = $publicBaseUrl.$path;

        $path = str_replace('//', '/', $path);

        return url($path, $parameters);
    }
}

/*
 * Show a date/time in the proper timezone for a user
 */
if (!function_exists('show_datetime')) {
    /**
     * Format the a Carbon date into the datetime string
     * but convert it into the user's timezone
     *
     *
     * @return string
     */
    function show_datetime(?Carbon $date = null)
    {
        if (!$date instanceof Carbon) {
            return '-';
        }

        $timezone = 'UTC';
        if (Auth::check()) {
            $timezone = Auth::user()->timezone ?: $timezone;
        }

        return $date->timezone($timezone)->toDayDateTimeString();
    }
}

/*
 * Show a date/time in the proper timezone for a user
 */
if (!function_exists('show_date')) {
    /**
     * Format the a Carbon date into the datetime string
     * but convert it into the user's timezone
     *
     * @param  string $default_timezone Default timezone to use, defaults to UTC
     * @return string
     */
    function show_date(Carbon $date, $default_timezone = 'UTC')
    {
        $timezone = $default_timezone;
        if (Auth::check()) {
            $timezone = Auth::user()->timezone ?: $timezone;
        }

        return $date->timezone($timezone)->toFormattedDateString();
    }
}

/*
 * Show a date/time in the proper timezone for a user
 */
if (!function_exists('show_datetime_format')) {
    /**
     * Format the a Carbon date into the datetime string
     * but convert it into the user's timezone
     *
     * @param  string $format
     * @param  string $default_timezone A default timezone to use (UTC by default)
     * @return string
     */
    function show_datetime_format(Carbon $date, $format, $default_timezone = 'UTC')
    {
        $timezone = $default_timezone;
        if (Auth::check()) {
            $timezone = Auth::user()->timezone ?: $timezone;
        }

        return $date->timezone($timezone)->format($format);
    }
}

if (!function_exists('secstohhmm')) {
    /**
     * Convert seconds to hhmm format
     */
    function secstohhmm($seconds)
    {
        $seconds = round((float) $seconds);
        $hhmm = sprintf('%02d%02d', $seconds / 3600, $seconds / 60 % 60);
        echo $hhmm;
    }
}

if (!function_exists('_fmt')) {
    /**
     * Replace strings
     *
     * @param        $line    "Hi, my name is :name"
     * @param  array $replace ['name' => 'Nabeel']
     * @return mixed
     */
    function _fmt($line, array $replace)
    {
        if ($replace === []) {
            return $line;
        }

        foreach ($replace as $key => $value) {
            $key = strtolower($key);
            $line = str_replace(
                [':'.$key],
                [$value],
                $line
            );
        }

        return $line;
    }
}

if (!function_exists('docs_link')) {
    /**
     * Return a link to the docs
     *
     * @param  string $key Key from phpvms.config.docs
     * @return string
     */
    function docs_link($key)
    {
        return config('phpvms.docs.root').config('phpvms.docs.'.$key);
    }
}

if (!function_exists('check_module')) {
    /**
     * Check if a module is installed and active
     *
     * @param  string $module_name
     * @return bool
     */
    function check_module($module_name)
    {
        $phpvms_module = Module::find($module_name);

        return filled($phpvms_module) ? $phpvms_module->isEnabled() : false;
    }
}

if (!function_exists('decode_days')) {
    /**
     * Decode days of flights for schedule display
     *
     * @param  int    $flight_days
     * @return string Monday, Tuesday, Friday, Sunday
     */
    function decode_days($flight_days)
    {
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            if ($flight_days & pow(2, $i)) {
                $days[] = jddayofweek($i, 1);
            }
        }

        return implode(', ', $days);
    }
}
