<template>
  <section class="dashboard-panel login-panel" aria-labelledby="login-title">
    <p class="eyebrow">{{ $t("dashboard.login.eyebrow") }}</p>
    <h1 id="login-title">{{ $t("dashboard.login.title") }}</h1>
    <p class="panel-copy">{{ $t("dashboard.login.description") }}</p>

    <form class="dashboard-form" @submit.prevent="submit">
      <label>
        <span>{{ $t("dashboard.login.identifier") }}</span>
        <input
          v-model.trim="login"
          name="login"
          type="text"
          autocomplete="username"
          required
          :disabled="busy"
        />
      </label>
      <label>
        <span>{{ $t("dashboard.login.password") }}</span>
        <input
          v-model="password"
          name="password"
          type="password"
          autocomplete="current-password"
          minlength="5"
          required
          :disabled="busy"
        />
      </label>
      <p v-if="error" class="dashboard-alert" role="alert">{{ error }}</p>
      <button class="primary-action" type="submit" :disabled="busy">
        {{
          busy ? $t("dashboard.login.submitting") : $t("dashboard.login.submit")
        }}
      </button>
    </form>
  </section>
</template>

<script>
export default {
  name: "DashboardLoginPanel",
  props: {
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  emits: ["submit"],
  data: () => ({ login: "", password: "" }),
  methods: {
    submit() {
      this.$emit("submit", { login: this.login, password: this.password });
    },
  },
};
</script>
