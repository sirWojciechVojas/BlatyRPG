import CampaignCard from "@/components/dashboard/CampaignCard.vue";
import CampaignCreateForm from "@/components/dashboard/CampaignCreateForm.vue";
import DashboardLoginPanel from "@/components/dashboard/DashboardLoginPanel.vue";
import { authApiClient } from "@/lib/auth/authApiClient";
import { authSession } from "@/lib/auth/authSession";
import { campaignApiClient } from "@/lib/campaign/campaignApiClient";
import { availableLocales, setLocale } from "@/i18n";
import { isJsonApiAuthorizationError } from "@/lib/api/jsonApiClient";
import { safeRedirectTarget } from "@/router/authGuard";
import logo from "@/assets/app-ui/img/BlatyRPG-logo.png";
import background from "@/assets/app-ui/img/bg2.jpg";

const sortCampaigns = (campaigns) =>
  [...campaigns].sort(
    (left, right) =>
      Number(right.isActive) - Number(left.isActive) ||
      left.name.localeCompare(right.name),
  );

export default {
  name: "DashboardHomeView",
  components: { CampaignCard, CampaignCreateForm, DashboardLoginPanel },
  data: () => ({
    logo,
    session: null,
    campaigns: [],
    canCreateCampaign: false,
    isRestoring: true,
    isLoading: false,
    isLoggingIn: false,
    isCreating: false,
    loginError: "",
    dashboardError: "",
    creationError: "",
    locales: availableLocales,
  }),
  computed: {
    currentLocale: {
      get() {
        return typeof this.$i18n.locale === "string"
          ? this.$i18n.locale
          : this.$i18n.locale.value;
      },
      set(locale) {
        setLocale(locale);
      },
    },
    displayName() {
      return this.session?.user?.username || this.session?.user?.email || "";
    },
    styleVars() {
      return { "--dashboard-background": `url("${background}")` };
    },
  },
  async mounted() {
    this.session = authSession.read();
    if (this.session) await this.loadDashboard();
    this.isRestoring = false;
  },
  methods: {
    errorMessage(error, fallbackKey) {
      if (error?.network) return this.$t("dashboard.errors.network");
      if (error?.status === 401)
        return this.$t("dashboard.errors.invalidCredentials");
      if (error?.status === 403) return this.$t("dashboard.errors.forbidden");
      if (error?.status === 422) return this.$t("dashboard.errors.validation");
      return this.$t(fallbackKey);
    },
    async login(credentials) {
      this.isLoggingIn = true;
      this.loginError = "";
      try {
        const result = await authApiClient.login(credentials);
        if (!result.token || !result.user)
          throw new TypeError("invalid_login_response");
        this.session = authSession.save(result);
        const redirect = safeRedirectTarget(
          this.$router,
          this.$route.query.redirect,
        );
        if (redirect) {
          await this.$router.replace(redirect);
          return;
        }
        await this.loadDashboard();
      } catch (error) {
        authSession.clear();
        this.session = null;
        this.loginError = this.errorMessage(error, "dashboard.errors.login");
      } finally {
        this.isLoggingIn = false;
      }
    },
    async loadDashboard() {
      this.isLoading = true;
      this.dashboardError = "";
      try {
        const [user, directory] = await Promise.all([
          authApiClient.me(),
          campaignApiClient.list(),
        ]);
        this.session = authSession.updateUser(user);
        this.campaigns = sortCampaigns(directory.campaigns);
        this.canCreateCampaign = directory.capabilities.canCreate;
      } catch (error) {
        if (isJsonApiAuthorizationError(error) || error?.status === 404) {
          this.logout();
          this.loginError = this.$t("dashboard.errors.sessionExpired");
        } else {
          this.dashboardError = this.errorMessage(
            error,
            "dashboard.errors.campaigns",
          );
        }
      } finally {
        this.isLoading = false;
      }
    },
    async createCampaign(draft) {
      this.isCreating = true;
      this.creationError = "";
      try {
        const campaign = await campaignApiClient.create(draft);
        this.campaigns = sortCampaigns([...this.campaigns, campaign]);
        this.$refs.createForm?.reset();
      } catch (error) {
        if (error?.status === 401) {
          this.logout();
          this.loginError = this.$t("dashboard.errors.sessionExpired");
          return;
        }
        this.creationError = this.errorMessage(
          error,
          "dashboard.errors.create",
        );
      } finally {
        this.isCreating = false;
      }
    },
    logout() {
      authSession.clear();
      this.session = null;
      this.campaigns = [];
      this.canCreateCampaign = false;
      this.dashboardError = "";
      if (this.$route.name !== "home") this.$router.replace({ name: "home" });
    },
  },
};
