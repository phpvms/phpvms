<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;

/**
 * Puts a form's action row in the footer of its last section, aligned right,
 * with the primary action furthest right.
 *
 * The ordering half lives in [[FormActionIcons]], which this trait pulls in so
 * a page gets both from one `use`. Only the placement below needs a resource
 * page: it calls `parent::getFormContentComponent()` and
 * `getSubmitFormLivewireMethodName()`, which exist on `EditRecord` and
 * `CreateRecord` but not on a plain `Filament\Pages\Page`. A plain page wanting
 * only the button order should use `FormActionIcons` directly, as Settings does.
 *
 * The placement: by default the actions render in the *form's* footer, which
 * sits outside the section card and leaves the buttons floating under it.
 * `getFormContentComponent()` below moves them into the section's own footer
 * instead, so they read as belonging to the thing they save.
 *
 * The *last* section, because a form is not always one section -- `RoleForm`
 * has three, `AwardForm` and `SubfleetForm` two. Last means bottom of the form
 * either way, which is where a submit row belongs. A form with no section at
 * all falls through to Filament's own layout.
 *
 * @see EditRecord::getFormActions()
 * @see EditRecord::getFormContentComponent()
 */
trait ReversePrimaryButtons
{
    use FormActionIcons;

    public function getFormContentComponent(): Component
    {
        $section = $this->lastFormSection();

        if (!$section instanceof Section) {
            return parent::getFormContentComponent();
        }

        // Two slots rather than one row: anything the schema already put in
        // this footer (a "run test" button, say) stays left, and the submit
        // pair sits right. `footerActionsAlignment()` cannot express that --
        // it aligns one flat list, so a third button would strand `Cancel` in
        // the middle.
        //
        // Already reversed: pages apply reversePrimaryButtons() in their own
        // getFormActions(), so this must not flip them a second time.
        //
        // `Alignment::Between` on the Flex -- `.fi-sc-flex.fi-align-between`
        // is `justify-content: space-between`, which with two slots pins one
        // to each edge.
        //
        // `grow(false)` on both, because a Flex child grows by default
        // (`Component::canGrow(default: true)`) and `.fi-sc-flex >
        // .fi-growable` is `flex: 1; width: 100%`. One growing slot eats the
        // row and squeezes the other to its minimum width, which wraps its
        // buttons onto separate lines. Nothing growing leaves space-between
        // free to do the positioning.
        //
        // Note the submit pair carries no alignment: `.fi-ac.fi-align-end` is
        // `flex-direction: row-reverse`, which would undo
        // `reversePrimaryButtons()` and put the primary action back on the left.
        $section->footer([
            Flex::make([
                Actions::make($section->getFooterActions())->grow(false),
                Actions::make($this->getFormActions())->grow(false),
            ])
                // And the Flex itself must grow, for the opposite reason: the
                // section footer is an inline container, where
                // `Component::canGrow()` defaults to *false*
                // (`Component.php:183`). Without this the row is only as wide
                // as its buttons and there is no gap for them to spread into.
                ->grow()
                ->alignment(Alignment::Between),
        ]);

        // The form keeps its submit handler but loses the separate actions
        // footer, which would otherwise render the same buttons twice.
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName());
    }

    /** The last top-level section of the form schema, if it has one. */
    protected function lastFormSection(): ?Section
    {
        $sections = array_filter(
            $this->getSchema('form')?->getComponents() ?? [],
            fn (Component $component): bool => $component instanceof Section,
        );

        return $sections === [] ? null : end($sections);
    }
}
