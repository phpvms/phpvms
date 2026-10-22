<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { ref, useTemplateRef } from "vue";
import ApiConnectionsDrawer from "@/components/profile/ApiConnectionsDrawer.vue";
import UAlert from "@nuxt/ui/components/Alert.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";

/**
 * "API access" -- the SPA form of the API-key row and the two action buttons in
 * the Blade edit page's lower half (edit.blade.php: the apiKey_show/apiKey_hide
 * toggle, the regen_apikey link, and the link to the API connections page).
 *
 * The key is hidden behind a reveal for the same reason the Blade page hides
 * it: it is a bearer credential and profile pages get screen-shared.
 */
const props = defineProps<{ apiKey: string | null }>();

const revealed = ref(false);
const drawer = useTemplateRef<InstanceType<typeof ApiConnectionsDrawer>>("drawer");

/**
 * A GET route that rotates the key and redirects back to the profile, so an
 * Inertia visit picks up the new page props. Confirmed first because the old
 * key stops working immediately.
 */
function regenerate() {
  if (!globalThis.confirm("Are you sure? This will reset your API key.")) return;
  router.get("/profile/regen_apikey");
}
</script>

<template>
  <UPageCard
    class="pv-profile-api"
    variant="outline"
    title="API access"
    description="Used by ACARS clients and API integrations. Do not share it."
    aria-label="API access"
  >
    <div class="api-body">
      <UAlert
        color="warning"
        variant="subtle"
        title="Legacy API key"
        description="Personal access tokens on the API connections page are preferred for new integrations."
      />

      <div class="key-row">
        <code v-if="revealed && props.apiKey" class="key">{{ props.apiKey }}</code>
        <code v-else class="key key-hidden">••••••••••••••••••••</code>

        <UButton
          color="neutral"
          variant="ghost"
          size="sm"
          :disabled="!props.apiKey"
          @click="revealed = !revealed"
        >
          {{ revealed ? "Hide" : "Show" }}
        </UButton>
      </div>

      <div class="actions">
        <UButton color="neutral" variant="outline" size="sm" @click="drawer?.show()">
          API connections
        </UButton>
        <UButton color="warning" variant="outline" size="sm" @click="regenerate">
          Regenerate API key
        </UButton>
      </div>
    </div>

    <ApiConnectionsDrawer ref="drawer" />
  </UPageCard>
</template>

<style scoped>
@layer components {
  .api-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .key-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .key {
    font-family: var(--pv-font-mono);
    font-size: 12px;
    color: var(--pv-ink);
    overflow-wrap: anywhere;
  }
  .key-hidden {
    color: var(--pv-ink-dim);
    letter-spacing: 0.1em;
  }
  .actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
}
</style>
