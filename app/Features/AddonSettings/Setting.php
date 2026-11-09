<?php

declare(strict_types=1);

namespace App\Features\AddonSettings;

use InvalidArgumentException;

/**
 * Typed addon setting declaration. `toArray()` returns the entry shape
 * `AddonSettingSyncService::syncEntry()` reads. `options` is the
 * comma-separated `key=label` string the admin select renders from.
 */
final readonly class Setting
{
    private function __construct(
        public string $key,
        public string $name,
        public mixed $default,
        public string $group,
        public int $order,
        public string $type,
        public string $options,
        public ?string $description,
    ) {}

    public static function text(string $key, string $name, ?string $default = null, string $group = 'General', int $order = 0, ?string $description = null): self
    {
        return new self($key, $name, $default, $group, $order, 'text', '', $description);
    }

    public static function int(string $key, string $name, ?int $default = null, string $group = 'General', int $order = 0, ?string $description = null): self
    {
        return new self($key, $name, $default, $group, $order, 'int', '', $description);
    }

    public static function float(string $key, string $name, ?float $default = null, string $group = 'General', int $order = 0, ?string $description = null): self
    {
        return new self($key, $name, $default, $group, $order, 'float', '', $description);
    }

    public static function bool(string $key, string $name, ?bool $default = null, string $group = 'General', int $order = 0, ?string $description = null): self
    {
        return new self($key, $name, $default, $group, $order, 'bool', '', $description);
    }

    public static function date(string $key, string $name, ?string $default = null, string $group = 'General', int $order = 0, ?string $description = null): self
    {
        return new self($key, $name, $default, $group, $order, 'date', '', $description);
    }

    /**
     * @param array<string|int, string> $options option key => label
     *
     * @throws InvalidArgumentException when options are empty or default is not an option key
     */
    public static function select(string $key, string $name, array $options, string|int $default, string $group = 'General', int $order = 0, ?string $description = null): self
    {
        if ($options === []) {
            throw new InvalidArgumentException(sprintf('Setting "%s" select requires non-empty options.', $key));
        }

        if (!array_key_exists($default, $options)) {
            throw new InvalidArgumentException(sprintf('Setting "%s" default "%s" is not one of the option keys.', $key, $default));
        }

        $pairs = [];
        foreach ($options as $optionKey => $label) {
            $pairs[] = $optionKey.'='.$label;
        }

        return new self($key, $name, $default, $group, $order, 'select', implode(',', $pairs), $description);
    }

    /**
     * A row the admin settings page does not list. Addons with their own
     * configuration UI keep their values here; name and description are
     * optional documentation for whoever reads the table.
     */
    public static function hidden(string $key, mixed $default = null, string $group = 'General', ?string $name = null, ?string $description = null, int $order = 0): self
    {
        return new self($key, $name ?? $key, $default, $group, $order, 'hidden', '', $description);
    }

    /**
     * @return array{key: string, name: string, default: mixed, group: string, order: int, type: string, options: string, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'key'         => $this->key,
            'name'        => $this->name,
            'default'     => $this->default,
            'group'       => $this->group,
            'order'       => $this->order,
            'type'        => $this->type,
            'options'     => $this->options,
            'description' => $this->description,
        ];
    }
}
