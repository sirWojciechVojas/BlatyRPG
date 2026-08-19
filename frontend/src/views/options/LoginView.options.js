import DashboardLoginPanel from "@/components/dashboard/DashboardLoginPanel.vue";
import { authApiClient } from "@/lib/auth/authApiClient";
import { authErrorKey } from "@/lib/auth/authErrors";
import { postAuthenticationTarget } from "@/lib/auth/authNavigation";
import { authSession } from "@/lib/auth/authSession";
import logo from "@/assets/app-ui/img/BlatyRPG-logo.png";
import background from "@/assets/app-ui/img/bg2.jpg";

export default {
  name: "LoginView",
  components: { DashboardLoginPanel },
  data: () => ({
    logo,
    busy: false,
    error: "",
  }),
  computed: {
    styleVars() {
      return { "--dashboard-background": `url("${background}")` };
    },
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
      this.busy = true;
      this.error = "";
      let sessionSaved = false;
      try {
        const result = await authApiClient.login(credentials);
        if (!result.token || !result.user) {
          throw new TypeError("invalid_login_response");
        }
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
  },
};
