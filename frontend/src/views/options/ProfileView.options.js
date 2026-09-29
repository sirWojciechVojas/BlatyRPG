import UserOverviewTab from "@/components/account/UserOverviewTab.vue";
import UserPreferencesPanel from "@/components/account/UserPreferencesPanel.vue";
import UserProfileForm from "@/components/account/UserProfileForm.vue";
import UserSecurityPanel from "@/components/account/UserSecurityPanel.vue";
import background from "@/assets/app-ui/img/background.jpg";
import { availableLocales, setLocale } from "@/i18n";
import { authApiClient } from "@/lib/auth/authApiClient";
import { authErrorKey } from "@/lib/auth/authErrors";
import { authSession } from "@/lib/auth/authSession";
import { campaignApiClient } from "@/lib/campaign/campaignApiClient";

const tabIds = ["overview", "profile", "security", "preferences"];

export default {
  name: "ProfileView",
  components: {
    UserOverviewTab,
    UserPreferencesPanel,
    UserProfileForm,
    UserSecurityPanel,
  },
  data: () => ({
    activeTab: "overview",
    user: {},
    campaigns: [],
    invitations: [],
    sessions: [],
    oauthProviders: [],
    oauthIdentities: [],
    locales: availableLocales,
    loading: true,
    busy: "",
    error: "",
    notice: "",
    avatarVisible: true,
    tabs: [
      { id: "overview", icon: "▦", label: "auth.userPanel.tabs.overview" },
      { id: "profile", icon: "◉", label: "auth.userPanel.tabs.profile" },
      { id: "security", icon: "◇", label: "auth.userPanel.tabs.security" },
      {
        id: "preferences",
        icon: "⚙",
        label: "auth.userPanel.tabs.preferences",
      },
    ],
  }),
  computed: {
    currentLocale() {
      return typeof this.$i18n.locale === "string"
        ? this.$i18n.locale
        : this.$i18n.locale.value;
    },
    displayName() {
      return (
        this.user.username || this.user.email || this.$t("auth.account.user")
      );
    },
    initials() {
      return this.displayName.trim().slice(0, 2).toUpperCase();
    },
    roleLabel() {
      return this.$t(
        this.user.role === "admin"
          ? "auth.account.roleAdmin"
          : "auth.account.roleUser",
      );
    },
    styleVars() {
      return { "--user-panel-background": `url("${background}")` };
    },
  },
  watch: {
    "$route.query.tab": {
      immediate: true,
      handler(tab) {
        this.activeTab = tabIds.includes(tab) ? tab : "overview";
      },
    },
    "user.avatarUrl"(value) {
      this.avatarVisible = Boolean(value);
    },
  },
  async mounted() {
    const session = authSession.read();
    if (!session) return this.$router.replace({ name: "login" });
    this.user = session.user || {};
    await this.loadPanel();
  },
  methods: {
    selectTab(tab) {
      if (!tabIds.includes(tab)) return;
      this.$router.replace({ query: tab === "overview" ? {} : { tab } });
    },
    message(error) {
      return this.$t(authErrorKey(error));
    },
    clearFeedback() {
      this.error = "";
      this.notice = "";
    },
    async loadPanel() {
      this.loading = true;
      this.clearFeedback();
      try {
        const [
          user,
          directory,
          invitations,
          sessions,
          oauthProviders,
          oauthIdentities,
        ] = await Promise.all([
          authApiClient.me(),
          campaignApiClient.list(),
          campaignApiClient.listMyInvitations(),
          authApiClient.sessions(),
          authApiClient.oauthProviders(),
          authApiClient.oauthIdentities(),
        ]);
        this.user = user;
        authSession.updateUser(user);
        this.campaigns = directory.campaigns;
        this.invitations = invitations;
        this.sessions = sessions;
        this.oauthProviders = oauthProviders;
        this.oauthIdentities = oauthIdentities;
        if (this.$route.query.oauth === "linked") {
          this.notice = this.$t("auth.oauth.linked", {
            provider: this.$route.query.provider || "",
          });
        } else if (this.$route.query.oauthError) {
          this.error = this.$t(
            `auth.oauth.errors.${this.$route.query.oauthError}`,
          );
        }
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.loading = false;
      }
    },
    async run(action, successKey, operation) {
      this.busy = action;
      this.clearFeedback();
      try {
        await operation();
        this.notice = this.$t(successKey);
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.busy = "";
      }
    },
    saveProfile(profile) {
      return this.run("profile", "auth.profile.saved", async () => {
        this.user = await authApiClient.updateProfile(profile);
        authSession.updateUser(this.user);
      });
    },
    changePassword(password) {
      return this.run("password", "auth.password.changed", async () => {
        const result = await authApiClient.changePassword(password);
        authSession.save(result);
        this.user = result.user;
        this.sessions = await authApiClient.sessions();
      });
    },
    revokeSession(id) {
      return this.run(`session-${id}`, "auth.sessions.revoked", async () => {
        await authApiClient.revokeSession(id);
        this.sessions = this.sessions.filter((item) => item.id !== id);
      });
    },
    revokeOtherSessions() {
      return this.run("sessions", "auth.sessions.othersRevoked", async () => {
        await authApiClient.revokeOtherSessions();
        this.sessions = this.sessions.filter((item) => item.isCurrent);
      });
    },
    async linkOAuth(provider) {
      if (this.busy) return;
      this.busy = `oauth-${provider}`;
      this.clearFeedback();
      try {
        const authorizationUrl = await authApiClient.linkOAuth(provider);
        window.location.assign(authorizationUrl);
      } catch (error) {
        this.error = this.message(error);
        this.busy = "";
      }
    },
    async changeLocale(locale) {
      await setLocale(locale);
      this.notice = this.$t("auth.preferences.saved");
    },
  },
};
