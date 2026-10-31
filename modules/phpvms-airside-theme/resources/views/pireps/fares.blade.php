{{-- Fares partial for GET /pireps/fares (kept for old links; the PIREP form is a Livewire component now). Data: aircraft, read_only. --}}
@if ($aircraft && $aircraft->subfleet->fares->count() > 0)
    <x-ts-card :header="trans_choice('pireps.fare', 2)">
        <div class="grid gap-3">
            @foreach ($aircraft->subfleet->fares as $fare)
                <x-vms::pireps.form-field :label="$fare->name.' ('.$fare->type->getLabel().', code '.$fare->code.')'" :for="'fare_'.$fare->id" wire:key="fare-{{ $fare->id }}">
                    <input type="number" name="fare_{{ $fare->id }}" id="fare_{{ $fare->id }}" min="0" value="{{ old('fare_'.$fare->id) }}" @disabled($read_only) />
                </x-vms::pireps.form-field>
            @endforeach
        </div>
    </x-ts-card>
@endif
