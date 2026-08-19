<template>
  <section class="dashboard-panel create-panel" aria-labelledby="create-title">
    <div class="section-heading">
      <div>
        <p class="eyebrow">{{ $t("dashboard.create.eyebrow") }}</p>
        <h2 id="create-title">{{ $t("dashboard.create.title") }}</h2>
      </div>
    </div>
    <form class="dashboard-form create-form" @submit.prevent="submit">
      <label>
        <span>{{ $t("dashboard.create.name") }}</span>
        <input v-model.trim="draft.name" maxlength="255" required />
      </label>
      <label>
        <span>{{ $t("dashboard.create.system") }}</span>
        <select v-model.number="draft.systemId" required>
          <option disabled :value="null">
            {{ $t("dashboard.create.selectSystem") }}
          </option>
          <option v-for="system in systems" :key="system.id" :value="system.id">
            {{ system.name }}
          </option>
        </select>
      </label>
      <label>
        <span>{{ $t("dashboard.create.world") }}</span>
        <select v-model.number="draft.universeId" required>
          <option disabled :value="null">
            {{ $t("dashboard.create.selectWorld") }}
          </option>
          <option
            v-for="universe in universes"
            :key="universe.id"
            :value="universe.id"
          >
            {{ universe.name }}
          </option>
        </select>
      </label>
      <label class="wide-field">
        <span>{{ $t("dashboard.create.description") }}</span>
        <textarea v-model.trim="draft.description" maxlength="10000" rows="3" />
      </label>
      <p v-if="!games.length" class="dashboard-alert wide-field" role="status">
        {{ $t("dashboard.create.catalogEmpty") }}
      </p>
      <p v-if="error" class="dashboard-alert wide-field" role="alert">
        {{ error }}
      </p>
      <button
        class="primary-action"
        type="submit"
        :disabled="busy || !draft.systemId || !draft.universeId"
      >
        {{
          busy
            ? $t("dashboard.create.submitting")
            : $t("dashboard.create.submit")
        }}
      </button>
    </form>
  </section>
</template>

<script>
const emptyDraft = () => ({
  name: "",
  description: "",
  systemId: null,
  universeId: null,
});

export default {
  name: "CampaignCreateForm",
  props: {
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
    games: { type: Array, default: () => [] },
  },
  emits: ["submit"],
  data: () => ({ draft: emptyDraft() }),
  computed: {
    systems() {
      const systems = new Map();
      for (const game of this.games) {
        if (!systems.has(game.systemId)) {
          systems.set(game.systemId, {
            id: game.systemId,
            name: game.systemName || game.systemCode,
          });
        }
      }
      return [...systems.values()].sort((left, right) =>
        left.name.localeCompare(right.name),
      );
    },
    universes() {
      return this.games
        .filter((game) => game.systemId === this.draft.systemId)
        .map((game) => ({
          id: game.universeId,
          name: game.universeName || game.universeCode,
        }))
        .sort((left, right) => left.name.localeCompare(right.name));
    },
  },
  watch: {
    games: { handler: "ensureSelection", immediate: true },
    "draft.systemId": "selectFirstUniverse",
  },
  methods: {
    ensureSelection() {
      if (!this.systems.some(({ id }) => id === this.draft.systemId)) {
        this.draft.systemId = this.systems[0]?.id ?? null;
      }
      this.selectFirstUniverse();
    },
    selectFirstUniverse() {
      if (!this.universes.some(({ id }) => id === this.draft.universeId)) {
        this.draft.universeId = this.universes[0]?.id ?? null;
      }
    },
    submit() {
      if (!this.draft.systemId || !this.draft.universeId) return;
      this.$emit("submit", { ...this.draft });
    },
    reset() {
      this.draft = emptyDraft();
      this.ensureSelection();
    },
  },
};
</script>
