import AdminCampaignTable from "@/components/admin/AdminCampaignTable.vue";
import AdminUserCreateForm from "@/components/admin/AdminUserCreateForm.vue";
import AdminUserTable from "@/components/admin/AdminUserTable.vue";
import { adminApiClient } from "@/lib/admin/adminApiClient";
import { authSession } from "@/lib/auth/authSession";

export default {
  name: "AdminView",
  components: { AdminCampaignTable, AdminUserCreateForm, AdminUserTable },
  data: () => ({
    users: [],
    campaigns: [],
    metrics: { users: 0, admins: 0, campaigns: 0 },
    currentUserId: 0,
    loading: true,
    creating: false,
    busyUserId: 0,
    error: "",
    createError: "",
    roleError: "",
  }),
  computed: {
    metricCards() {
      return [
        { label: this.$t("admin.metrics.users"), value: this.metrics.users },
        { label: this.$t("admin.metrics.admins"), value: this.metrics.admins },
        {
          label: this.$t("admin.metrics.campaigns"),
          value: this.metrics.campaigns,
        },
      ];
    },
  },
  mounted() {
    this.load();
  },
  methods: {
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
        const result = await adminApiClient.overview();
        this.users = result.users;
        this.campaigns = result.campaigns;
        this.metrics = result.metrics;
        this.currentUserId = result.currentUserId;
      } catch (error) {
        if (!this.handleAuthorization(error))
          this.error = this.message(error, "admin.errors.load");
      } finally {
        this.loading = false;
      }
    },
    async createUser(draft) {
      this.creating = true;
      this.createError = "";
      try {
        await adminApiClient.createUser(draft);
        this.$refs.createForm?.reset();
        await this.load();
      } catch (error) {
        if (!this.handleAuthorization(error))
          this.createError = this.message(error, "admin.errors.create");
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
