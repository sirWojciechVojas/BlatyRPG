<template>
  <main class="oauth-callback-page" :aria-busy="busy">
    <section class="oauth-callback-card" role="status">
      <span v-if="busy" class="login-submit__spinner" aria-hidden="true" />
      <h1>{{ $t("auth.oauth.completing") }}</h1>
      <p v-if="error" role="alert">{{ error }}</p>
    </section>
  </main>
</template>

<script>
import { authApiClient } from "@/lib/auth/authApiClient";
import { postAuthenticationTarget } from "@/lib/auth/authNavigation";
import { preloadAuthenticatedView } from "@/lib/auth/authRouteComponents";
import { authSession } from "@/lib/auth/authSession";

export default {
  name: "OAuthCallbackView",
  data: () => ({ busy: true, error: "" }),
  async mounted() {
    const query = this.$route.query || {};
    if (query.oauthStatus === "linked") {
      await this.$router.replace({
        name: "profile",
        query: { tab: "security", oauth: "linked", provider: query.provider },
      });
      return;
    }
    if (query.oauthError) {
      const target = authSession.read()
        ? {
            name: "profile",
            query: {
              tab: "security",
              oauthError: query.oauthError,
              provider: query.provider,
            },
          }
        : { name: "login", query: { oauthError: query.oauthError } };
      await this.$router.replace(target);
      return;
    }
    try {
      const result = await authApiClient.exchangeOAuthCode(query.code);
      if (!result.token || !result.user)
        throw new TypeError("invalid_login_response");
      await preloadAuthenticatedView({ user: result.user }).catch(() => {});
      const session = authSession.save(result);
      await this.$router.replace(
        postAuthenticationTarget(this.$router, session, query.redirect),
      );
    } catch (_error) {
      authSession.clear("oauth_failed");
      await this.$router.replace({
        name: "login",
        query: { oauthError: "oauth_exchange_failed" },
      });
    } finally {
      this.busy = false;
    }
  },
};
</script>

<style scoped>
.oauth-callback-page {
  display: grid;
  min-height: calc(100dvh - var(--ui-navigation-height, 3.5rem));
  place-items: center;
  background: #120a08;
  color: #f6efe2;
}
.oauth-callback-card {
  text-align: center;
}
.oauth-callback-card .login-submit__spinner {
  display: inline-block;
  border-color: rgba(216, 183, 120, 0.3);
  border-top-color: #d8b778;
}
</style>
