<div>
    <x-ts-card aria-label="API access">
        <x-slot:header>
        <div class="grid gap-0.5"><h2 class="text-sm font-semibold">{{ 'API access' }}</h2><p class="text-xs font-normal text-dark-500 dark:text-dark-400">{{ 'Used by ACARS clients and API integrations. Do not share it.' }}</p></div>
    </x-slot:header>
        <div class="grid gap-4" x-data="{ revealed: false }">
            <x-ts-alert color="primary" light shadowless role="status">
                <strong>Legacy API key.</strong>
                Personal access tokens on the API connections page are preferred for new integrations.
            </x-ts-alert>

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

                <x-ts-button sm outline color="amber" type="button" data-prompt="Are you sure? This will reset your API key." data-confirm="Regenerate" data-keep="Keep" x-on:click="$tsui.interaction('dialog').wireable($wire.$id).question($escapeHtml($el.dataset.prompt)).confirm($el.dataset.confirm, 'regenerate').cancel($el.dataset.keep).send()">Regenerate API key</x-ts-button>
            </div>
        </div>
    </x-ts-card>
</div>
