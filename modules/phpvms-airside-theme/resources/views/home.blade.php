<x-vms::layouts.landing :title="__('home.welcome.title')">
    <section class="vms-landing-hero" aria-labelledby="hero-title">
        <svg viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs>
                <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9ccaf3"/><stop offset=".55" stop-color="#cde4fa"/><stop offset="1" stop-color="#eef5fd"/></linearGradient>
                <filter id="clouds" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
                    <feTurbulence type="fractalNoise" baseFrequency="0.0036 0.0062" numOctaves="6" seed="23" result="raw"/>
                    <feGaussianBlur in="raw" stdDeviation="4" result="n"/>
                    <feColorMatrix in="n" type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 5 -2.45" result="a"/>
                    <feDiffuseLighting in="n" surfaceScale="4" diffuseConstant="1.1" lighting-color="#ffffff" result="lit"><feDistantLight azimuth="240" elevation="62"/></feDiffuseLighting>
                    <feColorMatrix in="lit" type="matrix" values=".8 0 0 0 .22  0 .84 0 0 .19  0 0 .9 0 .14  0 0 0 1 0" result="tint"/>
                    <feComposite in="tint" in2="a" operator="in"/>
                </filter>
                <filter id="wisps" x="0" y="0" width="100%" height="100%">
                    <feTurbulence type="fractalNoise" baseFrequency="0.0014 0.010" numOctaves="4" seed="4"/>
                    <feGaussianBlur stdDeviation="3"/>
                    <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 3 -1.45"/>
                </filter>
                <radialGradient id="edgeL" cx="0" cy="1" r=".9"><stop offset="0" stop-color="#fff"/><stop offset=".5" stop-color="#fff" stop-opacity=".9"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
                <radialGradient id="edgeR" cx="1" cy="0" r=".72"><stop offset="0" stop-color="#fff"/><stop offset=".55" stop-color="#fff" stop-opacity=".75"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
                <radialGradient id="edgeBR" cx="1.05" cy="1" r=".55"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
                <mask id="cloudmask"><rect width="1600" height="1000" fill="url(#edgeL)"/><rect width="1600" height="1000" fill="url(#edgeR)"/><rect width="1600" height="1000" fill="url(#edgeBR)"/></mask>
                <radialGradient id="glow" cx=".3" cy=".55" r=".45"><stop offset="0" stop-color="#fff" stop-opacity=".6"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
            </defs>
            <rect width="1600" height="1000" fill="url(#sky)"/>
            <rect width="1600" height="1000" filter="url(#wisps)" opacity=".5"/>
            <g mask="url(#cloudmask)"><rect width="1600" height="1000" filter="url(#clouds)"/></g>
            <rect width="1600" height="1000" fill="url(#glow)"/>
        </svg>
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
