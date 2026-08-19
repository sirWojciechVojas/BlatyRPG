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
        <input
          v-model.trim="draft.systemType"
          maxlength="50"
          pattern="[A-Za-z0-9][A-Za-z0-9_-]{0,49}"
          required
        />
      </label>
      <label class="wide-field">
        <span>{{ $t("dashboard.create.description") }}</span>
        <textarea v-model.trim="draft.description" maxlength="10000" rows="3" />
      </label>
      <p v-if="error" class="dashboard-alert wide-field" role="alert">
        {{ error }}
      </p>
      <button class="primary-action" type="submit" :disabled="busy">
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
  systemType: "wfrp2ed",
});

export default {
  name: "CampaignCreateForm",
  props: {
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  emits: ["submit"],
  data: () => ({ draft: emptyDraft() }),
  methods: {
    submit() {
      this.$emit("submit", { ...this.draft });
    },
    reset() {
      this.draft = emptyDraft();
    },
  },
};
</script>
