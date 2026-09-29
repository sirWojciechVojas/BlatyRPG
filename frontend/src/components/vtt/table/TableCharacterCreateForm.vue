<template>
  <form class="table-character-create" @submit.prevent="submit">
    <header>
      <div>
        <small>{{ $t("characters.create.eyebrow") }}</small>
        <strong>{{ $t("characters.create.title") }}</strong>
      </div>
      <button
        type="button"
        :disabled="busy"
        :title="$t('characters.actions.cancel')"
        :aria-label="$t('characters.actions.cancel')"
        @click="$emit('cancel')"
      >
        ×
      </button>
    </header>
    <p>
      {{ $t("characters.create.campaignContext", { name: campaign.name }) }}
    </p>
    <p v-if="error" class="table-character-create__error" role="alert">
      {{ error }}
    </p>
    <label>
      <span>{{ $t("characters.fields.name") }}</span>
      <input
        ref="name"
        v-model.trim="name"
        required
        minlength="2"
        maxlength="150"
        :disabled="busy"
        autocomplete="off"
      />
    </label>
    <footer>
      <button type="button" :disabled="busy" @click="$emit('cancel')">
        {{ $t("characters.actions.cancel") }}
      </button>
      <button type="submit" class="primary" :disabled="busy || name.length < 2">
        {{
          busy
            ? $t("characters.loading.creating")
            : $t("characters.actions.create")
        }}
      </button>
    </footer>
  </form>
</template>

<script>
export default {
  name: "TableCharacterCreateForm",
  props: {
    campaign: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  emits: ["cancel", "create"],
  data: () => ({ name: "" }),
  mounted() {
    this.$refs.name?.focus();
  },
  methods: {
    submit() {
      if (this.busy || this.name.length < 2) return;
      this.$emit("create", {
        name: this.name,
        avatarUrl: "",
        data: {
          details: {},
          attributes: { actual: {}, skills: [], talents: [] },
        },
      });
    },
  },
};
</script>
