<x-filament-panels::page>
  <x-filament::section>
    <x-slot name="heading">
      Update
    </x-slot>

    {{ $this->update() }}
    {{ $this->forceUpdateCheckAction() }}
    {{ $this->reseed() }}
    {{ $this->flushQueue() }}
    {{ $this->optimizeApp() }}
  </x-filament::section>

  <x-filament::section>
    <x-slot name="heading">
      CRON
    </x-slot>

    <div class="mb-4">
      A cron must be created that runs every minute calling artisan. An example is below. <a href="{{ docs_link('cron') }}" target="_blank" class="text-primary-500">See the docs</a>
    </div>

    <x-filament::input.wrapper disabled class="mb-4">
      <x-filament::input
        type="text"
        disabled
        :value="app(\App\Services\CronService::class)->getCronExecString()"
      />
    </x-filament::input.wrapper>

    <div class="mb-4">If you don't have cron access on your server, you can use a web-cron service to access this URL every minute. Keep it disabled if you're not using it. It's a unique ID that can be reset/changed if needed for security.</div>

    @php
      $cron_id = setting('cron.random_id');
      $cron_url = empty($cron_id) ? 'Not enabled' : url(route('api.maintenance.cron', $cron_id));
    @endphp

    <div class="flex items-center justify-between">
      <x-filament::input.wrapper disabled class="w-full">
        <x-filament::input
          type="text"
          disabled
          :value="$cron_url"
        />
      </x-filament::input.wrapper>

      <div class="mx-3 whitespace-nowrap">
        {{ $this->webCronEnable() }}
      </div>
      {{ $this->webCronDisable() }}
    </div>

  </x-filament::section>

  <x-filament::section>
    <x-slot name="heading">
      Reset Caches
    </x-slot>

    <div class="flex items-center justify-between">
      {{ $this->clearCaches()->label('Clear all caches')->arguments(['type' => 'all']) }}
      {{ $this->clearCaches()->label('Clear application cache')->arguments(['type' => 'application']) }}
      {{ $this->clearCaches()->label('Clear views cache')->arguments(['type' => 'views']) }}
    </div>
  </x-filament::section>
</x-filament-panels::page>
