<?php

namespace App\Repositories;

use Spatie\Valuestore\Valuestore;

class KvpRepository
{
    private $valueStore;

    public function __construct()
    {
        $this->valueStore = Valuestore::make(config('phpvms.kvp_storage_path'));
    }

    /**
     * @param null  $default
     * @param mixed $key
     *
     * @return array|string|null
     */
    public function retrieve($key, $default = null)
    {
        return $this->get($key, $default);
    }

    /**
     * Get a value from the KVP store
     *
     * @param string $key
     * @param mixed  $default default value to return
     *
     * @return array|string|null
     */
    public function get($key, $default = null)
    {
        if (!$this->valueStore->has($key)) {
            return $default;
        }

        return $this->valueStore->get($key);
    }

    /**
     * @alias store($key,$value)
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return null
     */
    public function save($key, $value)
    {
        return $this->store($key, $value);
    }

    /**
     * Save a value to the KVP store
     *
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return null
     */
    public function store($key, $value)
    {
        return $this->valueStore->put($key, $value);
    }
}
