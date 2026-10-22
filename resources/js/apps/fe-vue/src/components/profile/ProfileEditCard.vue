<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref, shallowRef, watch } from "vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UCheckbox from "@nuxt/ui/components/Checkbox.vue";
import UFileUpload from "@nuxt/ui/components/FileUpload.vue";
import UForm from "@nuxt/ui/components/Form.vue";
import UFormField from "@nuxt/ui/components/FormField.vue";
import UInput from "@nuxt/ui/components/Input.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";
import USelectMenu from "@nuxt/ui/components/SelectMenu.vue";
import USeparator from "@nuxt/ui/components/Separator.vue";

/**
 * Inline "Edit profile" card, rendered only on the pilot's own profile.
 *
 * Posts to the same `frontend.profile.update` route the Blade form uses, with
 * the same input names -- including `field_{slug}` for custom user fields -- so
 * the SPA and Blade themes stay one write path. `_method: "put"` spoofs the
 * verb because the route is PUT|PATCH but a file upload has to go over POST.
 *
 * Validation errors arrive through Inertia's shared `errors` prop (the base
 * inertia-laravel Middleware always shares it), which `useForm` exposes as
 * `form.errors`, so no client-side schema duplicates the server rules.
 */
const props = defineProps<{
  edit: App.Http.Data.ProfileEditData;
  profileId: number;
}>();

/**
 * Spelled out rather than `Record<string, unknown>`: Inertia constrains useForm
 * to FormDataType, whose values must be FormDataConvertible, and `unknown`
 * collapses to `never` there. The template-literal index carries the custom
 * fields, which are only known at runtime.
 */
interface ProfileFormData {
  _method: string;
  name: string;
  email: string;
  airline_id: string;
  home_airport_id: string;
  country: string;
  timezone: string;
  simbrief_username: string;
  opt_in: boolean;
  avatar: File | null;
  password: string;
  password_confirmation: string;
  /** Always a text input: user_fields has no type/options column. */
  [customField: `field_${string}`]: string;
}

/** Custom fields post as `field_{slug}`; seed them alongside the fixed keys. */
const customFieldValues = Object.fromEntries(
  props.edit.fields.map((field) => [`field_${field.slug}`, field.value ?? ""]),
) as Record<`field_${string}`, string>;

const form = useForm<ProfileFormData>({
  _method: "put",
  name: props.edit.name,
  email: props.edit.email,
  airline_id: props.edit.airlineId,
  home_airport_id: props.edit.homeAirport?.value ?? "",
  country: props.edit.country ?? "",
  timezone: props.edit.timezone ?? "",
  simbrief_username: props.edit.simbriefUsername ?? "",
  opt_in: props.edit.optIn,
  avatar: null,
  password: "",
  password_confirmation: "",
  ...customFieldValues,
});

/**
 * Airports are searched, not shipped: the table is unbounded, so the card seeds
 * the menu with the currently-selected airport and queries
 * `GET /api/airports/search` as the pilot types. `hubs=1` mirrors the
 * `pilots.home_hubs_only` setting the Blade form honours.
 */
const airportItems = shallowRef<App.Http.Data.SelectOptionData[]>(
  props.edit.homeAirport ? [props.edit.homeAirport] : [],
);
const airportSearch = ref("");
const airportLoading = ref(false);

let searchTimer: ReturnType<typeof setTimeout> | undefined;
/** Guards against an earlier, slower response overwriting a later one. */
let latestSearch = 0;

watch(airportSearch, (term) => {
  clearTimeout(searchTimer);

  if (term.trim().length < 2) {
    airportLoading.value = false;
    return;
  }

  airportLoading.value = true;
  searchTimer = setTimeout(() => void runAirportSearch(term.trim()), 250);
});

onBeforeUnmount(() => clearTimeout(searchTimer));

