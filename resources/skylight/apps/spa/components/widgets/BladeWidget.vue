<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Generic host shell for a `blade`-kind widget: renders server HTML from an
 * endpoint. Two modes:
 *  - island (default): fetch the endpoint, inject the returned HTML into a
 *    container, and progressively enhance any <form> inside it (intercept
 *    submit, re-fetch with the CSRF header, swap the HTML). Mirrors WxWidget's
 *    fetch/abort/loading/error lifecycle.
 *  - iframe: render an <iframe> and auto-size it to its same-origin content.
 * Fail-visible: a non-OK response or error renders a diagnostic, never a gap.
 */
const props = withDefaults(defineProps<{ endpoint: string; mode?: 'island' | 'iframe' }>(), {
  mode: 'island',
})

type State =
  | { status: 'idle' | 'loading' }
  | { status: 'ready' }
  | { status: 'error'; message: string }

const state = ref<State>({ status: 'idle' })
// Non-destructive submit error: surfaced as a line ABOVE the existing fragment
// so a failed form submit never wipes the user's input/fragment.
const submitError = ref<string | null>(null)
const container = ref<HTMLElement | null>(null)
const frame = ref<HTMLIFrameElement | null>(null)
const frameHeight = ref<number>(0)

let controller: AbortController | null = null
let resizeObserver: ResizeObserver | null = null

function csrfToken(): string {
  return (
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
  )
}

/**
 * Serialize a form's fields into URLSearchParams. Passing the `submitter` (the
 * pressed submit button) includes its name/value — otherwise a multi-action
 * form (multiple `<button name= value=>`) loses which action was pressed.
 */
function serializeForm(form: HTMLFormElement, submitter?: HTMLElement | null): URLSearchParams {
  const params = new URLSearchParams()
  // FormData's 2nd arg is the submitter; guard for older engines by passing it
  // only when present (it must be a submit button that belongs to the form).
  const fd = submitter ? new FormData(form, submitter as HTMLElement) : new FormData(form)
  for (const [key, value] of fd.entries()) {
    // Files are not supported in this progressive-enhancement path.
    if (typeof value === 'string') params.append(key, value)
  }
  return params
}

/** Intercept every <form> inside the container: submit → fetch → swap HTML. */
function attachForms() {
  const el = container.value
  if (!el) return
  el.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', onFormSubmit)
  })
}

async function onFormSubmit(event: Event) {
  event.preventDefault()
  const form = event.currentTarget as HTMLFormElement
  // Capture the pressed submit button so its name/value is included (multi-
  // action forms). SubmitEvent.submitter is undefined on very old engines.
  const submitter = (event as SubmitEvent).submitter ?? null
  const method = (form.getAttribute('method') || 'get').toUpperCase()
  const action = form.getAttribute('action') || props.endpoint
  const params = serializeForm(form, submitter)

  // Whether we currently have a rendered fragment to preserve on failure.
  const hadFragment = state.value.status === 'ready'

  controller?.abort()
  controller = new AbortController()
  submitError.value = null
  // Only show the full-shell loading state if there is nothing to preserve;
  // an in-place re-submit keeps the existing fragment visible.
  if (!hadFragment) state.value = { status: 'loading' }

  // Surface a submit failure without wiping the user's fragment/input: if a
  // fragment is already shown, keep it and show an error line above it;
  // otherwise fall back to the dead-end error box.
  const failSubmit = (message: string) => {
    if (hadFragment) {
      submitError.value = message
      state.value = { status: 'ready' }
    } else {
      state.value = { status: 'error', message }
    }
  }

  try {
    let url = action
    const init: RequestInit = {
      method,
      credentials: 'same-origin',
      signal: controller.signal,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/html',
        'X-CSRF-TOKEN': csrfToken(),
      },
    }
    if (method === 'GET') {
      const q = params.toString()
      url = q ? `${action}${action.includes('?') ? '&' : '?'}${q}` : action
    } else {
      init.body = params
      ;(init.headers as Record<string, string>)['Content-Type'] =
        'application/x-www-form-urlencoded'
    }
    const res = await fetch(url, init)
    if (!res.ok) {
      failSubmit(`Submit failed (HTTP ${res.status})`)
      return
    }
    // A redirect (e.g. session expired → login page) must NOT be injected as
    // widget HTML — treat it as an error, keeping the current fragment.
    if (res.redirected) {
      failSubmit('Submit failed (session expired — please reload)')
      return
    }
    inject(await res.text())
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') return
    failSubmit(err instanceof Error ? err.message : 'Submit error')
  }
}

