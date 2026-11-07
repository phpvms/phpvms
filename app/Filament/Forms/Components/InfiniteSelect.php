<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;
use LogicException;

/**
 * Single-select backed by Tom Select that pages its options from the server
 * as the list is scrolled.
 */
class InfiniteSelect extends Field
{
    protected string $view = 'filament.forms.components.infinite-select';

    protected ?Closure $getPageUsing = null;

    protected ?Closure $getOptionLabelUsing = null;

    protected int|Closure $perPage = 50;

    protected string|Closure|null $placeholder = null;

    /**
     * @param Closure(string $search, int $page, int $perPage): array{options: array<int, array{value: string|int, label: string}>, hasMore: bool} $callback
     *                                                                                                                                                       Returns the requested page; see App\Support\OptionPage::fromQuery().
     */
    public function getPageUsing(Closure $callback): static
    {
        $this->getPageUsing = $callback;

        return $this;
    }

    public function getOptionLabelUsing(Closure $callback): static
    {
        $this->getOptionLabelUsing = $callback;

        return $this;
    }

    public function perPage(int|Closure $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function placeholder(string|Closure|null $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPerPage(): int
    {
        return max(1, (int) $this->evaluate($this->perPage));
    }

    public function getPlaceholder(): ?string
    {
        return $this->evaluate($this->placeholder);
    }

    public function getInitialLabel(): ?string
    {
        $state = $this->getState();

        if (blank($state) || !$this->getOptionLabelUsing instanceof Closure) {
            return null;
        }

        return $this->evaluate($this->getOptionLabelUsing, ['value' => $state]);
    }

    /**
     * Mirrors Filament's Select: a filled state whose option label cannot be
     * resolved fails the `in` rule (an empty list never matches).
     *
     * @return ?array<string>
     */
    public function getInValidationRuleValues(): ?array
    {
        $values = parent::getInValidationRuleValues();

        if ($values !== null) {
            return $values;
        }

        if (!$this->getOptionLabelUsing instanceof Closure) {
            throw new LogicException("Filament failed to validate the [{$this->getStatePath()}] field's selected option because it did not have a [getOptionLabelUsing()] configuration. Please use this method to inform Filament which options are valid for this field.");
        }

        $state = $this->getState();

        if (blank($state)) {
            return null;
        }

        return blank($this->evaluate($this->getOptionLabelUsing, ['value' => $state])) ? [] : null;
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionLabelForJs(string $value): ?string
    {
        if (!$this->getOptionLabelUsing instanceof Closure) {
            return null;
        }

        $label = $this->evaluate($this->getOptionLabelUsing, ['value' => $value]);

        return blank($label) ? null : (string) $label;
    }

    /**
     * @return array{options: array<int, array{value: string|int, label: string}>, hasMore: bool}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getPageForJs(string $search, int $page): array
    {
        if (!$this->getPageUsing instanceof Closure) {
            return ['options' => [], 'hasMore' => false];
        }

        return $this->evaluate($this->getPageUsing, [
            'search'  => $search,
            'page'    => max(1, $page),
            'perPage' => $this->getPerPage(),
        ]);
    }
}
