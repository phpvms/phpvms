@props(['code', 'title', 'message' => null])

{{-- The body of an error page: status code, title and message; the actions go in the default slot. Props: code, title, message. --}}
<div {{ $attributes->class('vms-error mx-auto grid max-w-lg justify-items-center gap-3 px-4 py-16 text-center') }}>
    <p class="font-mono text-6xl font-bold text-dark-300 dark:text-dark-600">{{ $code }}</p>
    <h1 class="text-xl font-semibold">{{ $title }}</h1>
    @if ($message)
        <p class="text-sm text-dark-500 dark:text-dark-400">{{ $message }}</p>
    @endif
    <div class="mt-2 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
</div>
