<template>
  <main class="auth-page">
    <section class="auth-panel">
      <h1>{{ $t("auth.resetConfirm.title") }}</h1>
      <form @submit.prevent="submit">
        <label>
          <span>{{ $t("auth.fields.password") }}</span>
          <input
            v-model="password"
            type="password"
            autocomplete="new-password"
            minlength="12"
            required
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.confirmPassword") }}</span>
          <input
            v-model="confirmPassword"
            type="password"
            autocomplete="new-password"
            minlength="12"
            required
          />
        </label>
        <p v-if="error" class="auth-error" role="alert">{{ error }}</p>
        <button type="submit" :disabled="busy">
          {{
            busy ? $t("auth.actions.saving") : $t("auth.actions.setPassword")
          }}
        </button>
      </form>
    </section>
  </main>
</template>

<script>
import { authApiClient } from "@/lib/auth/authApiClient";
import { authErrorKey } from "@/lib/auth/authErrors";
import { authSession } from "@/lib/auth/authSession";

export default {
  name: "PasswordResetConfirmView",
  data: () => ({ password: "", confirmPassword: "", busy: false, error: "" }),
  methods: {
    async submit() {
      this.error = "";
      if (this.password !== this.confirmPassword) {
        this.error = this.$t("auth.errors.passwordMismatch");
        return;
      }
      const token = String(this.$route.query.token || "");
      if (!token) {
        this.error = this.$t("auth.errors.resetToken");
        return;
      }
      this.busy = true;
      try {
        const result = await authApiClient.confirmReset({
          token,
          password: this.password,
          confirmPassword: this.confirmPassword,
        });
        authSession.save(result);
        await this.$router.replace({ name: "home" });
      } catch (error) {
        this.error = this.$t(authErrorKey(error, "auth.errors.reset"));
      } finally {
        this.busy = false;
      }
    },
  },
};
</script>

<style src="./styles/AuthViews.css" />
