<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";

/**
 * "Connected accounts" -- the SPA form of
 * resources/views/layouts/seven/profile/connected-accounts.blade.php.
 *
 * A linked provider offers unlink (DELETE oauth.unlink), an unlinked but
 * connectable one offers link, and a provider that is linked-but-no-longer-
 * connectable is listed with unlink only, so a pilot is never stranded.
 *
 * The Blade page also printed the Discord account id in its "Your profile"
 * table; it belongs to the provider that issued it, so it is shown on that
 * provider's row here instead of in a separate table.
 */
const props = defineProps<{ connections: App.Http.Data.ProfileConnectionData[] }>();

function unlink(connection: App.Http.Data.ProfileConnectionData) {
  if (!globalThis.confirm(`Unlink ${connection.displayName}?`)) return;

  router.delete(`/oauth/${encodeURIComponent(connection.connectionId)}/unlink`, {
    preserveScroll: true,
  });
}

/**
 * Full page navigation, not an Inertia visit: this route 302s out to the
 * identity provider, and an XHR visit would follow that redirect cross-origin
 * and fail CORS.
 */
function linkUrl(connection: App.Http.Data.ProfileConnectionData): string {
  return `/oauth/${encodeURIComponent(connection.connectionId)}/redirect?intent=link`;
}
</script>

<template>
  <UPageCard
    v-if="props.connections.length"
    class="pv-profile-connections"
    variant="outline"
    title="Connected accounts"
    description="Sign in to phpvms with an external account."
    aria-label="Connected accounts"
  >
    <ul class="connections">
      <li v-for="connection in props.connections" :key="connection.connectionId" class="connection">
        <div class="identity">
          <span class="name">{{ connection.displayName }}</span>
          <span class="state">
            <UBadge :color="connection.linked ? 'success' : 'neutral'" variant="subtle" size="sm">
              {{ connection.linked ? "Connected" : "Not connected" }}
            </UBadge>
            <span v-if="connection.providerUserId" class="provider-id">
              {{ connection.providerUserId }}
            </span>
          </span>
        </div>

        <UButton
          v-if="connection.linked"
          color="error"
          variant="outline"
          size="sm"
          @click="unlink(connection)"
        >
          Unlink
        </UButton>
        <UButton
          v-else-if="connection.linkable"
          :href="linkUrl(connection)"
          color="neutral"
          variant="outline"
          size="sm"
        >
          Link
        </UButton>
      </li>
    </ul>
  </UPageCard>
</template>

<style scoped>
@layer components {
  .connections {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .connection {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .identity {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
  }
  .name {
    font-size: 13px;
    font-weight: 650;
    color: var(--pv-ink);
  }
  .state {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  /* Data font: this is an id issued by the provider, not prose. */
  .provider-id {
    font-family: var(--pv-font-mono);
    font-size: 11px;
    color: var(--pv-ink-dim);
    overflow-wrap: anywhere;
  }
}
</style>
