<?php

declare(strict_types=1);

use App\Features\Themes\ThemeFields;
use App\Features\Themes\ThemeOptions;
use App\Livewire\Filament\GlobalSlotList;
use App\Models\GlobalSlot;
use App\Models\Permission;
use App\Models\ThemeSetting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CodeEditor;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => [
        CodeEditor::make('custom_css')->default('')->meta('emit', 'css'),
        CodeEditor::make('head_insert')->default('')->meta('emit', 'head'),
        CodeEditor::make('footer_insert')->default('')->meta('emit', 'footer'),
    ]);
    Theme::set('airside');
});

function renderHead(): string
{
    return Blade::render('<x-theme-head />');
}

function renderFooter(): string
{
    return Blade::render('<x-theme-footer />');
}

it('refuses a row with an undeclared slot and stores nothing', function (): void {
    try {
        GlobalSlot::factory()->create(['slot' => 'sidebar']);
        $this->fail('The save was not refused.');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('slot');
    }

    expect(GlobalSlot::query()->count())->toBe(0);
});

it('does not emit a disabled row but keeps it', function (): void {
    GlobalSlot::factory()->footer()->disabled()->create(['value' => '<i>off</i>']);

    expect(renderFooter())->not->toContain('<i>off</i>')
        ->and(GlobalSlot::query()->count())->toBe(1);
});

it('emits rows in their new order after a re-order', function (): void {
    $a = GlobalSlot::factory()->css()->create(['value' => '.row-a{}', 'order' => 1]);
    $b = GlobalSlot::factory()->css()->create(['value' => '.row-b{}', 'order' => 2]);

    expect(strpos(renderHead(), '.row-a'))->toBeLessThan(strpos(renderHead(), '.row-b'));

    $b->update(['order' => 0]);

    $head = renderHead();
    expect(strpos($head, '.row-b'))->toBeLessThan(strpos($head, '.row-a'));
});

it('emits a global css row after the theme css field', function (): void {
    GlobalSlot::factory()->css()->create(['value' => '.global-rule{}']);
    app(ThemeFields::class)->set('airside', 'custom_css', '.theme-rule{}');

    $head = renderHead();

    expect(strpos($head, '.theme-rule'))->toBeLessThan(strpos($head, '.global-rule'));
});

it('keeps a css row from closing the style tag', function (): void {
    GlobalSlot::factory()->css()->create(['value' => '</style><script>alert(1)</script>']);

    $head = renderHead();

    expect(substr_count(strtolower($head), '</style'))->toBe(1)
        ->and($head)->not->toContain('</style><script>')
        ->and(strpos($head, 'alert(1)'))->toBeLessThan(strpos($head, '</style>'));
});

it('shows an edited row on the next render', function (): void {
    $row = GlobalSlot::factory()->create(['value' => '<meta name="old">']);

    expect(renderHead())->toContain('<meta name="old">');

    $row->update(['value' => '<meta name="new">']);

    expect(renderHead())->toContain('<meta name="new">')->not->toContain('<meta name="old">');
});

it('shows a saved theme css field on the next render', function (): void {
    $fields = app(ThemeFields::class);
    $fields->set('airside', 'custom_css', '.first{}');

    expect(renderHead())->toContain('.first{}');

    $fields->set('airside', 'custom_css', '.second{}');

    expect(renderHead())->toContain('.second{}')->not->toContain('.first{}');

    $fields->reset('airside', 'custom_css');

    expect(renderHead())->not->toContain('.second{}');
});

it('shows a theme setting changed directly on the model on the next render', function (): void {
    app(ThemeFields::class)->set('airside', 'custom_css', '.first{}');

    expect(renderHead())->toContain('.first{}');

    ThemeSetting::query()->where('name', 'custom_css')->firstOrFail()->update(['content' => '.direct{}']);

    expect(renderHead())->toContain('.direct{}')->not->toContain('.first{}');
});

it('puts the theme footer field before the global footer row', function (): void {
    GlobalSlot::factory()->footer()->create(['value' => '<script>global()</script>']);
    app(ThemeFields::class)->set('airside', 'footer_insert', '<script>theme()</script>');

    $footer = renderFooter();

    expect(strpos($footer, 'theme()'))->toBeLessThan(strpos($footer, 'global()'));
});

it('refuses reorder, edit and toggle on a read-only list', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());

    $a = GlobalSlot::factory()->css()->create(['name' => 'A', 'order' => 1]);
    $b = GlobalSlot::factory()->css()->create(['name' => 'B', 'order' => 2]);

    livewire(GlobalSlotList::class, ['key' => 'global', 'canEdit' => false])
        ->call('reorderTable', [$b->id, $a->id])
        ->assertActionHidden(TestAction::make('edit')->table($a))
        ->call('mountAction', 'edit', [], ['table' => true, 'recordKey' => (string) $a->id])
        ->call('callMountedAction')
        ->call('updateTableColumnState', 'enabled', (string) $a->id, false);

    expect($a->fresh()->order)->toBe(1)
        ->and($b->fresh()->order)->toBe(2)
        ->and($a->fresh()->name)->toBe('A')
        ->and($a->fresh()->enabled)->toBeTrue();
});

it('reorders and creates rows on an editable list', function (): void {
    Filament::setCurrentPanel('admin');

    $user = User::factory()->create();

    foreach (['view:global-slot', 'edit:global-slot'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    $this->actingAs($user->fresh());

    $a = GlobalSlot::factory()->css()->create(['order' => 1]);
    $b = GlobalSlot::factory()->css()->create(['order' => 2]);

    livewire(GlobalSlotList::class, ['key' => 'global'])
        ->call('reorderTable', [$b->id, $a->id])
        ->assertHasNoErrors();

    expect($b->fresh()->order)->toBeLessThan($a->fresh()->order);
});
