<template>
  <main class="auth-page">
    <section class="auth-panel">
      <h1>{{ $t("auth.register.title") }}</h1>
      <form @submit.prevent="submit">
        <label>
          <span>{{ $t("auth.fields.username") }}</span>
          <input
            v-model.trim="form.username"
            autocomplete="username"
            required
            minlength="3"
            maxlength="100"
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.email") }}</span>
          <input
            v-model.trim="form.email"
            type="email"
            autocomplete="email"
            required
            maxlength="255"
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.password") }}</span>
          <input
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required
            minlength="12"
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.confirmPassword") }}</span>
          <input
            v-model="form.confirmPassword"
            type="password"
            autocomplete="new-password"
            required
            minlength="12"
          />
        </label>
        <p v-if="error" class="auth-error" role="alert">{{ error }}</p>
        <p v-if="success" role="status">{{ $t("auth.register.success") }}</p>
        <button type="submit" :disabled="busy">
          {{ busy ? $t("auth.actions.saving") : $t("auth.actions.register") }}
        </button>
      </form>
      <router-link :to="{ name: 'home' }">{{
        $t("auth.actions.signIn")
      }}</router-link>
    </section>
  </main>
</template>

<script>
import { authApiClient } from "@/lib/auth/authApiClient";
import { authErrorKey } from "@/lib/auth/authErrors";
import { authSession } from "@/lib/auth/authSession";

export default {
  name: "RegisterView",
  data: () => ({
    form: { username: "", email: "", password: "", confirmPassword: "" },
    busy: false,
    error: "",
    success: false,
  }),
  methods: {
    async submit() {
      this.error = "";
      if (this.form.password !== this.form.confirmPassword) {
        this.error = this.$t("auth.errors.passwordMismatch");
        return;
      }
      this.busy = true;
      try {
        const result = await authApiClient.register(this.form);
        if (result.token) authSession.save(result);
        this.success = true;
        await this.$router.replace({
          name: "home",
          query: { registered: "1" },
        });
      } catch (error) {
        this.error = this.$t(authErrorKey(error, "auth.errors.register"));
      } finally {
        this.busy = false;
      }
    },
  },
};
</script>

<style src="./styles/AuthViews.css" />
