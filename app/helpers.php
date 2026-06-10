<?php

use App\Models\Addon;
use App\Services\KvpService;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/*
 * array_key_first only exists in PHP 7.3+
 */
if (!function_exists('array_key_first')) {
    function array_key_first(array $arr): int|string|null
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
     */
    function in_mask($mask, $value): bool
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
     */
    function get_truth_state($state): bool
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
     */
    function list_to_assoc(array $list): array
    {
        $ret = [];
        foreach ($list as $item) {
            if (substr_count((string) $item, '=') !== 0) {
                [$item, $title] = explode('=', (string) $item);
            } else {
                $title = $item;
            }

            $item = trim((string) $item);
            $title = trim((string) $title);

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
     */
    function list_to_editable(array $list): array
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
     * @return Factory|Illuminate\View\View
     */
    function skin_view(string $template, array $vars = [], array $merge_data = []): Factory|Illuminate\Contracts\View\View
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
    function setting(string $key, $default = null)
    {
        /** @var SettingService $settingService */
        $settingService = app(SettingService::class);

        try {
            if (app()->environment('production')) {
                $cache = config('cache.keys.SETTINGS');

                $value = Cache::remember($cache['key'].$key, $cache['time'], fn () => $settingService->retrieve($key));
            } else {
                $value = $settingService->retrieve($key);
            }
        } catch (Throwable) {
            return $default;
        }

        return $value;
    }
}

/*
 * Shortcut for retrieving a setting value
 */
if (!function_exists('setting_save')) {
    function setting_save(string $key, $value)
    {
        /** @var SettingService $settingService */
        $settingService = app(SettingService::class);
        $settingService->save($key, $value);

        return $value;
    }
}

/*
 * Shortcut for retrieving a KVP
 */
if (!function_exists('kvp')) {
    /**
     * Read a value from the KVP service
     *
     * @param  string|null $default
     * @return mixed|null
     */
    function kvp(string $key, $default = null)
    {
        /** @var KvpService $kvpService */
        $kvpService = app(KvpService::class);

        try {
            $value = $kvpService->get($key, $default);
        } catch (Exception) {
            return $default;
        }

        return $value;
    }
}

/*
 * Shortcut for persisting a KVP
 */
if (!function_exists('kvp_save')) {
    /**
     * Persist a value to the KVP service
     */
    function kvp_save(string $key, string $value): void
    {
        /** @var KvpService $kvpService */
        $kvpService = app(KvpService::class);
        $kvpService->save($key, $value);
    }
}

/*
 * Wrap the asset URL in the publicBaseUrl that's been
 * set
 */
if (!function_exists('public_asset')) {

    #[Deprecated('use asset() instead')]
    function public_asset($path, array $parameters = []): string
    {
        $path = str_replace('//', '/', $path);

        return url($path, $parameters);
    }
}

if (!function_exists('public_mix')) {
    #[Deprecated('use asset() instead')]
    function public_mix($path, array $parameters = []): string
    {
        return public_asset($path, $parameters);
    }
}

/**
 * Wrap a call to url() and append the public folder before it
 */
if (!function_exists('public_url')) {
    #[Deprecated('use url() instead')]
    function public_url($path, array $parameters = []): string
    {
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
     */
    function show_datetime(?Carbon $date = null): string
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
     * @param string $default_timezone Default timezone to use, defaults to UTC
     */
    function show_date(Carbon $date, $default_timezone = 'UTC'): string
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
     * @param string $default_timezone A default timezone to use (UTC by default)
     */
    function show_datetime_format(Carbon $date, string $format, $default_timezone = 'UTC'): string
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
    function secstohhmm($seconds): void
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
            $key = strtolower((string) $key);
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
     * @param string $key Key from phpvms.config.docs
     */
    function docs_link(string $key): string
    {
        return config('phpvms.docs.root').config('phpvms.docs.'.$key);
    }
}

if (!function_exists('check_module')) {
    /**
     * Check if a module is installed and active
     *
     * @param string $module_name
     */
    function check_module($module_name): bool
    {
        /** @var Addon|null $phpvms_module */
        $phpvms_module = Addon::where('name', $module_name)->first();

        return $phpvms_module !== null && $phpvms_module->enabled;
    }
}

if (!function_exists('decode_days')) {
    /**
     * Decode days of flights for schedule display
     *
     * @param  int    $flight_days
     * @return string Monday, Tuesday, Friday, Sunday
     */
    function decode_days($flight_days): string
    {
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            if (($flight_days & 2 ** $i) !== 0) {
                $days[] = jddayofweek($i, 1);
            }
        }

        return implode(', ', $days);
    }
}

if (!function_exists('installed')) {
    /**
     * Determine whether the application has been installed.
     *
     * Uses the existence of the settings table as the installation indicator.
     * Returns false when the database is unreachable or not yet configured.
     */
    function installed(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('paginate_limit')) {
    /**
     * Resolve a `?limit=` query value to a safe per-page integer.
     *
     * Falls back to `phpvms.pagination.limit` when no limit is provided
     * and clamps the result to `[1, phpvms.pagination.max]` so the API
     * cannot be coerced into oversized result sets.
     *
     * @param  int|null $requested Raw `?limit=` from the request (post integer cast)
     * @return int      Sanitized per-page value
     */
    function paginate_limit(?int $requested = null): int
    {
        $default = (int) config('phpvms.pagination.limit', 50);
        $max = (int) config('phpvms.pagination.max', 100);
        $value = $requested ?: $default;

        return min(max($value, 1), $max);
    }
}
