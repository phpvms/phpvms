<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Exceptions\SettingNotFound;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Log;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Class SettingRepository
 */
class SettingRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    public $cacheMinutes = 1;

    /**
     * @return string
     */
    public function model()
    {
        return Setting::class;
    }

    /**
     * Get a setting, reading it from the cache possibly
     *
     * @param string $key
     *
     * @throws SettingNotFound
     *
     * @return mixed
     */
    public function retrieve($key)
    {
        $key = Setting::formatKey($key);
        $setting = $this->findWhere(['id' => $key], ['type', 'value'])->first();

        if (!$setting) {
            throw new SettingNotFound($key.' not found');
        }

        // cast some types
        switch ($setting->type) {
            case 'bool':
            case 'boolean':
                $value = $setting->value;

                return $value === 'true' || $value === '1' || $value === 1;
            case 'date':
                return Carbon::parse($setting->value);
            case 'int':
            case 'integer':
            case 'number':
                return (int) $setting->value;
            case 'float':
                return (float) $setting->value;
            default:
                return $setting->value;
        }
    }

    /**
     * @alias store($key,$value)
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return null
     */
    public function save($key, $value)
    {
        return $this->store($key, $value);
    }

    /**
     * Update an existing setting with a new value. Doesn't create
     * a new setting
     *
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return null
     */
    public function store($key, $value)
    {
        $key = Setting::formatKey($key);
        $setting = $this->findWhere(
            ['id' => $key],
            ['id', 'value'] // only get these columns
        )->first();

        if (!$setting) {
            return;
        }

        try {
            if (\is_bool($value)) {
                $value = $value === true ? 1 : 0;
            }

            $this->update(['value' => $value], $setting->id);
        } catch (ValidatorException $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }

        return $value;
    }
}
