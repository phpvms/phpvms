<?php

declare(strict_types=1);

namespace Tests\Unit\Features\Hooks;

use App\Features\Hooks\Attributes\HookPriority;
use App\Features\Hooks\Exceptions\HookDenied;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\FilterHook;
use App\Features\Hooks\GateHook;
use App\Features\Hooks\HookDispatcher;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

final class ListFilter extends FilterHook
{
    public function accepts(mixed $value): bool
    {
        return is_array($value);
    }
}

final class ActionGate extends GateHook {}

final class ClosedGate extends GateHook
{
    protected bool $failClosed = true;
}

final class CallLog
{
    /** @var list<string> */
    public static array $calls = [];
}

#[HookPriority(200)]
final class AddsA
{
    public function handle(ListFilter $hook): array
    {
        CallLog::$calls[] = 'A';

        return [...$hook->value, 'a'];
    }
}

#[HookPriority(50)]
final class AddsB
{
    public function handle(ListFilter $hook): array
    {
        CallLog::$calls[] = 'B';

        return [...$hook->value, 'b'];
    }
}

final class AddsC
{
    public function handle(ListFilter $hook): array
    {
        CallLog::$calls[] = 'C';

        return [...$hook->value, 'c'];
    }
}

final class Throws
{
    public function handle(): never
    {
        throw new RuntimeException('boom');
    }
}

final class ReturnsString
{
    public function handle(): string
    {
        return 'nope';
    }
}

final class Allows
{
    public function handle(): void
    {
        CallLog::$calls[] = 'allow';
    }
}

final class Denies
{
    public function __invoke(ActionGate $gate): void
    {
        CallLog::$calls[] = 'deny';
        $gate->deny('Complete your checkride first');
    }
}

final class DeniesThenThrows
{
    public function handle(ActionGate|ClosedGate $gate): never
    {
        $gate->deny('Complete your checkride first');

        throw new RuntimeException('boom');
    }
}

beforeEach(function (): void {
    CallLog::$calls = [];
    $this->hooks = new HookDispatcher(app());
});

it('runs listeners in ascending priority', function (): void {
    $this->hooks->listen(ListFilter::class, AddsA::class);
    $this->hooks->listen(ListFilter::class, AddsB::class);

    $this->hooks->filter(new ListFilter([]));

    expect(CallLog::$calls)->toBe(['B', 'A']);
});

it('runs equal priorities in registration order', function (): void {
    $this->hooks->listen(ListFilter::class, AddsC::class);
    $this->hooks->listen(ListFilter::class, AddsC::class);
    $this->hooks->listen(ListFilter::class, AddsB::class);

    expect(array_column($this->hooks->listenersFor(ListFilter::class), 'listener'))
        ->toBe([AddsB::class, AddsC::class, AddsC::class]);
});

it('chains filter values', function (): void {
    $this->hooks->listen(ListFilter::class, AddsB::class);
    $this->hooks->listen(ListFilter::class, AddsC::class);

    expect($this->hooks->filter(new ListFilter(['x'])))->toBe(['x', 'b', 'c']);
});

it('returns the original value with no listeners', function (): void {
    expect($this->hooks->filter(new ListFilter(['x'])))->toBe(['x']);
});

it('continues the chain past a throwing filter listener', function (): void {
    Log::spy();
    $this->hooks->listen(ListFilter::class, AddsB::class);
    $this->hooks->listen(ListFilter::class, Throws::class);
    $this->hooks->listen(ListFilter::class, AddsC::class);

    expect($this->hooks->filter(new ListFilter([])))->toBe(['b', 'c']);
    Log::shouldHaveReceived('log')->withArgs(fn (string $level): bool => $level === 'warning')->once();
});

it('discards a filter result of the wrong type', function (): void {
    Log::spy();
    $this->hooks->listen(ListFilter::class, ReturnsString::class);
    $this->hooks->listen(ListFilter::class, AddsC::class);

    expect($this->hooks->filter(new ListFilter([])))->toBe(['c']);
    Log::shouldHaveReceived('log')->once();
});

it('stops at the first denial and reports its message', function (): void {
    $this->hooks->listen(ActionGate::class, Denies::class);
    $this->hooks->listen(ActionGate::class, Allows::class);

    expect(fn () => $this->hooks->gate(new ActionGate()))
        ->toThrow(HookDenied::class, 'Complete your checkride first');
    expect(CallLog::$calls)->toBe(['deny']);
});

it('composes gates from several listeners', function (): void {
    $this->hooks->listen(ActionGate::class, Allows::class);
    $this->hooks->listen(ActionGate::class, Denies::class);

    expect(fn () => $this->hooks->gate(new ActionGate()))->toThrow(HookDenied::class);
    expect(CallLog::$calls)->toBe(['allow', 'deny']);
});

it('allows when every gate listener allows', function (): void {
    $this->hooks->listen(ActionGate::class, Allows::class);

    $this->hooks->gate(new ActionGate());

    expect(CallLog::$calls)->toBe(['allow']);
});

it('allows past a throwing listener on a fail-open gate', function (): void {
    Log::spy();
    $this->hooks->listen(ActionGate::class, Throws::class);

    $this->hooks->gate(new ActionGate());

    Log::shouldHaveReceived('log')->withArgs(fn (string $level): bool => $level === 'error')->once();
});

it('denies when a listener throws on a fail-closed gate', function (): void {
    Log::spy();
    $this->hooks->listen(ClosedGate::class, Throws::class);

    expect(fn () => $this->hooks->gate(new ClosedGate()))->toThrow(HookDenied::class);
});

it('renders a denial as a 422 hook-denied problem', function (): void {
    $denied = new HookDenied('No', ActionGate::name());

    expect($denied->getStatusCode())->toBe(422)
        ->and($denied->getJson()['type'])->toEndWith('/hook-denied')
        ->and($denied->getJson()['hook'])->toBe('action-gate');
});

it('refuses a listener for a class that is not a hook point', function (): void {
    expect(fn () => $this->hooks->listen(CallLog::class, Allows::class, 'acme'))
        ->toThrow(InvalidArgumentException::class, 'acme');
});

it('fakes a denial and records dispatches', function (): void {
    $fake = Hooks::fake()->deny(ActionGate::class, 'Faked');

    expect(fn () => Hooks::gate(new ActionGate()))->toThrow(HookDenied::class, 'Faked');
    $fake->assertDispatched(ActionGate::class);
    $fake->assertNotDispatched(ListFilter::class);
});

it('keeps a denial from a listener that throws after denying', function (): void {
    Log::spy();
    $this->hooks->listen(ActionGate::class, DeniesThenThrows::class);

    expect(fn () => $this->hooks->gate(new ActionGate()))
        ->toThrow(HookDenied::class, 'Complete your checkride first');
});

it('reports the listener denial, not the generic one, on a fail-closed gate', function (): void {
    Log::spy();
    $this->hooks->listen(ClosedGate::class, DeniesThenThrows::class);

    expect(fn () => $this->hooks->gate(new ClosedGate()))
        ->toThrow(HookDenied::class, 'Complete your checkride first');
});
