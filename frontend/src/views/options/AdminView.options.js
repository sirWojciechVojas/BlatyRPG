import AdminActivityTab from "@/components/admin/AdminActivityTab.vue";
import AdminCampaignsTab from "@/components/admin/AdminCampaignsTab.vue";
import AdminOverviewTab from "@/components/admin/AdminOverviewTab.vue";
import AdminSystemTab from "@/components/admin/AdminSystemTab.vue";
import AdminUsersTab from "@/components/admin/AdminUsersTab.vue";
import AdminIcon from "@/components/admin/AdminIcon.vue";
import { adminApiClient } from "@/lib/admin/adminApiClient";
import { authSession } from "@/lib/auth/authSession";

const emptyAnalytics = () => ({
  accountRoles: [],
  campaignStatuses: [],
  membershipRoles: [],
  systems: [],
  growth: [],
});

export default {
  name: "AdminView",
  components: {
    AdminActivityTab,
    AdminCampaignsTab,
    AdminOverviewTab,
    AdminSystemTab,
    AdminUsersTab,
    AdminIcon,
  },
  data: () => ({
    activeTab: "overview",
    users: [],
    campaigns: [],
    activity: [],
    analytics: emptyAnalytics(),
    system: {},
    metrics: {
      users: 0,
      admins: 0,
      campaigns: 0,
      activeCampaigns: 0,
      memberships: 0,
      activeSessions: 0,
    },
    currentUserId: 0,
    loading: true,
    creating: false,
    busyUserId: 0,
    error: "",
    createError: "",
    roleError: "",
  }),
  computed: {
    tabs() {
      return [
        { id: "overview", icon: "overview", count: null },
        { id: "users", icon: "users", count: this.metrics.users },
        { id: "campaigns", icon: "campaigns", count: this.metrics.campaigns },
        { id: "activity", icon: "activity", count: this.activity.length },
        { id: "system", icon: "system", count: null },
      ].map((tab) => ({ ...tab, label: this.$t(`admin.tabs.${tab.id}`) }));
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatTime(value) {
      return value
        ? new Intl.DateTimeFormat(this.$i18n.locale, {
            hour: "2-digit",
            minute: "2-digit",
          }).format(new Date(value))
        : "";
    },
    message(error, fallback) {
      if (error?.network) return this.$t("admin.errors.network");
      if (error?.status === 409) return this.$t("admin.errors.lastAdmin");
      if (error?.status === 422) return this.$t("admin.errors.validation");
      return this.$t(fallback);
    },
    handleAuthorization(error) {
      if (error?.status === 401) {
        authSession.clear();
        this.$router.replace({ name: "home" });
        return true;
      }
      if (error?.status === 403) {
        this.$router.replace({ name: "forbidden" });
        return true;
      }
      return false;
    },
    async load() {
      this.loading = true;
      this.error = "";
      try {
        Object.assign(this, await adminApiClient.overview());
      } catch (error) {
        if (!this.handleAuthorization(error)) {
          this.error = this.message(error, "admin.errors.load");
        }
      } finally {
        this.loading = false;
      }
    },
    async createUser(draft) {
      this.creating = true;
      this.createError = "";
      try {
        await adminApiClient.createUser(draft);
        this.$refs.usersTab?.resetForm();
        await this.load();
      } catch (error) {
        if (!this.handleAuthorization(error)) {
          this.createError = this.message(error, "admin.errors.create");
        }
      } finally {
        this.creating = false;
      }
    },
    async changeRole({ user, role }) {
      this.busyUserId = user.id;
      this.roleError = "";
      try {
        const updated = await adminApiClient.changeUserRole(user.id, role);
        this.users = this.users.map((item) =>
          item.id === updated.id ? { ...item, ...updated } : item,
        );
        this.metrics.admins = this.users.filter(
          (item) => item.role === "admin",
        ).length;
        if (updated.id === this.currentUserId && updated.role !== "admin") {
          const session = authSession.read();
          if (session)
            authSession.updateUser({ ...session.user, role: updated.role });
          await this.$router.replace({ name: "home" });
        }
      } catch (error) {
        if (!this.handleAuthorization(error)) {
          this.roleError = this.message(error, "admin.errors.role");
          await this.load();
        }
      } finally {
        this.busyUserId = 0;
      }
    },
  },
};
