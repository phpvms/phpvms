<?php

namespace App\Utils;

use App\Contracts\Service;
use Spatie\Valuestore\Valuestore;

class IdMapper extends Service
{
    private Valuestore $valueStore;

    public function __construct()
    {
        $this->valueStore = Valuestore::make(storage_path('app/legacy_migration.json'));
    }

    /**
     * Create a new mapping between an old ID and the new one.
     *
     * @param string $entity Name of the entity (e,g table)
     */
    public function addMapping(string $entity, string $old_id, string $new_id)
    {
        $key_name = $entity.'_'.$old_id;
        if (!$this->valueStore->has($key_name)) {
            $this->valueStore->put($key_name, $new_id);
        }
    }

    /**
     * Return the ID for a mapping.
     *
     *
     * @return bool
     */
    public function getMapping($entity, $old_id): bool|int
    {
        $key_name = $entity.'_'.$old_id;
        if (!$this->valueStore->has($key_name)) {
            return 0;
        }

        return $this->valueStore->get($key_name);
    }

    /**
     * Clear the value store.
     */
    public function clear()
    {
        $this->valueStore->flush();
    }
}
