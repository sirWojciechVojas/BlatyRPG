<template>
  <section
    class="dashboard-panel login-panel"
    aria-labelledby="login-title"
    aria-describedby="login-description"
    :aria-busy="busy"
  >
    <header class="login-panel__header">
      <img v-if="logo" class="login-panel__logo" :src="logo" alt="" />
      <p class="eyebrow">{{ $t("dashboard.login.eyebrow") }}</p>
      <h1 id="login-title">{{ $t("dashboard.login.title") }}</h1>
      <p id="login-description" class="panel-copy">
        {{ $t("dashboard.login.description") }}
      </p>
    </header>

    <form
      class="dashboard-form"
      :aria-busy="busy"
      :aria-describedby="error ? 'login-error' : undefined"
      @submit.prevent="submit"
    >
      <label for="login-identifier">
        <span>{{ $t("dashboard.login.identifier") }}</span>
        <input
          id="login-identifier"
          ref="loginInput"
          v-model.trim="login"
          name="login"
          type="text"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          required
          :disabled="busy"
          :aria-describedby="error ? 'login-error' : undefined"
        />
      </label>
      <div class="login-field-group">
        <label for="login-password">
          {{ $t("dashboard.login.password") }}
        </label>
        <span class="login-password-field">
          <input
            id="login-password"
            ref="passwordInput"
            v-model="password"
            name="password"
            :type="passwordVisible ? 'text' : 'password'"
            autocomplete="current-password"
            required
            :disabled="busy"
            :aria-describedby="error ? 'login-error' : undefined"
          />
          <button
            class="login-password-toggle"
            type="button"
            :disabled="busy"
            :aria-pressed="passwordVisible"
            :aria-label="
              $t(
                passwordVisible
                  ? 'auth.actions.hidePassword'
                  : 'auth.actions.showPassword',
              )
            "
            @click="togglePassword"
          >
            {{
              $t(
                passwordVisible
                  ? "auth.actions.hidePassword"
                  : "auth.actions.showPassword",
              )
            }}
          </button>
        </span>
      </div>
      <p v-if="error" id="login-error" class="dashboard-alert" role="alert">
        {{ error }}
      </p>
      <button
        class="primary-action login-submit"
        type="submit"
        :disabled="busy"
        aria-live="polite"
      >
        <span v-if="busy" class="login-submit__spinner" aria-hidden="true" />
        <span>
          {{
            busy
              ? $t("dashboard.login.submitting")
              : $t("dashboard.login.submit")
          }}
        </span>
      </button>
    </form>
    <nav class="login-links" :aria-label="$t('auth.actions.account')">
      <router-link :to="{ name: 'register' }">
        {{ $t("auth.actions.register") }}
      </router-link>
      <router-link :to="{ name: 'password-reset-request' }">
        {{ $t("auth.actions.resetPassword") }}
      </router-link>
    </nav>
  </section>
</template>

<script>
export default {
  name: "DashboardLoginPanel",
  props: {
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
    logo: { type: String, default: "" },
  },
  emits: ["submit"],
  data: () => ({
    login: "",
    password: "",
    passwordVisible: false,
  }),
  watch: {
    error(value) {
      if (!value) return;
      this.$nextTick(() => this.$refs.loginInput?.focus());
    },
  },
  methods: {
    togglePassword() {
      this.passwordVisible = !this.passwordVisible;
      this.$nextTick(() => this.$refs.passwordInput?.focus());
    },
    submit() {
      if (this.busy) return;
      this.$emit("submit", { login: this.login, password: this.password });
    },
  },
};
</script>
