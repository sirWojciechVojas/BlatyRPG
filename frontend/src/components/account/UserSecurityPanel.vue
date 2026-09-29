<template>
  <section class="user-panel-content" aria-labelledby="security-title">
    <div class="user-panel-section-heading">
      <div>
        <p>{{ $t("auth.security.eyebrow") }}</p>
        <h2 id="security-title">{{ $t("auth.security.title") }}</h2>
      </div>
    </div>
    <div class="user-panel-security-grid">
      <form
        class="user-panel-card user-panel-form"
        @submit.prevent="submitPassword"
      >
        <header>
          <h3>{{ $t("auth.password.title") }}</h3>
          <small>{{ $t("auth.password.hint") }}</small>
        </header>
        <label
          ><span>{{ $t("auth.fields.currentPassword") }}</span
          ><input
            v-model="password.currentPassword"
            type="password"
            autocomplete="current-password"
            required
        /></label>
        <label
          ><span>{{ $t("auth.fields.newPassword") }}</span
          ><input
            v-model="password.newPassword"
            type="password"
            autocomplete="new-password"
            minlength="12"
            required
        /></label>
        <label
          ><span>{{ $t("auth.fields.confirmPassword") }}</span
          ><input
            v-model="password.confirmPassword"
            type="password"
            autocomplete="new-password"
            minlength="12"
            required
        /></label>
        <p v-if="mismatch" class="user-panel-inline-error" role="alert">
          {{ $t("auth.errors.passwordMismatch") }}
        </p>
        <button
          class="user-panel-primary"
          type="submit"
          :disabled="busy === 'password'"
        >
          {{ $t("auth.actions.changePassword") }}
        </button>
      </form>
      <article class="user-panel-card user-panel-sessions">
        <header>
          <span
            ><h3>{{ $t("auth.sessions.title") }}</h3>
            <small>{{ $t("auth.sessions.hint") }}</small></span
          >
          <button
            v-if="otherSessions.length"
            type="button"
            :disabled="busy === 'sessions'"
            @click="$emit('revoke-others')"
          >
            {{ $t("auth.sessions.revokeOthers") }}
          </button>
        </header>
        <div
          v-for="session in sessions"
          :key="session.id"
          class="user-panel-session"
        >
          <span class="user-panel-session-icon" aria-hidden="true">▣</span>
          <span>
            <strong>{{
              session.isCurrent
                ? $t("auth.sessions.current")
                : $t("auth.sessions.browser")
            }}</strong>
            <small
              >{{ $t("auth.sessions.lastActive") }}:
              {{ formatDate(session.lastSeenAt) }} ·
              {{ $t("auth.sessions.expires") }}:
              {{ formatDate(session.expiresAt) }}</small
            >
          </span>
          <em v-if="session.isCurrent">{{ $t("auth.sessions.active") }}</em>
          <button
            v-else
            type="button"
            :disabled="busy === `session-${session.id}`"
            @click="$emit('revoke-session', session.id)"
          >
            {{ $t("auth.sessions.revoke") }}
          </button>
        </div>
        <p v-if="!sessions.length">{{ $t("auth.sessions.empty") }}</p>
      </article>
      <article class="user-panel-card user-panel-connections">
        <header>
          <h3>{{ $t("auth.oauth.connectionsTitle") }}</h3>
          <small>{{ $t("auth.oauth.connectionsHint") }}</small>
        </header>
        <OAuthProviderButtons
          :providers="connectionProviders"
          :disabled="busy.startsWith('oauth-')"
          mode="link"
          label-key="auth.oauth.connectionsTitle"
          @select="$emit('link-oauth', $event)"
        />
      </article>
    </div>
  </section>
</template>

<script>
import OAuthProviderButtons from "@/components/account/OAuthProviderButtons.vue";

export default {
  name: "UserSecurityPanel",
  components: { OAuthProviderButtons },
  props: {
    sessions: { type: Array, default: () => [] },
    busy: { type: String, default: "" },
    oauthProviders: { type: Array, default: () => [] },
    oauthIdentities: { type: Array, default: () => [] },
  },
  emits: ["change-password", "revoke-session", "revoke-others", "link-oauth"],
  data: () => ({
    password: { currentPassword: "", newPassword: "", confirmPassword: "" },
    mismatch: false,
  }),
  computed: {
    otherSessions() {
      return this.sessions.filter((item) => !item.isCurrent);
    },
    connectionProviders() {
      const linked = new Map(
        this.oauthIdentities.map((identity) => [identity.provider, identity]),
      );
      return this.oauthProviders.map((provider) => {
        const identity = linked.get(provider.provider);
        return {
          ...provider,
          baseLabel: provider.label,
          email: identity?.email || "",
          linked: Boolean(identity),
          enabled: provider.enabled && !identity,
        };
      });
    },
  },
  methods: {
    submitPassword() {
      this.mismatch =
        this.password.newPassword !== this.password.confirmPassword;
      if (this.mismatch) return;
      this.$emit("change-password", { ...this.password });
      this.password = {
        currentPassword: "",
        newPassword: "",
        confirmPassword: "",
      };
    },
    formatDate(value) {
      if (!value) return this.$t("auth.sessions.unknown");
      const source = String(value);
      const normalized = source.includes("T")
        ? source
        : `${source.replace(" ", "T")}Z`;
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) return value;
      return new Intl.DateTimeFormat(this.$i18n.locale, {
        dateStyle: "short",
        timeStyle: "short",
      }).format(date);
    },
  },
};
</script>
