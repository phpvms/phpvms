<script setup lang="ts">
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * Transient flash messages from the shared `flash` prop (set by
 * HandleInertiaRequests). Shows on every Inertia visit that carries a
 * success/error, auto-dismisses, and stacks. Lives in PvApp (persistent chrome).
 */
interface Flash { success?: string | null; error?: string | null }
interface Toast { id: number; kind: 'success' | 'error'; text: string }

const page = usePage()
const toasts = ref<Toast[]>([])
let seq = 0

function push(kind: Toast['kind'], text: string) {
  const id = ++seq
  toasts.value.push({ id, kind, text })
  setTimeout(() => dismiss(id), 5000)
}
function dismiss(id: number) {
  toasts.value = toasts.value.filter((t) => t.id !== id)
}

watch(
  () => page.props.flash as Flash | undefined,
  (flash) => {
    if (flash?.success) push('success', flash.success)
    if (flash?.error) push('error', flash.error)
  },
  { immediate: true, deep: true },
)
</script>

<template>
  <div class="flash-stack" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="flash">
      <div v-for="t in toasts" :key="t.id" class="toast" :class="t.kind" role="status">
        <span class="tag">{{ t.kind === 'success' ? 'OK' : 'FAULT' }}</span>
        <span class="text">{{ t.text }}</span>
        <button class="x" aria-label="Dismiss" @click="dismiss(t.id)">✕</button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.flash-stack {
  position: fixed;
  right: 20px;
  bottom: 20px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 380px;
}
.toast {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-left-width: 3px;
  border-radius: var(--pv-radius-md);
  box-shadow: var(--pv-shadow-panel);
  font-family: var(--pv-font-mono);
  font-size: calc(12px * var(--pv-type-scale));
  color: var(--pv-ink);
}
.toast.success { border-left-color: var(--pv-green); }
.toast.error { border-left-color: var(--pv-color-error); }
.tag {
  font-size: calc(8px * var(--pv-type-scale));
  letter-spacing: 0.14em;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: var(--pv-radius-sm);
}
.toast.success .tag { color: var(--pv-green); background: color-mix(in srgb, var(--pv-green) 10%, transparent); }
.toast.error .tag { color: var(--pv-color-error); background: color-mix(in srgb, var(--pv-color-error) 10%, transparent); }
.text { flex: 1; }
.x {
  background: none;
  border: none;
  color: var(--pv-ink-dim);
  cursor: pointer;
  font-size: calc(11px * var(--pv-type-scale));
}
.x:hover { color: var(--pv-ink); }

.flash-enter-active,
.flash-leave-active { transition: opacity 0.25s, transform 0.25s; }
.flash-enter-from,
.flash-leave-to { opacity: 0; transform: translateX(20px); }
</style>
