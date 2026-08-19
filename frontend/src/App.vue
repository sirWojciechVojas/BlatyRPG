<template>
  <nav
    v-if="!isHomeRoute"
    class="app-navigation"
    :class="{
      'app-navigation--overlay': usesOverlayNav,
      'app-navigation--workspace': isSceneWorkspace,
    }"
  >
    <router-link to="/">{{ $t("nav.home") }}</router-link>
    <span class="nav-sep">|</span>
    <router-link to="/about">{{ $t("nav.about") }}</router-link>
    <span class="nav-sep">|</span>
    <router-link to="/dice">{{ $t("nav.diceRoller") }}</router-link>
    <template v-if="campaignId">
      <span class="nav-sep">|</span>
      <router-link :to="{ name: 'scene-workspace', params: { campaignId } }">{{
        $t("vtt.scene.navigation.title")
      }}</router-link>
    </template>
    <span class="nav-sep">|</span>
    <label class="locale-switch">
      <span>{{ $t("nav.language") }}</span>
      <select v-model="currentLocale" aria-label="Language">
        <option
          v-for="locale in locales"
          :key="locale.code"
          :value="locale.code"
        >
          {{ locale.label }}
        </option>
      </select>
    </label>
  </nav>
  <router-view />
  <ShopAccessModeSelector v-if="$route.name === 'shop-gm'" />
</template>

<script>
import { availableLocales, setLocale } from "@/i18n";
import ShopAccessModeSelector from "@/components/shop/ShopAccessModeSelector.vue";
export default {
  name: "AppRoot",
  components: { ShopAccessModeSelector },

  data() {
    return {
      localization: {},
      locales: availableLocales,
      // Nazwa aplikacji (fallback do tytułu zakładki)
      appTitle:
        typeof process !== "undefined" &&
        process.env &&
        process.env.VUE_APP_TITLE
          ? process.env.VUE_APP_TITLE
          : "BlatyRPG",
    };
  },
  watch: {
    // Ustawia tytuł zakładki na podstawie meta.title w routach
    $route: {
      immediate: true,
      handler(to) {
        const pageTitle = to?.meta?.title;
        document.title = pageTitle
          ? `${pageTitle} — ${this.appTitle}`
          : this.appTitle;
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
    usesOverlayNav() {
      return this.$route?.path === "/dice" || this.$route?.name === "shop-gm";
    },
    isSceneWorkspace() {
      return this.$route?.name === "scene-workspace";
    },
    isHomeRoute() {
      return this.$route?.path === "/";
    },
    campaignId() {
      return this.$route?.params?.campaignId || null;
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

.app-navigation {
  padding: 30px;
}

.app-navigation a {
  font-weight: bold;
  color: #2c3e50;
}

.app-navigation a.router-link-exact-active {
  color: #42b983;
}

.app-navigation .nav-sep {
  margin: 0 8px;
  color: inherit;
}

.app-navigation .locale-switch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: inherit;
}

.app-navigation .locale-switch select {
  border-radius: 6px;
  border: 1px solid rgba(44, 62, 80, 0.3);
  padding: 4px 8px;
  background: #ffffff;
  color: #2c3e50;
}

.app-navigation--overlay {
  position: fixed;
  top: 12px;
  right: 12px;
  padding: 6px 10px;
  background: rgba(0, 0, 0, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 10px;
  font-size: 0.9rem;
  z-index: 20;
}

.app-navigation--overlay a,
.app-navigation--workspace a {
  color: #ffffff;
}

.app-navigation--overlay a.router-link-exact-active,
.app-navigation--workspace a.router-link-exact-active {
  color: #ffd166;
}

.app-navigation--overlay .locale-switch select,
.app-navigation--workspace .locale-switch select {
  border-color: rgba(255, 255, 255, 0.35);
  background: rgba(0, 0, 0, 0.6);
  color: #ffffff;
}

.app-navigation--workspace {
  position: sticky;
  top: 0;
  z-index: 30;
  box-sizing: border-box;
  display: flex;
  width: 100%;
  min-height: 64px;
  align-items: center;
  justify-content: flex-end;
  padding: 10px 16px;
  overflow-x: auto;
  color: #edf0f5;
  white-space: nowrap;
  background: #141922;
  border-bottom: 1px solid #303746;
}

@media (max-width: 760px) {
  .app-navigation--workspace {
    justify-content: flex-start;
  }
}
</style>
