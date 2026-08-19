<template>
  <main class="auth-page">
    <section class="auth-panel">
      <h1>{{ $t("auth.profile.title") }}</h1>
      <form @submit.prevent="saveProfile">
        <label>
          <span>{{ $t("auth.fields.username") }}</span>
          <input
            v-model.trim="profile.username"
            required
            minlength="3"
            maxlength="100"
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.email") }}</span>
          <input
            v-model.trim="profile.email"
            type="email"
            required
            maxlength="255"
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.avatar") }}</span>
          <input v-model.trim="profile.avatarUrl" type="text" maxlength="255" />
        </label>
        <button type="submit" :disabled="busy">
          {{ $t("auth.actions.saveProfile") }}
        </button>
      </form>
      <h2>{{ $t("auth.password.title") }}</h2>
      <form @submit.prevent="changePassword">
        <label>
          <span>{{ $t("auth.fields.currentPassword") }}</span>
          <input
            v-model="password.currentPassword"
            type="password"
            autocomplete="current-password"
            required
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.newPassword") }}</span>
          <input
            v-model="password.newPassword"
            type="password"
            autocomplete="new-password"
            minlength="12"
            required
          />
        </label>
        <label>
          <span>{{ $t("auth.fields.confirmPassword") }}</span>
          <input
            v-model="password.confirmPassword"
            type="password"
            autocomplete="new-password"
            minlength="12"
            required
          />
        </label>
        <button type="submit" :disabled="busy">
          {{ $t("auth.actions.changePassword") }}
        </button>
      </form>
      <p v-if="error" class="auth-error" role="alert">{{ error }}</p>
      <p v-if="notice" role="status">{{ notice }}</p>
    </section>
  </main>
</template>

<script>
import { authApiClient } from "@/lib/auth/authApiClient";
import { authErrorKey } from "@/lib/auth/authErrors";
import { authSession } from "@/lib/auth/authSession";

export default {
  name: "ProfileView",
  data: () => ({
    profile: { username: "", email: "", avatarUrl: "" },
    password: { currentPassword: "", newPassword: "", confirmPassword: "" },
    busy: false,
    error: "",
    notice: "",
  }),
  mounted() {
    const user = authSession.read()?.user;
    if (!user) return this.$router.replace({ name: "home" });
    this.profile = { ...this.profile, ...user };
  },
  methods: {
    async perform(operation, successKey) {
      this.busy = true;
      this.error = "";
      this.notice = "";
      try {
        await operation();
        this.notice = this.$t(successKey);
      } catch (error) {
        this.error = this.$t(authErrorKey(error));
      } finally {
        this.busy = false;
      }
    },
    saveProfile() {
      return this.perform(async () => {
        const user = await authApiClient.updateProfile(this.profile);
        authSession.updateUser(user);
        this.profile = { ...this.profile, ...user };
      }, "auth.profile.saved");
    },
    changePassword() {
      if (this.password.newPassword !== this.password.confirmPassword) {
        this.error = this.$t("auth.errors.passwordMismatch");
        return;
      }
      return this.perform(async () => {
        const result = await authApiClient.changePassword(this.password);
        authSession.save(result);
        this.password = {
          currentPassword: "",
          newPassword: "",
          confirmPassword: "",
        };
      }, "auth.password.changed");
    },
  },
};
</script>

<style src="./styles/AuthViews.css" />
