<!-- Selects one generated consumption profile through a compact suggestion input. -->
<template>
  <div class="consumption-profile-picker">
    <div class="consumption-profile-picker__search">
      <input
        v-model.trim="query"
        type="search"
        class="form-control form-control-sm"
        :placeholder="searchPlaceholder"
        role="combobox"
        :aria-expanded="showSuggestions"
        aria-autocomplete="list"
        @focus="showSuggestions = true"
        @blur="hideSuggestions"
        @input="handleQueryInput"
        @keydown.esc="showSuggestions = false"
      />
      <div
        v-if="showSuggestions"
        class="consumption-profile-picker__suggestions"
        role="listbox"
      >
        <button
          v-for="profile in suggestions"
          :key="profile.id"
          type="button"
          role="option"
          class="consumption-profile-picker__suggestion"
          :aria-selected="profile.id === modelValue"
          @mousedown.prevent="selectProfile(profile)"
        >
          <strong>{{ profile.id }} · {{ profile.name }}</strong>
          <small
            >{{ profile.kind }} · {{ profile.category }} ·
            {{ profile.region }}</small
          >
        </button>
        <small v-if="loading" class="consumption-profile-picker__empty"
          >Ładowanie katalogu…</small
        >
        <small
          v-else-if="!suggestions.length"
          class="consumption-profile-picker__empty"
        >
          Brak pasujących profili.
        </small>
      </div>
    </div>
  </div>
</template>

<script>
import { shopApiClient } from "@/lib/trade/shop-api-client";
import { useShopWorkspaceContext } from "@/components/shop/modules/gm-workspace/shopWorkspaceContext";

export default {
  name: "ConsumptionProfilePicker",
  props: {
    modelValue: { type: String, default: "" },
    emptyLabel: { type: String, default: "Niespożywalny" },
    placeholder: {
      type: String,
      default: "Szukaj: ID, nazwa, rodzaj, kategoria, region",
    },
  },
  emits: ["update:modelValue", "selected"],
  setup() {
    return useShopWorkspaceContext();
  },
  data: () => ({
    query: "",
    profiles: [],
    loading: false,
    catalogLoaded: false,
    showSuggestions: false,
  }),
  computed: {
    filteredProfiles() {
      const query = this.query.toLocaleLowerCase();
      if (!query) return this.profiles;
      return this.profiles.filter((profile) =>
        [
          profile.id,
          profile.name,
          profile.kind,
          profile.category,
          profile.region,
        ]
          .join(" ")
          .toLocaleLowerCase()
          .includes(query),
      );
    },
    selected() {
      return (
        this.profiles.find((profile) => profile.id === this.modelValue) || null
      );
    },
    suggestions() {
      return this.filteredProfiles.slice(0, 12);
    },
    searchPlaceholder() {
      return this.modelValue
        ? this.placeholder
        : `${this.emptyLabel} — ${this.placeholder}`;
    },
  },
  watch: {
    selected: {
      handler(profile) {
        if (this.catalogLoaded) this.$emit("selected", profile || null);
      },
    },
  },
  mounted() {
    this.loadProfiles();
  },
  methods: {
    async loadProfiles() {
      if (this.loading || !this.shopState?.campaignId) return;
      this.loading = true;
      try {
        const response = await shopApiClient.getConsumptionProfiles({
          campaignId: this.shopState.campaignId,
          ownerCode: this.profileDraft?.ownerCode || "BG1",
        });
        this.profiles = Array.isArray(response?.items) ? response.items : [];
        this.catalogLoaded = true;
        if (this.selected && !this.query)
          this.query = this.selectedLabel(this.selected);
        this.$emit("selected", this.selected);
      } finally {
        this.loading = false;
      }
    },
    selectProfile(profile) {
      this.query = this.selectedLabel(profile);
      this.showSuggestions = false;
      this.$emit("update:modelValue", profile.id);
      this.$emit("selected", profile);
    },
    selectedLabel(profile) {
      return [profile.id, profile.name].filter(Boolean).join(" · ");
    },
    handleQueryInput() {
      if (this.query || !this.modelValue) return;
      this.$emit("update:modelValue", null);
      this.$emit("selected", null);
    },
    hideSuggestions() {
      window.setTimeout(() => {
        this.showSuggestions = false;
      }, 100);
    },
  },
};
</script>

<style scoped>
.consumption-profile-picker__search {
  position: relative;
}
.consumption-profile-picker__suggestions {
  position: absolute;
  z-index: 20;
  top: calc(100% + 2px);
  right: 0;
  left: 0;
  max-height: 15rem;
  overflow-y: auto;
  border: 1px solid #80613d;
  border-radius: 0.25rem;
  background: #21140c;
  color: #f4e3c5;
  box-shadow: 0 0.35rem 0.8rem rgb(0 0 0 / 18%);
}
.consumption-profile-picker__suggestion {
  display: grid;
  width: 100%;
  padding: 0.4rem 0.55rem;
  border: 0;
  border-bottom: 1px solid rgb(244 227 197 / 18%);
  background: transparent;
  color: inherit;
  text-align: left;
}
.consumption-profile-picker__suggestion:hover,
.consumption-profile-picker__suggestion:focus {
  background: rgb(194 145 75 / 22%);
}
.consumption-profile-picker__suggestion small,
.consumption-profile-picker__empty {
  color: #c7b499;
}
.consumption-profile-picker__empty {
  display: block;
  padding: 0.5rem;
}
</style>
