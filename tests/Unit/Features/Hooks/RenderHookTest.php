<?php

declare(strict_types=1);

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\FilterHook;
use App\Features\Hooks\HookDispatcher;
use App\Features\Hooks\RenderHook;
use Illuminate\Support\Facades\Log;

final class TestRender extends RenderHook {}

final class PlainList extends FilterHook
{
    public function accepts(mixed $value): bool
    {
        return is_array($value);
    }
}

final class RenderNoop
{
    public function handle(): string
    {
        return 'ignored';
    }
}

final class RenderAddsOne
{
    public function handle(TestRender $hook): void
    {
        $hook->add(Fill::html('one'));
    }
}

final class RenderAddsTwo
{
    public function handle(TestRender $hook): void
    {
        $hook->add(Fill::html('two'));
    }
}

final class RenderRemovesOne
{
    public function handle(TestRender $hook): void
    {
        $hook->remove(fn (Fill $fill): bool => $fill->html === 'one');
    }
}

final class RenderReplaces
{
    public function handle(TestRender $hook): void
    {
        $hook->replace(Fill::html('only'));
    }
}

final class RenderSetsJunk
{
    public function handle(TestRender $hook): void
    {
        $hook->value = 'junk';
    }
}

final class RenderAddsNonFill
{
    public function handle(TestRender $hook): void
    {
        $property = new ReflectionProperty($hook, 'value');
        $property->setValue($hook, [...$hook->value, 'not a fill']);
    }
}

final class PlainReturns
{
    public function handle(PlainList $hook): array
    {
        return [...$hook->value, 'x'];
    }
}

function htmls(array $fills): array
{
    return array_map(fn (Fill $fill): ?string => $fill->html, $fills);
}

beforeEach(function (): void {
    $this->hooks = new HookDispatcher(app());
});

it('adds, replaces and removes fills', function (): void {
    $hook = new TestRender();
    $hook->add(Fill::html('a'), Fill::html('b'));
    $hook->add(Fill::html('c'));

    expect(htmls($hook->value))->toBe(['a', 'b', 'c']);

    $hook->remove(fn (Fill $fill): bool => $fill->html !== 'b');
    expect(htmls($hook->value))->toBe(['b']);

    $hook->replace(Fill::html('x'), Fill::html('y'));
    expect(htmls($hook->value))->toBe(['x', 'y']);
});

it('keeps the list when a listener changes nothing, whatever it returns', function (): void {
    $this->hooks->listen(TestRender::class, RenderNoop::class);

    expect($this->hooks->filter(new TestRender()))->toBe([]);
});

it('chains mutations from several listeners', function (): void {
    $this->hooks->listen(TestRender::class, RenderAddsOne::class);
    $this->hooks->listen(TestRender::class, RenderAddsTwo::class);
    $this->hooks->listen(TestRender::class, RenderRemovesOne::class);

    expect(htmls($this->hooks->filter(new TestRender())))->toBe(['two']);
});

it('lets a later listener replace what an earlier one added', function (): void {
    $this->hooks->listen(TestRender::class, RenderAddsOne::class);
    $this->hooks->listen(TestRender::class, RenderReplaces::class);

    expect(htmls($this->hooks->filter(new TestRender())))->toBe(['only']);
});

it('still uses the return value of a plain filter hook', function (): void {
    $this->hooks->listen(PlainList::class, PlainReturns::class);

    expect($this->hooks->filter(new PlainList([])))->toBe(['x']);
});

it('drops a non-list state with a warning and keeps the previous list', function (string $listener): void {
    Log::spy();
    $this->hooks->listen(TestRender::class, RenderAddsOne::class);
    $this->hooks->listen(TestRender::class, $listener);
    $this->hooks->listen(TestRender::class, RenderAddsTwo::class);

    expect(htmls($this->hooks->filter(new TestRender())))->toBe(['one', 'two']);
    Log::shouldHaveReceived('log')->withArgs(fn (string $level): bool => $level === 'warning')->once();
})->with([RenderSetsJunk::class, RenderAddsNonFill::class]);

it('records a render hook in Hooks::fake() and returns its list unchanged', function (): void {
    $fake = Hooks::fake();
    $hook = new TestRender();
    $hook->add(Fill::html('a'));

    expect(htmls($fake->filter($hook)))->toBe(['a']);
    $fake->assertDispatched(TestRender::class);
});
