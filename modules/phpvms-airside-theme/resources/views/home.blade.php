<x-vms::layouts.landing :title="__('home.welcome.title')">
    <section class="vms-landing-hero" aria-labelledby="hero-title">
        <x-vms::sky />
        <div class="vms-landing-hero-body">
            <p class="vms-landing-eyebrow">Now hiring pilots</p>
            <h1 id="hero-title" class="vms-landing-title">Your next flight<br><span>starts here.</span></h1>
            <p class="vms-landing-lead">A virtual airline with a real schedule, a growing fleet and a crew to fly with.</p>
            <div class="vms-landing-actions">
                <a href="{{ url('/register') }}" class="vms-pill vms-pill--dark vms-pill--lg">Join the crew</a>
                <a href="{{ url('/flights') }}" class="vms-pill vms-pill--light vms-pill--lg">Explore our routes</a>
            </div>
        </div>
    </section>

    <section class="vms-landing-stories" aria-label="Why fly with us">
        <article class="vms-story">
            <p class="vms-story-label">01 / Dispatch</p>
            <h3>Flight planning, built in.</h3>
            <p>Select routes, prep your flight, and follow the briefing to the runway.</p>
        </article>
        <article class="vms-story">
            <p class="vms-story-label">02 / Tracking</p>
            <h3>Every mile matters.</h3>
            <p>Track flights and build your pilot record through ACARS.</p>
        </article>
        <article class="vms-story">
            <p class="vms-story-label">03 / Community</p>
            <h3>Fly with a crew.</h3>
            <p>Group flights, events, and a worldwide pilot community.</p>
        </article>
    </section>

    <section class="vms-landing-news" aria-labelledby="news-title">
        <div class="vms-section-head">
            <div>
                <p class="vms-section-eyebrow">Announcements</p>
                <h2 id="news-title" class="vms-section-title">Latest news</h2>
            </div>
        </div>
        <div class="vms-news-grid">
            <article class="vms-news">
                <time datetime="2026-10-28">28 October 2026</time>
                <h4>Summer schedule is live</h4>
                <p>Forty new routes across Europe and the Atlantic, plus two new A350 bases.</p>
            </article>
            <article class="vms-news">
                <time datetime="2026-10-14">14 October 2026</time>
                <h4>Around the Pacific tour</h4>
                <p>A twelve-leg journey from Honolulu to Sydney. Bid the whole tour in one go.</p>
            </article>
            <article class="vms-news">
                <time datetime="2026-10-02">2 October 2026</time>
                <h4>Welcome to our new pilots</h4>
                <p>Thirty-two pilots joined the crew last month. Say hello on Discord.</p>
            </article>
        </div>
    </section>
</x-vms::layouts.landing>
