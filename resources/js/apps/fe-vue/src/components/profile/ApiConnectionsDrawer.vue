<script setup lang="ts">
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, shallowRef } from "vue";
import IconX from "~icons/tabler/x";
import UAlert from "@nuxt/ui/components/Alert.vue";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UCheckbox from "@nuxt/ui/components/Checkbox.vue";
import UDrawer from "@nuxt/ui/components/Drawer.vue";
import UFormField from "@nuxt/ui/components/FormField.vue";
import UInput from "@nuxt/ui/components/Input.vue";
import USeparator from "@nuxt/ui/components/Separator.vue";

/**
 * API connections, in a drawer on the profile rather than the standalone Blade
 * page at /profile/connections (which the `seven` theme still serves).
 *
 * `apiConnections` is an Inertia optional prop, so it is absent until the
 * drawer asks for it by name -- opening triggers a partial reload, and the
 * same reload refreshes the list after a create or revoke.
 */
const open = shallowRef(false);
const loading = shallowRef(false);
const page = usePage();

const data = computed(
  () => page.props.apiConnections as App.Http.Data.ApiConnectionsData | undefined,
);

/**
 * Shown once, immediately after creation: Passport only ever returns the
 * plaintext token at issue time, so there is no second chance to read it.
 */
const plainTextToken = computed(
  () => (page.props.flash as { plainTextToken?: string } | undefined)?.plainTextToken,
);

const form = useForm<{ name: string; scopes: string[] }>({ name: "", scopes: [] });
const revoking = ref<string | null>(null);

function reload() {
  loading.value = true;
  router.reload({
    only: ["apiConnections", "flash"],
    onFinish: () => {
      loading.value = false;
    },
  });
}

function show() {
  open.value = true;
  reload();
}

function updateOpen(next: boolean) {
  open.value = next;
}

function toggleScope(scope: string, checked: boolean) {
  form.scopes = checked ? [...form.scopes, scope] : form.scopes.filter((value) => value !== scope);
}

function createToken() {
  form.post("/profile/tokens", {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      reload();
    },
  });
}

function revokeToken(token: App.Http.Data.ApiTokenData) {
  if (!globalThis.confirm(`Revoke "${token.name}"? Anything using it stops working.`)) return;

  revoking.value = token.id;
  router.delete(`/profile/tokens/${encodeURIComponent(token.id)}`, {
    preserveScroll: true,
    onFinish: () => {
      revoking.value = null;
      reload();
    },
  });
}

function revokeApp(app: App.Http.Data.AuthorizedAppData) {
  if (!globalThis.confirm(`Revoke access for ${app.name}?`)) return;

  revoking.value = app.clientId;
  router.delete(`/profile/connections/${encodeURIComponent(app.clientId)}`, {
    preserveScroll: true,
    onFinish: () => {
      revoking.value = null;
      reload();
    },
  });
}

defineExpose({ show });
</script>