/** Replace container HTML and (re-)attach form listeners. */
function inject(html: string) {
  const el = container.value
  if (!el) return
  el.innerHTML = html
  submitError.value = null
  state.value = { status: 'ready' }
  attachForms()
}

async function loadIsland() {
  controller?.abort()
  controller = new AbortController()
  state.value = { status: 'loading' }
  try {
    const res = await fetch(props.endpoint, {
      credentials: 'same-origin',
      signal: controller.signal,
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
    })
    if (!res.ok) {
      state.value = { status: 'error', message: `Unavailable (HTTP ${res.status})` }
      return
    }
    inject(await res.text())
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') return
    state.value = { status: 'error', message: err instanceof Error ? err.message : 'Fetch error' }
  }
}

/** iframe mode: sync height from the same-origin content document. */
function syncFrameHeight() {
  const iframe = frame.value
  try {
    const doc = iframe?.contentDocument
    if (!doc) return
    frameHeight.value = doc.documentElement.scrollHeight
  } catch {
    /* cross-origin — cannot measure, leave height as-is */
  }
}

function onFrameLoad() {
  syncFrameHeight()
  try {
    const body = frame.value?.contentDocument?.body
    if (body && typeof ResizeObserver !== 'undefined') {
      resizeObserver?.disconnect()
      resizeObserver = new ResizeObserver(() => syncFrameHeight())
      resizeObserver.observe(body)
    }
  } catch {
    /* cross-origin — no observer */
  }
}

onMounted(() => {
  if (props.mode === 'island') loadIsland()
})

watch(
  () => props.endpoint,
  () => {
    if (props.mode === 'island') loadIsland()
  },
)

onBeforeUnmount(() => {
  controller?.abort()
  resizeObserver?.disconnect()
})
</script>

<template>
  <div class="blade-widget">
    <!-- iframe mode -->
    <iframe
      v-if="mode === 'iframe'"
      ref="frame"
      :src="endpoint"
      class="frame"
      :style="{ height: frameHeight ? frameHeight + 'px' : undefined }"
      @load="onFrameLoad"
    />

    <!-- island mode -->
    <template v-else>
      <div
        v-if="state.status === 'loading' || state.status === 'idle'"
        class="loading"
        role="status"
      >
        <span class="spin" aria-hidden="true" /> Loading…
      </div>
      <div
        v-else-if="state.status === 'error'"
        class="err"
        role="alert"
        data-blade-error
      >
        {{ state.message }}
      </div>
      <!--
        Non-destructive submit error: shown ABOVE the preserved fragment so a
        failed submit surfaces the problem without wiping the user's input.
      -->
      <div
        v-if="submitError && state.status === 'ready'"
        class="err"
        role="alert"
        data-blade-submit-error
      >
        {{ submitError }}
      </div>
      <!-- Container is always present so the ref exists before injection. -->
      <div v-show="state.status === 'ready'" ref="container" class="island" />
    </template>
  </div>
</template>

<style scoped>
.blade-widget {
  display: flex;
  flex-direction: column;
  gap: 10px;
  color: var(--pv-ink);
}
.frame {
  width: 100%;
  border: 0;
  display: block;
}
.island {
  font-size: 13px;
  color: var(--pv-ink);
}
.loading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--pv-ink-dim);
}
.spin {
  width: 12px;
  height: 12px;
  border: 2px solid var(--pv-line);
  border-top-color: var(--pv-accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.err {
  font-size: 12px;
  color: var(--pv-slot-error-text);
  background: var(--pv-slot-error-bg);
  border: 1px solid var(--pv-slot-error-border);
  border-radius: var(--pv-radius-sm);
  padding: 8px 10px;
  font-family: var(--pv-font-mono);
}
</style>
