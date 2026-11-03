@props(['shell', 'topbar' => false])

{{--
  The account menu: account entries and the theme switch. The trigger sits in the
  sidebar footer, or at the right end of the top bar (`topbar`); the entries are the same.
--}}
@php($user = $shell->user())

<x-ts-dropdown :position="$topbar ? 'bottom-end' : 'top-start'" width="sm" scope="airside.pilot-menu">
    <x-slot:action>
        @if ($topbar)
            <button type="button" x-on:click="show = !show" class="vms-shell-account ml-auto flex w-fit cursor-pointer items-center gap-3 rounded-full p-1.5 pr-3 text-left hover:bg-(--vms-surface)/60" aria-label="{{ $user ? 'Account menu for '.$user['name'] : 'Account menu' }}">
                <x-ts-avatar :image="$user['avatar'] ?? null" :text="$user ? mb_strtoupper(mb_substr($user['name'], 0, 2)) : '?'" sm />
                <span class="hidden min-w-0 leading-tight sm:block">
                    <span class="block truncate text-sm font-semibold text-(--vms-ink)">{{ $user['name'] ?? __('ui.role_pilot') }}</span>
                    @if ($user)
                        <span class="block truncate font-mono text-xs text-(--vms-ink-3)">{{ collect([$user['ident'], $user['callsign']])->filter()->implode(' · ') }}</span>
                    @endif
                </span>
            </button>
        @else
            {{-- On the collapsed rail only the avatar shows; the store is TallStackUI's sidebar state. --}}
            <button type="button" x-on:click="show = !show" class="vms-shell-account flex w-full cursor-pointer items-center gap-3 rounded-full p-2 text-left hover:bg-(--vms-surface)/60" x-bind:class="{ 'justify-center': $store['tsui.side-bar'].collapsed }" aria-label="{{ $user ? 'Account menu for '.$user['name'] : 'Account menu' }}">
                <x-ts-avatar :image="$user['avatar'] ?? null" :text="$user ? mb_strtoupper(mb_substr($user['name'], 0, 2)) : '?'" sm />
                <span class="min-w-0 flex-1" x-show="! $store['tsui.side-bar'].collapsed">
                    <span class="block truncate text-sm font-semibold text-(--vms-ink)">{{ $user['name'] ?? __('ui.role_pilot') }}</span>
                    @if ($user)
                        <span class="block truncate font-mono text-xs text-(--vms-ink-3)">{{ collect([$user['ident'], $user['callsign']])->filter()->implode(' · ') }}</span>
                    @endif
                </span>
            </button>
        @endif
    </x-slot:action>

    <x-slot:header>
        <div class="px-2 py-1">
            <p class="mb-1 text-xs text-dark-500 dark:text-dark-400">{{ __('ui.theme') }}</p>
            <x-ts-theme-switch block xs />
        </div>
    </x-slot:header>

    @if ($user)
        @if ($shell->canAccessAdmin())
            {{-- A different app: a full load, not wire:navigate. --}}
            <x-ts-dropdown.items :text="__('ui.go_to_admin')" icon="phosphor.gear.light" :href="\Filament\Facades\Filament::getPanel('admin')->getUrl()" />
        @endif
        <x-ts-dropdown.items :text="__('common.profile')" icon="phosphor.user.light" :href="url('/profile')" navigate />
        <x-ts-dropdown.items :text="__('ui.sign_out')" icon="phosphor.sign-out.light" :href="url('/logout')" separator />
    @else
        <x-ts-dropdown.items :text="__('ui.sign_in')" icon="phosphor.sign-in.light" :href="url('/login')" />
    @endif
</x-ts-dropdown>
