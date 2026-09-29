import CampaignCarousel from "@/components/dashboard/CampaignCarousel.vue";
import CampaignCreateForm from "@/components/dashboard/CampaignCreateForm.vue";
import { authApiClient } from "@/lib/auth/authApiClient";
import { authSession } from "@/lib/auth/authSession";
import { campaignApiClient } from "@/lib/campaign/campaignApiClient";
import { gameCatalogApiClient } from "@/lib/catalog/gameCatalogApiClient";

const sortCampaigns = (campaigns) =>
  [...campaigns].sort(
    (left, right) =>
      Number(right.isActive) - Number(left.isActive) ||
      left.name.localeCompare(right.name),
  );

export default {
  name: "DashboardHomeView",
  components: { CampaignCarousel, CampaignCreateForm },
  data: () => ({
    session: null,
    campaigns: [],
    games: [],
    canCreateCampaign: false,
    isLoading: false,
    isCreating: false,
    dashboardError: "",
    creationError: "",
    showCreateForm: false,
    carouselRange: { start: 0, end: 0, visible: 0, total: 0 },
    unsubscribeAuth: null,
  }),
  computed: {
    displayName() {
      return this.session?.user?.username || this.session?.user?.email || "";
    },
  },
  async mounted() {
    this.unsubscribeAuth = authSession.subscribe(
      (session) => {
        this.session = session;
        if (session) return;
        this.campaigns = [];
        this.canCreateCampaign = false;
      },
      { immediate: false },
    );
    this.session = authSession.read();
    await this.loadDashboard();
  },
  beforeUnmount() {
    this.unsubscribeAuth?.();
  },
  methods: {
    errorMessage(error, fallbackKey) {
      if (error?.network) return this.$t("dashboard.errors.network");
      if (error?.code === "session_storage_unavailable")
        return this.$t("dashboard.errors.sessionStorage");
      if (error?.status === 401)
        return this.$t("dashboard.errors.invalidCredentials");
      if (error?.status === 403) return this.$t("dashboard.errors.forbidden");
      if (error?.status === 422) return this.$t("dashboard.errors.validation");
      if (error?.status === 429) return this.$t("dashboard.errors.rateLimited");
      return this.$t(fallbackKey);
    },
    async loadDashboard() {
      this.isLoading = true;
      this.dashboardError = "";
      try {
        const [user, directory, games] = await Promise.all([
          authApiClient.me(),
          campaignApiClient.list(),
          gameCatalogApiClient.listGames(),
        ]);
        this.session = authSession.updateUser(user);
        this.campaigns = sortCampaigns(directory.campaigns);
        this.canCreateCampaign = directory.capabilities.canCreate;
        this.games = games;
      } catch (error) {
        if ([401, 403].includes(error?.status)) {
          await this.logout();
          return;
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
        this.closeCreateCampaign(true);
        await this.$nextTick();
        this.$refs.carousel?.scrollToCampaign(campaign.id);
      } catch (error) {
        if (error?.status === 401) {
          await this.logout();
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
    openCreateCampaign() {
      if (!this.canCreateCampaign || this.isLoading) return;
      this.creationError = "";
      this.showCreateForm = true;
      this.$nextTick(() => {
        const dialog = this.$refs.createDialog;
        const firstField = dialog?.querySelector("input, select, textarea");
        (firstField || dialog)?.focus();
      });
    },
    closeCreateCampaign(force = false) {
      if (this.isCreating && !force) return;
      const wasOpen = this.showCreateForm;
      this.showCreateForm = false;
      this.creationError = "";
      if (wasOpen) {
        this.$nextTick(() => this.$refs.newCampaignButton?.focus());
      }
    },
    handleRangeChange(range) {
      this.carouselRange = range;
    },
    async logout() {
      try {
        if (authSession.read()) await authApiClient.logout();
      } catch (_error) {
        // Local logout remains mandatory when the server is unreachable.
      } finally {
        authSession.clear("logout");
        this.session = null;
        this.campaigns = [];
        this.canCreateCampaign = false;
        this.dashboardError = "";
        if (this.$route.name !== "landing") {
          await this.$router.replace({ name: "landing" });
        }
      }
    },
  },
};
