<header class="relative isolate mb-6 pt-6 pb-10">
    {{-- Dot-grid background --}}
    <div
        class="absolute inset-x-0 inset-y-0 -z-10 opacity-50"
        style="
            background-image: radial-gradient(circle, var(--color-primary-500) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
        "
        aria-hidden="true"
    ></div>

    @if ($eyebrow)
        <span
            class="mb-[18.75px] inline-flex items-center gap-[6px] rounded-full border border-primary-500/25 bg-primary-500/10 px-[9.75px] py-[4.5px] text-[11.7px] font-medium tracking-[0.02em] text-primary-500 dark:text-primary-400"
        >
            {{ $eyebrow }}
        </span>
    @endif

    <h1
        class="font-display mb-[15px] text-[clamp(30px,4vw,42px)] font-semibold leading-[1.1] tracking-[-0.02em] text-[#1c1e21] dark:text-[#e3e3e3]"
    >
        {{ $title }}
    </h1>

    @if ($subtitle)
        <p class="mb-[30px] max-w-[640px] font-sans text-[16.5px] leading-[1.55] text-[#606770] dark:text-[#8a8a8a]">
            {{ $subtitle }}
        </p>
    @endif
</header>
