<div>
    <x-airside::card title="API access" description="Used by ACARS clients and API integrations. Do not share it." aria-label="API access">
        <div class="grid gap-4" x-data="{ revealed: false }">
            <x-airside::alert>
                <strong>Legacy API key.</strong>
                Personal access tokens on the API connections page are preferred for new integrations.
            </x-airside::alert>

            <div class="flex flex-wrap items-center gap-3">
                @if ($this->apiKey)
                    <code class="break-all rounded-md px-3 py-1.5 font-mono text-[13px]" x-show="revealed" x-cloak>{{ $this->apiKey }}</code>
                @endif
                <code class="rounded-md px-3 py-1.5 font-mono text-[13px]" @if ($this->apiKey) x-show="! revealed" @endif>••••••••••••••••••••</code>

                <x-ts-button sm light type="button" :disabled="! $this->apiKey" x-on:click="revealed = ! revealed">
                    <span x-text="revealed ? 'Hide' : 'Show'">Show</span>
                </x-ts-button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ts-button sm outline type="button" x-on:click="$dispatch('api-connections.open')">API connections</x-ts-button>

                <x-airside::confirm prompt="Are you sure? This will reset your API key." confirm-label="Regenerate" color="amber" wire:click="regenerate">
                    <x-slot:trigger>
                        <x-ts-button sm outline color="amber" type="button">Regenerate API key</x-ts-button>
                    </x-slot:trigger>
                </x-airside::confirm>
            </div>
        </div>
    </x-airside::card>
</div>
