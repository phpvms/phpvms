<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\Valuestore\Valuestore;

class KvpService
{
    private Valuestore $valueStore;

    public function __construct()
    {
        $this->valueStore = Valuestore::make(config('phpvms.kvp_storage_path'));
    }

    /**
     * @param  null              $default
     * @return array|string|null
     */
    public function retrieve(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * Get a value from the KVP store
     *
     * @param  mixed             $default default value to return
     * @return array|string|null
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->valueStore->has($key)) {
            return $default;
        }

        return $this->valueStore->get($key);
    }

    /**
     * @alias store($key,$value)
     */
    public function save(string $key, mixed $value): Valuestore
    {
        return $this->valueStore->put($key, $value);
    }

    /**
     * Save a value to the KVP store
     */
    public function store(string $key, mixed $value): Valuestore
    {
        return $this->valueStore->put($key, $value);
    }
}
