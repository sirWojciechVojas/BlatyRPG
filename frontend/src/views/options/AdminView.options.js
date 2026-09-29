import { defineAsyncComponent } from "vue";
import AdminActivityTab from "@/components/admin/AdminActivityTab.vue";
import AdminCampaignsTab from "@/components/admin/AdminCampaignsTab.vue";
import AdminCharactersTab from "@/components/admin/AdminCharactersTab.vue";
import AdminCompendiumTab from "@/components/admin/AdminCompendiumTab.vue";
import AdminProfessionsTab from "@/components/admin/AdminProfessionsTab.vue";
import AdminAudioTab from "@/components/admin/AdminAudioTab.vue";
import AdminTokenTemplatesTab from "@/components/admin/AdminTokenTemplatesTab.vue";
import AdminOverviewTab from "@/components/admin/AdminOverviewTab.vue";
import AdminSystemTab from "@/components/admin/AdminSystemTab.vue";
import AdminUsersTab from "@/components/admin/AdminUsersTab.vue";
import AdminIcon from "@/components/admin/AdminIcon.vue";
import { adminApiClient } from "@/lib/admin/adminApiClient";
import { resolveAdminUserApiFieldErrors } from "@/lib/admin/adminUserValidation";
import { authSession } from "@/lib/auth/authSession";

const emptyAnalytics = () => ({
  accountRoles: [],
  campaignStatuses: [],
  membershipRoles: [],
  systems: [],
  growth: [],
});

const AdminAssetsTab = defineAsyncComponent(
  () => import("@/components/admin/AdminAssetsTab.vue"),
);

export default {
  name: "AdminView",
  components: {
    AdminActivityTab,
    AdminCampaignsTab,
    AdminCharactersTab,
    AdminCompendiumTab,
    AdminProfessionsTab,
    AdminAudioTab,
    AdminTokenTemplatesTab,
    AdminAssetsTab,
    AdminOverviewTab,
    AdminSystemTab,
    AdminUsersTab,
    AdminIcon,
  },
  data: () => ({
    activeTab: "overview",
    users: [],
    campaigns: [],
    characters: [],
    characterCampaigns: [],
    characterOwners: [],
    characterGameMasters: [],
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
    busyAssignmentKey: "",
    error: "",
    createError: "",
    createFieldErrors: {},
    roleError: "",
    characterError: "",
    compendiumCount: null,
    professionCount: null,
    audioCount: null,
    tokenTemplateCount: null,
    assetCount: null,
  }),
  computed: {
    tabs() {
      return [
        { id: "overview", icon: "overview", count: null },
        { id: "assets", icon: "assets", count: this.assetCount },
        { id: "users", icon: "users", count: this.metrics.users },
        { id: "campaigns", icon: "campaigns", count: this.metrics.campaigns },
        {
          id: "compendium",
          icon: "compendium",
          count: this.compendiumCount,
        },
        {
          id: "professions",
          icon: "professions",
          count: this.professionCount,
        },
        { id: "audio", icon: "audio", count: this.audioCount },
        {
          id: "tokenTemplates",
          icon: "tokenTemplates",
          count: this.tokenTemplateCount,
        },
        { id: "characters", icon: "characters", count: this.characters.length },
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
      this.createFieldErrors = {};
      try {
        await adminApiClient.createUser(draft);
        this.$refs.usersTab?.resetForm();
        await this.load();
      } catch (error) {
        if (!this.handleAuthorization(error)) {
          this.createError = this.message(error, "admin.errors.create");
          this.createFieldErrors = this.resolveCreateFieldErrors(error);
        }
      } finally {
        this.creating = false;
      }
    },
    resolveCreateFieldErrors(error) {
      return resolveAdminUserApiFieldErrors(error?.payload?.errors, (field) =>
        this.$t(`admin.errors.fields.${field}`),
      );
    },
    clearCreateFieldError(field) {
      if (!this.createFieldErrors[field]) return;
      const remaining = { ...this.createFieldErrors };
      delete remaining[field];
      this.createFieldErrors = remaining;
      if (!Object.keys(remaining).length) this.createError = "";
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
    async setCharacterCampaign({ character, campaignId, assigned }) {
      this.busyAssignmentKey = `campaign:${character.id}:${campaignId}`;
      this.characterError = "";
      try {
        await adminApiClient.setCharacterCampaign(
          character.id,
          campaignId,
          assigned,
        );
        if (assigned) {
          const campaign = this.campaigns.find(
            (item) => item.id === campaignId,
          );
          this.characterCampaigns.push({
            characterId: character.id,
            campaignId,
            campaignName: campaign?.name || "",
          });
        } else {
          this.characterCampaigns = this.characterCampaigns.filter(
            (item) =>
              item.characterId !== character.id ||
              item.campaignId !== campaignId,
          );
          this.characterOwners = this.characterOwners.filter(
            (item) =>
              item.characterId !== character.id ||
              item.campaignId !== campaignId,
          );
        }
      } catch (error) {
        if (!this.handleAuthorization(error)) {
          this.characterError =
            error?.status === 422
              ? this.$t("admin.errors.characterCampaign")
              : this.message(error, "admin.errors.characterAssignment");
        }
      } finally {
        this.busyAssignmentKey = "";
      }
    },
    async setCharacterOwner({ character, gameMaster, assigned }) {
      const { campaignId, userId } = gameMaster;
      this.busyAssignmentKey = `owner:${character.id}:${campaignId}:${userId}`;
      this.characterError = "";
      try {
        await adminApiClient.setCharacterOwner(
          character.id,
          campaignId,
          userId,
          assigned,
        );
        if (assigned) {
          if (
            !this.characterCampaigns.some(
              (item) =>
                item.characterId === character.id &&
                item.campaignId === campaignId,
            )
          ) {
            const campaign = this.campaigns.find(
              (item) => item.id === campaignId,
            );
            this.characterCampaigns.push({
              characterId: character.id,
              campaignId,
              campaignName: campaign?.name || "",
            });
          }
          this.characterOwners.push({
            characterId: character.id,
            campaignId,
            userId,
            username: gameMaster.username,
          });
        } else {
          this.characterOwners = this.characterOwners.filter(
            (item) =>
              item.characterId !== character.id ||
              item.campaignId !== campaignId ||
              item.userId !== userId,
          );
        }
      } catch (error) {
        if (!this.handleAuthorization(error)) {
          this.characterError =
            error?.status === 422
              ? this.$t("admin.errors.characterCandidate")
              : this.message(error, "admin.errors.characterAssignment");
        }
      } finally {
        this.busyAssignmentKey = "";
      }
    },
  },
};
