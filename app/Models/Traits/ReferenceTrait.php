<?php

namespace App\Models\Traits;

/**
 * Trait ReferenceTrait
 *
 * @property \App\Contracts\Model $ref_model
 * @property mixed                $ref_model_id
 */
trait ReferenceTrait
{
    /**
     * @param  \App\Contracts\Model $object
     * @return self
     */
    public function referencesObject($object)
    {
        $this->ref_model = $object::class;
        $this->ref_model_id = $object->id;
        $this->save();

        return $this;
    }

    /**
     * Return an instance of the object or null
     *
     * @return \App\Contracts\Model|$this|null
     */
    public function getReferencedObject()
    {
        if (!$this->ref_model || !$this->ref_model_id) {
            return null;
        }

        if ($this->ref_model === self::class) {
            return $this;
        }

        try {
            $klass = new $this->ref_model();

            return $klass->find($this->ref_model_id);
        } catch (\Exception) {
            return null;
        }
    }
}
