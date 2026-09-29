import DashboardLoginPanel from "@/components/dashboard/DashboardLoginPanel.vue";
import { authApiClient } from "@/lib/auth/authApiClient";
import { authErrorKey } from "@/lib/auth/authErrors";
import { postAuthenticationTarget } from "@/lib/auth/authNavigation";
import { authSession } from "@/lib/auth/authSession";
import {
  preloadAuthenticatedView,
  preloadDefaultAuthenticatedView,
} from "@/lib/auth/authRouteComponents";
import logo from "@/assets/app-ui/img/BlatyRPG-logo.png";
import background from "@/assets/app-ui/img/niceBg.webp";

export default {
  name: "LoginView",
  components: { DashboardLoginPanel },
  data: () => ({
    logo,
    busy: false,
    error: "",
    oauthProviders: [],
    oauthBusy: false,
  }),
  computed: {
    styleVars() {
      return { "--dashboard-background": `url("${background}")` };
    },
  },
  mounted() {
    preloadDefaultAuthenticatedView().catch(() => {});
    this.loadOAuthProviders();
    if (this.$route?.query?.oauthError) {
      this.error = this.$t(`auth.oauth.errors.${this.$route.query.oauthError}`);
    }
  },
  methods: {
    message(error, sessionSaved) {
      if (sessionSaved) return this.$t("auth.errors.navigation");
      if (error?.code === "session_storage_unavailable") {
        return this.$t("auth.errors.sessionStorage");
      }
      return this.$t(authErrorKey(error, "auth.errors.login"));
    },
    async login(credentials) {
      if (this.busy) return;
      this.busy = true;
      this.error = "";
      let sessionSaved = false;
      try {
        const result = await authApiClient.login(credentials);
        if (!result.token || !result.user) {
          throw new TypeError("invalid_login_response");
        }
        await preloadAuthenticatedView({ user: result.user }).catch(() => {});
        const session = authSession.save(result);
        sessionSaved = true;
        await this.$router.replace(
          postAuthenticationTarget(
            this.$router,
            session,
            this.$route?.query?.redirect,
          ),
        );
      } catch (error) {
        if (!sessionSaved) authSession.clear();
        this.error = this.message(error, sessionSaved);
      } finally {
        this.busy = false;
      }
    },
    async loadOAuthProviders() {
      try {
        this.oauthProviders = await authApiClient.oauthProviders();
      } catch (_error) {
        this.oauthProviders = [];
      }
    },
    async startOAuth(provider) {
      if (this.busy || this.oauthBusy) return;
      this.oauthBusy = true;
      this.error = "";
      try {
        const authorizationUrl = await authApiClient.startOAuth(provider);
        window.location.assign(authorizationUrl);
      } catch (error) {
        this.error = this.message(error, false);
        this.oauthBusy = false;
      }
    },
  },
};
