<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
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

    /** @var array<int, Component>|null */
    private ?array $preservedFooterComponents = null;

    public function getFormContentComponent(): Component
    {
        $section = $this->lastFormSection();

        if (!$section instanceof Section) {
            return parent::getFormContentComponent();
        }

        // The submit pair always sits right, as [Cancel] [Save changes].
        //
        // Two slots when the schema already put actions in this footer (a "run
        // test" button, say): that one stays left and the submit pair keeps the
        // right edge. `footerActionsAlignment()` cannot express that -- it
        // aligns one flat list, so a third button would strand `Cancel` in the
        // middle.
        //
        // One slot otherwise, and the alignment has to change with it.
        // `Alignment::Between` is `justify-content: space-between`, which pins
        // one child to each edge -- but an `Actions::make([])` renders no
        // element at all, so the "two slot" row collapses to a single child and
        // space-between lays it out at flex-start. That is what put Cancel and
        // Save on the left of every page whose form has no footer actions.
        // `Alignment::End` on the Flex is a plain `justify-content: flex-end`
        // (`filament/schemas/resources/css/components/flex.css:13-16`).
        //
        // Already reversed: pages apply reversePrimaryButtons() in their own
        // getFormActions(), so this must not flip them a second time.
        //
        // The alignment goes on the Flex, never on the submit pair itself:
        // `.fi-ac.fi-align-end` is `flex-direction: row-reverse`, which would
        // undo `reversePrimaryButtons()` and put the primary action back on
        // the left.
        //
        // `grow(false)` on the slots, because a Flex child grows by default
        // (`Component::canGrow(default: true)`) and `.fi-sc-flex >
        // .fi-growable` is `flex: 1; width: 100%`. One growing slot eats the
        // row and squeezes the other to its minimum width, which wraps its
        // buttons onto separate lines. Nothing growing leaves the justification
        // free to do the positioning.
        $footerActions = $section->getFooterActions();

        $slots = $footerActions === []
            ? [Actions::make($this->getFormActions())->grow(false)]
            : [
                Actions::make($footerActions)->grow(false),
                Actions::make($this->getFormActions())->grow(false),
            ];

        $submitRow = Flex::make($slots)
            // And the Flex itself must grow, for the opposite reason: the
            // section footer is an inline container, where
            // `Component::canGrow()` defaults to *false*
            // (`Component.php:183`). Without this the row is only as wide
            // as its buttons and there is no gap to push them across.
            ->grow()
            ->alignment($footerActions === [] ? Alignment::End : Alignment::Between);

        // A schema that put its own components in this footer -- FlightForm's
        // `enabled` toggle -- keeps them, on their own row above the submit
        // row. `Section::footer()` is `childComponents(..., FOOTER_SCHEMA_KEY)`,
        // which *replaces* the footer schema, so writing the submit row
        // straight into it silently dropped them: the flight edit page had no
        // way to toggle a flight off at all.
        //
        // Grid rather than a bare list, because the footer lays its children
        // out inline; a one-column Grid stacks them. The rule between the rows
        // is `.fi-section-footer .fi-grid > * + *` in the admin theme.
        //
        // The Grid takes the `grow()` the submit row needed when it sat in the
        // footer directly -- it is the footer's child now, and a child of the
        // inline container is shrink-to-fit without `.fi-growable`. Without it
        // the stack is only as wide as the toggle's helper text, which both
        // truncates the rule and strands the buttons back at the left.
        $rows = $this->preservedFooterComponents($section);

        $section->footer(
            $rows === []
                ? [$submitRow]
                : [Grid::make(1)->schema([...$rows, $submitRow])->grow()],
        );

        // The form keeps its submit handler but loses the separate actions
        // footer, which would otherwise render the same buttons twice.
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName());
    }

    /**
     * The footer components the form schema itself declared, read once before
     * this trait overwrites the footer.
     *
     * Memoised because `getFormContentComponent()` runs more than once per
     * render: on the second pass the footer already holds what was built
     * below, and reading it back would stack a copy of the submit row above
     * itself.
     *
     * Anything action-shaped is excluded. A section with no custom footer
     * still has one -- `Section::setUp()` defaults it to
     * `Schema::start($c->getFooterActions())` -- and those actions already have
     * their own slot in the submit row, so keeping them here would render
     * AwardForm's "Run test" twice. That default schema holds raw
     * `Filament\Actions\Action` objects rather than `Component`s, hence the
     * `instanceof Component` half; the `Actions` half drops the schema
     * component a form could wrap its own actions in.
     *
     * @return array<int, Component>
     */
    protected function preservedFooterComponents(Section $section): array
    {
        return $this->preservedFooterComponents ??= array_values(array_filter(
            $section->getChildSchema(Section::FOOTER_SCHEMA_KEY)?->getComponents() ?? [],
            fn (mixed $component): bool => $component instanceof Component
                && !$component instanceof Actions,
        ));
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
