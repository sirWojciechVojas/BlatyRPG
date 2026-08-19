<template>
  <main class="auth-page">
    <section class="auth-panel">
      <h1>{{ $t("auth.resetRequest.title") }}</h1>
      <p>{{ $t("auth.resetRequest.description") }}</p>
      <form @submit.prevent="submit">
        <label>
          <span>{{ $t("auth.fields.email") }}</span>
          <input
            v-model.trim="email"
            type="email"
            autocomplete="email"
            required
            maxlength="255"
          />
        </label>
        <p v-if="error" class="auth-error" role="alert">{{ error }}</p>
        <p v-if="sent" role="status">{{ $t("auth.resetRequest.sent") }}</p>
        <button type="submit" :disabled="busy">
          {{ busy ? $t("auth.actions.sending") : $t("auth.actions.sendReset") }}
        </button>
      </form>
    </section>
  </main>
</template>

<script>
import { authApiClient } from "@/lib/auth/authApiClient";
import { authErrorKey } from "@/lib/auth/authErrors";

export default {
  name: "PasswordResetRequestView",
  data: () => ({ email: "", busy: false, error: "", sent: false }),
  methods: {
    async submit() {
      this.busy = true;
      this.error = "";
      try {
        await authApiClient.requestReset({ email: this.email });
        this.sent = true;
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
