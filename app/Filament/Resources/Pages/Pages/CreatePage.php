<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Enums\NavigationTarget;
use App\Enums\PageType;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Pages\PageResource;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Services\PageService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Override;

class CreatePage extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = PageResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    /**
     * Losing a race for a path surfaces as the field error, not a 500.
     *
     * @param array<string, mixed> $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw app(PageService::class)->toPathValidationException($uniqueConstraintViolationException, 'data.path');
        }
    }

    /**
     * Offer the new page its menu item in the same flow. The two menu fields are
     * not page columns, so they are read from the raw form state.
     */
    protected function afterCreate(): void
    {
        /** @var Page $page */
        $page = $this->record;

        /** @var array{add_to_menu?: bool, menu_label?: string|null} $state */
        $state = $this->form->getRawState();

        if (!($state['add_to_menu'] ?? false) || $page->type !== PageType::PAGE) {
            return;
        }

        $label = filled($state['menu_label'] ?? null) ? (string) $state['menu_label'] : $page->name;

        NavigationItem::create([
            'name'         => $label,
            'label'        => $label,
            'parent_id'    => null,
            'sort'         => ((int) NavigationItem::query()->whereNull('parent_id')->max('sort')) + 1,
            'target_type'  => NavigationTarget::PAGE,
            'target_value' => (string) $page->id,
            'new_window'   => false,
            'public'       => (bool) $page->public,
            'visible'      => true,
        ]);
    }
}
