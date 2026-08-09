{{--
    Drives the installer's "advance to the next step on its own" countdown.

    The two controls it talks to — the wizard's Pause and Next buttons — are
    rendered in the wizard footer, which sits outside this step's schema, so the
    shared countdown state lives in a global Alpine store rather than an x-data
    scope on a common ancestor.
--}}
<script>
    (() => {
        if (window.installerAutoAdvanceRegistered) {
            return
        }

        window.installerAutoAdvanceRegistered = true

        const store = () => window.Alpine?.store('installerAutoAdvance')

        const register = () => {
            window.Alpine.store('installerAutoAdvance', {
                active: false,
                paused: false,
                remaining: 0,
                timer: null,

                start(seconds) {
                    if (this.active) {
                        return
                    }

                    this.active = true
                    this.paused = false
                    this.remaining = seconds

                    this.timer = setInterval(() => {
                        if (this.paused) {
                            return
                        }

                        this.remaining--

                        if (this.remaining <= 0) {
                            this.advance()
                        }
                    }, 1000)

                    requestAnimationFrame(() => {
                        document
                            .querySelector('.fi-sc-wizard-footer')
                            ?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                    })
                },

                toggle() {
                    this.paused = ! this.paused
                },

                advance() {
                    this.stop()

                    document.querySelector('[data-installer-next]')?.click()
                },

                stop() {
                    clearInterval(this.timer)

                    this.timer = null
                    this.active = false
                },
            })
        }

        document.addEventListener('alpine:init', register)

        if (window.Alpine) {
            register()
        }

        window.addEventListener('installer-migrations-complete', (event) => {
            store()?.start(event.detail?.seconds ?? {{ $seconds }})
        })

        // Covers both the countdown firing and the user clicking Next early, so
        // the paused countdown can't linger into the following step.
        window.addEventListener('next-wizard-step', () => store()?.stop())
    })()
</script>
