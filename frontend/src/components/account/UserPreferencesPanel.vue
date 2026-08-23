<template>
  <section class="user-panel-content" aria-labelledby="preferences-title">
    <div class="user-panel-section-heading">
      <div>
        <p>{{ $t("auth.preferences.eyebrow") }}</p>
        <h2 id="preferences-title">{{ $t("auth.preferences.title") }}</h2>
      </div>
    </div>
    <div class="user-panel-card user-panel-preferences">
      <label>
        <span
          ><strong>{{ $t("auth.preferences.language") }}</strong
          ><small>{{ $t("auth.preferences.languageHint") }}</small></span
        >
        <select
          :value="currentLocale"
          @change="$emit('change-locale', $event.target.value)"
        >
          <option
            v-for="locale in locales"
            :key="locale.code"
            :value="locale.code"
          >
            {{ locale.label }}
          </option>
        </select>
      </label>
      <div>
        <span
          ><strong>{{ $t("auth.preferences.appearance") }}</strong
          ><small>{{ $t("auth.preferences.appearanceHint") }}</small></span
        >
        <em>{{ $t("auth.preferences.compactBrown") }}</em>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "UserPreferencesPanel",
  props: {
    locales: { type: Array, default: () => [] },
    currentLocale: { type: String, required: true },
  },
  emits: ["change-locale"],
};
</script>
