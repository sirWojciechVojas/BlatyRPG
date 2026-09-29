<template>
  <section class="user-panel-content" aria-labelledby="profile-details-title">
    <div class="user-panel-section-heading">
      <div>
        <p>{{ $t("auth.profile.eyebrow") }}</p>
        <h2 id="profile-details-title">{{ $t("auth.profile.details") }}</h2>
      </div>
    </div>
    <form class="user-panel-form" @submit.prevent="$emit('save', draft)">
      <div class="user-panel-form-grid">
        <label>
          <span>{{ $t("auth.fields.username") }}</span>
          <input
            v-model.trim="draft.username"
            required
            minlength="3"
            maxlength="100"
            autocomplete="username"
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.email") }}</span>
          <input
            v-model.trim="draft.email"
            type="email"
            required
            maxlength="255"
            autocomplete="email"
          />
        </label>
        <label class="user-panel-form-wide">
          <span>{{ $t("auth.fields.avatar") }}</span>
          <input
            v-model.trim="draft.avatarUrl"
            type="url"
            maxlength="255"
            placeholder="https://…"
          />
        </label>
      </div>
      <p class="user-panel-help">{{ $t("auth.profile.avatarHelp") }}</p>
      <button class="user-panel-primary" type="submit" :disabled="busy">
        {{ busy ? $t("auth.actions.saving") : $t("auth.actions.saveProfile") }}
      </button>
    </form>
  </section>
</template>

<script>
export default {
  name: "UserProfileForm",
  props: {
    user: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["save"],
  data: () => ({ draft: { username: "", email: "", avatarUrl: "" } }),
  watch: {
    user: {
      immediate: true,
      deep: true,
      handler(user) {
        this.draft = {
          username: user.username || "",
          email: user.email || "",
          avatarUrl: user.avatarUrl || "",
        };
      },
    },
  },
};
</script>
