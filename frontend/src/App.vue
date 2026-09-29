<template>
  <nav
    v-if="showNavigation"
    class="app-navigation"
    :class="{
      'app-navigation--legacy': !uiSystemActive,
      'app-navigation--overlay': usesOverlayNav,
      'app-navigation--workspace': isCampaignWorkspace,
      'app-navigation--public': routeUi.isPublic,
      'app-navigation--campaigns': $route.name === 'tables',
    }"
    aria-label="Blaty RPG"
  >
    <router-link class="app-nav-brand" :to="{ name: 'landing' }">
      <img :src="appLogo" alt="" />
      <span>
        <strong>{{ $t("landing.brand.title") }}</strong>
        <small>{{ $t("landing.brand.subtitle") }}</small>
      </span>
    </router-link>
    <div class="app-nav-links">
      <router-link :to="{ name: 'landing' }">{{ $t("nav.home") }}</router-link>
      <router-link :to="{ name: 'about' }">{{ $t("nav.about") }}</router-link>
      <template v-if="campaignId">
        <router-link
          :to="{ name: 'scene-workspace', params: { campaignId } }"
          >{{ $t("vtt.scene.navigation.title") }}</router-link
        >
        <router-link
          :to="{ name: 'character-workspace', params: { campaignId } }"
          >{{ $t("dashboard.campaign.openCharacters") }}</router-link
        >
        <router-link
          :to="{
            name: 'scene-workspace',
            params: { campaignId },
            hash: '#campaign-chat',
          }"
          >{{ $t("dashboard.campaign.openChat") }}</router-link
        >
      </template>
    </div>
    <div class="app-nav-actions">
      <router-link class="app-nav-action-link" :to="{ name: 'dice' }">
        {{ $t("nav.diceRoller") }}
      </router-link>
      <label class="locale-switch">
        <span>{{ $t("nav.language") }}</span>
        <select v-model="currentLocale" :aria-label="$t('nav.language')">
          <option
            v-for="locale in locales"
            :key="locale.code"
            :value="locale.code"
          >
            {{ locale.label }}
          </option>
        </select>
      </label>
      <UserAccountMenu
        v-if="session?.user && !$route.meta.redirectAuthenticated"
        :session="session"
        :is-admin="isAdmin"
        :logging-out="loggingOut"
        @logout="logout"
      />
    </div>
  </nav>
  <router-view />
  <ShopAccessModeSelector v-if="$route.name === 'shop-gm'" />
</template>

<script>
import { availableLocales, setLocale } from "@/i18n";
import ShopAccessModeSelector from "@/components/shop/ShopAccessModeSelector.vue";
import UserAccountMenu from "@/components/navigation/UserAccountMenu.vue";
import { authApiClient } from "@/lib/auth/authApiClient";
import { authSession } from "@/lib/auth/authSession";
import appLogo from "@/assets/app-ui/img/BlatyRPG-logo.png";
import {
  UI_ROOT_CLASS_NAMES,
  resolveRouteUi,
  routeUiRootClasses,
} from "@/components/ui/routeUi";
export default {
  name: "AppRoot",
  components: { ShopAccessModeSelector, UserAccountMenu },
  data() {
    return {
      localization: {},
      appLogo,
      locales: availableLocales,
      session: authSession.read(),
      loggingOut: false,
      unsubscribeAuth: null,
      appTitle:
        typeof process !== "undefined" &&
        process.env &&
        process.env.VUE_APP_TITLE
          ? process.env.VUE_APP_TITLE
          : "BlatyRPG",
    };
  },
  created() {
    this.unsubscribeAuth = authSession.subscribe((session) => {
      this.session = session;
      if (!session && this.$store.hasModule("professions")) {
        this.$store.commit("professions/SET_CONTEXT", null);
      }
    });
  },
  beforeUnmount() {
    this.clearUiRootState();
    this.unsubscribeAuth?.();
  },
  watch: {
    $route: {
      immediate: true,
      handler(to) {
        this.session = authSession.read();
        const pageTitle = to?.meta?.title;
        document.title = pageTitle
          ? `${pageTitle} — ${this.appTitle}`
          : this.appTitle;
        this.syncUiRootState(to);
      },
    },
  },
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
    routeUi() {
      return resolveRouteUi(this.$route);
    },
    uiSystemActive() {
      return this.routeUi.enabled;
    },
    showNavigation() {
      return this.routeUi.showNavigation;
    },
    usesOverlayNav() {
      return this.routeUi.navigation === "overlay";
    },
    isCampaignWorkspace() {
      return this.routeUi.isWorkspace;
    },
    campaignId() {
      return this.$route?.params?.campaignId || null;
    },
    isAdmin() {
      return this.session?.user?.role === "admin";
    },
  },
  methods: {
    async logout() {
      if (this.loggingOut) return;
      this.loggingOut = true;
      try {
        if (authSession.read()) await authApiClient.logout();
      } catch (_error) {
        // Local sign-out must still work if the server is temporarily offline.
      } finally {
        authSession.clear("logout");
        this.loggingOut = false;
        if (this.$route.name !== "landing") {
          await this.$router.replace({ name: "landing" });
        }
      }
    },
    uiRootElements() {
      if (typeof document === "undefined") {
        return [];
      }
      return [document.body, document.getElementById("app")].filter(Boolean);
    },
    clearUiRootState() {
      this.uiRootElements().forEach((element) => {
        element.classList.remove(...UI_ROOT_CLASS_NAMES);
        delete element.dataset.uiLayout;
      });
    },
    syncUiRootState(route) {
      const routeUi = resolveRouteUi(route);
      this.clearUiRootState();
      if (!routeUi.enabled) {
        return;
      }
      const classes = routeUiRootClasses(routeUi);
      this.uiRootElements().forEach((element) => {
        element.classList.add(...classes);
        element.dataset.uiLayout = routeUi.layout;
      });
    },
  },
};
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
}
</style>
