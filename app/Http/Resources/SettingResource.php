<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Support\Settings\SettingDefinition;
use Override;

/**
 * One core setting, in the shape the settings table rows were listed in.
 *
 * @property-read SettingDefinition $resource
 */
class SettingResource extends Resource
{
    #[Override]
    public function toArray($request)
    {
        $definition = $this->resource;

        return [
            'id'          => $definition->id(),
            'type'        => $definition->type->legacyName(),
            'name'        => $definition->label(),
            'value'       => $definition->toLegacyString(app($definition->class)->{$definition->name}),
            'group'       => $definition->group,
            'order'       => $definition->order,
            'description' => $definition->description(),
        ];
    }
}
