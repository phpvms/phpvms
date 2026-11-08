{{--
  The generated sky: gradient, clouds and wisps. Fill a positioned parent with it (the home hero,
  the dashboard hero). Colours come from the `.clouds-bg` rules in airside.css (day, and night under .dark).
  The cloud matrices stay white; dark mode dims them with the opacity rules.
--}}
<svg {{ $attributes->class(['vms-sky clouds-bg']) }} viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <defs>
        <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop class="clouds-bg-top" offset="0"/><stop class="clouds-bg-mid" offset=".55"/><stop class="clouds-bg-bottom" offset="1"/></linearGradient>
        <filter id="clouds" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
            <feTurbulence type="fractalNoise" baseFrequency="0.0036 0.0062" numOctaves="6" seed="23" result="raw"/>
            <feGaussianBlur in="raw" stdDeviation="4" result="n"/>
            <feColorMatrix in="n" type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 5 -2.45" result="a"/>
            <feDiffuseLighting class="clouds-bg-light" in="n" surfaceScale="4" diffuseConstant="1.1" result="lit"><feDistantLight azimuth="240" elevation="62"/></feDiffuseLighting>
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
        <radialGradient id="glow" cx=".3" cy=".55" r=".45"><stop class="clouds-bg-glow" offset="0"/><stop class="clouds-bg-glow clouds-bg-glow-end" offset="1"/></radialGradient>
    </defs>
    <rect width="1600" height="1000" fill="url(#sky)"/>
    <rect width="1600" height="1000" class="clouds-bg-wisps" filter="url(#wisps)"/>
    <g class="clouds-bg-clouds" mask="url(#cloudmask)"><rect width="1600" height="1000" filter="url(#clouds)"/></g>
    <rect width="1600" height="1000" fill="url(#glow)"/>
</svg>