async function runAirportSearch(term: string) {
  const token = ++latestSearch;

  try {
    const params = new URLSearchParams({ search: term, limit: "20" });
    if (props.edit.hubsOnly) params.set("hubs", "1");

    const response = await fetch(`/api/airports/search?${params}`, {
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw new Error(`airport search failed: ${response.status}`);

    const body = (await response.json()) as {
      data?: Array<{ id: number | string; icao: string; name: string }>;
    };
    if (token !== latestSearch) return;

    airportItems.value = (body.data ?? []).map((airport) => ({
      value: String(airport.id),
      label: `${airport.icao} - ${airport.name}`,
    }));
  } catch {
    if (token === latestSearch) airportItems.value = [];
  } finally {
    if (token === latestSearch) airportLoading.value = false;
  }
}

const avatarHint = computed(
  () => `JPEG or PNG. Resized to ${props.edit.avatarWidth}x${props.edit.avatarHeight}.`,
);

function submit() {
  form.post(`/profile/${props.profileId}`, {
    forceFormData: true,
    preserveScroll: true,
    // Success feedback is the app-wide FlashToasts, fed by the controller's
    // Flash::success(). No local banner duplicating it.
    onSuccess: () => form.reset("password", "password_confirmation", "avatar"),
  });
}
</script>

<template>
  <UPageCard
    class="pv-profile-edit"
    variant="outline"
    title="Edit profile"
    description="Update your details. Leave the password fields blank to keep your current password."
    aria-label="Edit profile"
  >
    <UForm :state="form" class="grid gap-4 sm:grid-cols-2" @submit="submit">
      <UFormField label="Name" name="name" required :error="form.errors.name">
        <UInput v-model="form.name" class="w-full" autocomplete="name" />
      </UFormField>

      <UFormField label="Email" name="email" required :error="form.errors.email">
        <UInput v-model="form.email" type="email" class="w-full" autocomplete="email" />
      </UFormField>

      <UFormField label="Airline" name="airline_id" required :error="form.errors.airline_id">
        <USelectMenu
          v-model="form.airline_id"
          :items="edit.airlines"
          value-key="value"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Home airport"
        name="home_airport_id"
        :error="form.errors.home_airport_id"
        :hint="edit.hubsOnly ? 'Hubs only' : undefined"
      >
        <USelectMenu
          v-model="form.home_airport_id"
          v-model:search-term="airportSearch"
          :items="airportItems"
          :loading="airportLoading"
          value-key="value"
          ignore-filter
          placeholder="Search by ICAO or name"
          class="w-full"
        />
      </UFormField>

      <UFormField label="Country" name="country" :error="form.errors.country">
        <USelectMenu
          v-model="form.country"
          :items="edit.countries"
          value-key="value"
          class="w-full"
        />
      </UFormField>

      <UFormField label="Timezone" name="timezone" :error="form.errors.timezone">
        <USelectMenu
          v-model="form.timezone"
          :items="edit.timezones"
          value-key="value"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="SimBrief username"
        name="simbrief_username"
        :error="form.errors.simbrief_username"
      >
        <UInput v-model="form.simbrief_username" class="w-full" />
      </UFormField>

      <UFormField label="Avatar" name="avatar" :error="form.errors.avatar" :hint="avatarHint">
        <UFileUpload v-model="form.avatar" accept="image/jpeg,image/png" variant="button" />
      </UFormField>

      <UFormField name="opt_in" :error="form.errors.opt_in" class="col-span-full">
        <UCheckbox
          v-model="form.opt_in"
          label="Opt in to emails"
          description="Receive news and announcements from the airline."
        />
      </UFormField>

      <template v-if="edit.fields.length">
        <USeparator class="col-span-full" label="Additional details" />

        <UFormField
          v-for="field in edit.fields"
          :key="field.slug"
          :label="field.name"
          :name="`field_${field.slug}`"
          :required="field.required"
          :error="form.errors[`field_${field.slug}`]"
        >
          <UInput v-model="form[`field_${field.slug}`]" class="w-full" />
        </UFormField>
      </template>

      <USeparator class="col-span-full" label="Change password" />

      <UFormField label="New password" name="password" :error="form.errors.password">
        <UInput
          v-model="form.password"
          type="password"
          class="w-full"
          autocomplete="new-password"
        />
      </UFormField>

      <UFormField label="Confirm password" name="password_confirmation">
        <UInput
          v-model="form.password_confirmation"
          type="password"
          class="w-full"
          autocomplete="new-password"
        />
      </UFormField>

      <div class="col-span-full flex justify-end">
        <UButton type="submit" :loading="form.processing" :disabled="form.processing">
          Save changes
        </UButton>
      </div>
    </UForm>
  </UPageCard>
</template>
