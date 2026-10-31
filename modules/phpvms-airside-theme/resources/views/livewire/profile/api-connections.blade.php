<div>
    <x-airside::drawer name="api-connections" wire:model="open" title="API connections" description="Applications and tokens that can access the API on your behalf.">
        @if ($open)
            <div class="grid gap-5">
                @if ($plainTextToken)
                    <x-airside::alert kind="success" data-role="plain-text-token">
                        <p class="font-semibold">Copy this token now</p>
                        <p>It is shown once and cannot be retrieved again.</p>
                        <code class="mt-1 block break-all font-mono text-xs">{{ $plainTextToken }}</code>
                    </x-airside::alert>
                @endif

                <section class="grid gap-3" aria-label="Create a personal access token">
                    <h3 class="text-sm font-semibold">Personal access tokens</h3>
                    <p class="text-[13px]">Create a token to use the API from your own scripts or ACARS client. Choose only the scopes you need.</p>

                    <x-airside::profile-field label="Token name" for="api_token_name" error="name" required>
                        <input type="text" id="api_token_name" placeholder="My ACARS client" wire:model="name" />
                    </x-airside::profile-field>

                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-[13px] font-medium">Select scopes</legend>
                        @foreach ($this->connections?->scopes ?? [] as $scope)
                            <label class="flex items-start gap-2" wire:key="scope-{{ $scope->value }}">
                                <input type="checkbox" class="mt-0.5" value="{{ $scope->value }}" wire:model="scopes" />
                                <span class="grid gap-0.5">
                                    <span class="font-mono text-[13px]">{{ $scope->value }}</span>
                                    <span class="text-xs">{{ $scope->label }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div>
                        <x-ts-button sm type="button" wire:click="createToken" wire:loading.attr="disabled" wire:target="createToken">Create token</x-ts-button>
                    </div>
                </section>

                <section class="grid gap-3 pt-4" aria-label="Active tokens">
                    <h3 class="text-sm font-semibold">Active tokens</h3>

                    @forelse ($this->connections?->personalTokens ?? [] as $token)
                        <div class="flex items-center justify-between gap-3" wire:key="token-{{ $token->id }}">
                            <div class="grid min-w-0 gap-0.5">
                                <span class="text-[13px] font-medium">{{ $token->name }}</span>
                                <span class="break-words text-xs">{{ implode(', ', $token->scopes) ?: 'No scopes' }}</span>
                            </div>
                            <x-airside::confirm :prompt="'Revoke '.$token->name.'? Anything using it stops working.'" confirm-label="Revoke" wire:click="revokeToken('{{ $token->id }}')">
                                <x-slot:trigger>
                                    <x-ts-button sm outline color="red" type="button">Revoke</x-ts-button>
                                </x-slot:trigger>
                            </x-airside::confirm>
                        </div>
                    @empty
                        <p class="text-[13px]">No personal tokens yet.</p>
                    @endforelse
                </section>

                <section class="grid gap-3 pt-4" aria-label="Authorized applications">
                    <h3 class="text-sm font-semibold">Authorized applications</h3>

                    @forelse ($this->connections?->authorizedApps ?? [] as $app)
                        <div class="flex items-center justify-between gap-3" wire:key="app-{{ $app->clientId }}">
                            <div class="grid min-w-0 gap-0.5">
                                <span class="text-[13px] font-medium">{{ $app->name }}</span>
                                <span class="break-words text-xs">{{ implode(', ', $app->scopes) ?: 'No scopes' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ts-badge color="slate" light :text="$app->tokenCount.' '.($app->tokenCount === 1 ? 'token' : 'tokens')" />
                                <x-airside::confirm :prompt="'Revoke access for '.$app->name.'?'" confirm-label="Revoke" wire:click="revokeApp('{{ $app->clientId }}')">
                                    <x-slot:trigger>
                                        <x-ts-button sm outline color="red" type="button">Revoke</x-ts-button>
                                    </x-slot:trigger>
                                </x-airside::confirm>
                            </div>
                        </div>
                    @empty
                        <p class="text-[13px]">No applications have been authorized.</p>
                    @endforelse
                </section>
            </div>
        @endif
    </x-airside::drawer>
</div>