<template>
  <UDrawer
    :open="open"
    direction="right"
    title="API connections"
    description="Applications and tokens that can access the API on your behalf."
    :handle="false"
    @update:open="updateOpen"
  >
    <template #content>
      <section class="pv-api-connections" aria-label="API connections">
        <header class="drawer-header">
          <div>
            <h2>API connections</h2>
            <p>Applications and tokens that can access the API on your behalf.</p>
          </div>
          <UButton
            type="button"
            color="neutral"
            variant="ghost"
            :icon="IconX"
            aria-label="Close API connections"
            @click="updateOpen(false)"
          />
        </header>

        <div class="drawer-scroll">
          <UAlert
            v-if="plainTextToken"
            color="success"
            variant="subtle"
            title="Copy this token now"
            description="It is shown once and cannot be retrieved again."
            class="mb-4"
          >
            <template #description>
              <p>It is shown once and cannot be retrieved again.</p>
              <code class="token-value">{{ plainTextToken }}</code>
            </template>
          </UAlert>

          <h3>Personal access tokens</h3>
          <p class="section-hint">
            Create a token to use the API from your own scripts or ACARS client. Choose only the
            scopes you need.
          </p>

          <UFormField label="Token name" name="name" required :error="form.errors.name">
            <UInput v-model="form.name" class="w-full" placeholder="My ACARS client" />
          </UFormField>

          <fieldset class="scopes">
            <legend>Select scopes</legend>
            <UCheckbox
              v-for="scope in data?.scopes ?? []"
              :key="scope.value"
              :model-value="form.scopes.includes(scope.value)"
              :label="scope.value"
              :description="scope.label"
              @update:model-value="toggleScope(scope.value, $event === true)"
            />
          </fieldset>

          <UButton
            :loading="form.processing"
            :disabled="form.processing || !form.name"
            @click="createToken"
          >
            Create token
          </UButton>

          <USeparator class="my-5" />

          <h3>Active tokens</h3>
          <p v-if="!data?.personalTokens.length" class="section-hint">No personal tokens yet.</p>
          <ul v-else class="rows">
            <li v-for="token in data.personalTokens" :key="token.id" class="row">
              <div class="row-copy">
                <span class="row-name">{{ token.name }}</span>
                <span class="row-scopes">{{ token.scopes.join(", ") || "No scopes" }}</span>
              </div>
              <UButton
                color="error"
                variant="outline"
                size="sm"
                :loading="revoking === token.id"
                @click="revokeToken(token)"
              >
                Revoke
              </UButton>
            </li>
          </ul>

          <USeparator class="my-5" />

          <h3>Authorized applications</h3>
          <p v-if="!data?.authorizedApps.length" class="section-hint">
            No applications have been authorized.
          </p>
          <ul v-else class="rows">
            <li v-for="app in data.authorizedApps" :key="app.clientId" class="row">
              <div class="row-copy">
                <span class="row-name">{{ app.name }}</span>
                <span class="row-scopes">{{ app.scopes.join(", ") || "No scopes" }}</span>
              </div>
              <div class="row-actions">
                <UBadge color="neutral" variant="subtle" size="sm">
                  {{ app.tokenCount }} {{ app.tokenCount === 1 ? "token" : "tokens" }}
                </UBadge>
                <UButton
                  color="error"
                  variant="outline"
                  size="sm"
                  :loading="revoking === app.clientId"
                  @click="revokeApp(app)"
                >
                  Revoke
                </UButton>
              </div>
            </li>
          </ul>
        </div>
      </section>
    </template>
  </UDrawer>
</template>

<style scoped>
@layer components {
  .pv-api-connections {
    display: flex;
    height: 100%;
    min-width: 0;
    flex-direction: column;
  }
  .drawer-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--pv-line);
  }
  .drawer-header h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 650;
    color: var(--pv-ink);
  }
  .drawer-header p {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--pv-ink-dim);
  }
  .drawer-scroll {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    padding: 20px;
  }
  .drawer-scroll h3 {
    margin: 0;
    font-size: 13px;
    font-weight: 650;
    color: var(--pv-ink);
  }
  .section-hint {
    margin: 4px 0 12px;
    font-size: 12px;
    color: var(--pv-ink-dim);
  }
  .scopes {
    display: grid;
    gap: 8px;
    margin: 12px 0 16px;
    padding: 0;
    border: 0;
  }
  .scopes legend {
    padding: 0;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 500;
    color: var(--pv-ink-dim);
  }
  /* Data font: a bearer credential, not prose. */
  .token-value {
    display: block;
    margin-top: 8px;
    font-family: var(--pv-font-mono);
    font-size: 12px;
    overflow-wrap: anywhere;
  }
  .rows {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .row-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
  }
  .row-name {
    font-size: 13px;
    font-weight: 650;
    color: var(--pv-ink);
  }
  .row-scopes {
    font-family: var(--pv-font-mono);
    font-size: 11px;
    color: var(--pv-ink-dim);
    overflow-wrap: anywhere;
  }
  .row-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}
</style>
