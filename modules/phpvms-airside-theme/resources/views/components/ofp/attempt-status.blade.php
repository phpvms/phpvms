{{-- The simbrief-attempt island's status blocks: waiting for the popup, polling, error. It shows one by [data-show]. --}}
<div data-show="waiting-for-popup" hidden>
    <x-ts-alert color="primary" light shadowless role="status">SimBrief is open. phpVMS is checking for the completed briefing.</x-ts-alert>
</div>
<div data-show="polling" hidden>
    <div data-role="polling" class="inline-flex min-w-0 items-center" role="status" aria-live="polite" aria-label="Checking for the completed OFP (0/60)"><x-ts-spinner sm text="Checking for the completed OFP (0/60)" /></div>
</div>
<div data-show="error" hidden>
    <x-ts-alert color="red" light shadowless role="alert"><span data-role="failure"></span></x-ts-alert>
</div>
