import LandingCtaSection from "@/components/home/LandingCtaSection.vue";
import LandingFeaturesSection from "@/components/home/LandingFeaturesSection.vue";
import LandingGallerySection from "@/components/home/LandingGallerySection.vue";
import LandingHeroSection from "@/components/home/LandingHeroSection.vue";
import LandingModulesSection from "@/components/home/LandingModulesSection.vue";
import LandingPlansSection from "@/components/home/LandingPlansSection.vue";
import LandingStatsSection from "@/components/home/LandingStatsSection.vue";
import LandingUspStrip from "@/components/home/LandingUspStrip.vue";
import UserAccountMenu from "@/components/navigation/UserAccountMenu.vue";
import { availableLocales, setLocale } from "@/i18n";
import { subscriptionPlanApiClient } from "@/lib/subscription/subscriptionPlanApiClient";
import { authApiClient } from "@/lib/auth/authApiClient";
import { authSession } from "@/lib/auth/authSession";
import bg1 from "@/assets/app-ui/img/bg1.jpg";
import bg2 from "@/assets/app-ui/img/bg2.jpg";
import background from "@/assets/app-ui/img/background.jpg";
import logo from "@/assets/app-ui/img/BlatyRPG-logo.png";
import dice20 from "@/assets/app-ui/img/dice20.png";
import navbar from "@/assets/app-ui/gfx/navbar-bg.jpg";

export default {
  name: "HomeView",
  components: {
    LandingCtaSection,
    LandingFeaturesSection,
    LandingGallerySection,
    LandingHeroSection,
    LandingModulesSection,
    LandingPlansSection,
    LandingStatsSection,
    LandingUspStrip,
    UserAccountMenu,
  },
  data: () => ({
    assets: { bg1, bg2, background, logo, dice20, navbar },
    menuOpen: false,
    session: authSession.read(),
    unsubscribeAuth: null,
    loggingOut: false,
    locales: availableLocales,
    sectionLinks: [
      { target: "features", label: "landing.nav.features" },
      { target: "gallery", label: "landing.nav.gallery" },
      { target: "modules", label: "landing.nav.modules" },
      { target: "stats", label: "landing.nav.stats" },
      { target: "plans", label: "landing.nav.plans" },
      { target: "cta", label: "landing.nav.start" },
    ],
    plans: [],
    plansLoading: false,
    plansError: "",
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
    isAuthenticated() {
      return Boolean(this.session?.user);
    },
    isAdmin() {
      return this.session?.user?.role === "admin";
    },
    styleVars() {
      return {
        "--landing-background": `url("${this.assets.background}")`,
        "--landing-hero": `url("${this.assets.background}")`,
        "--landing-map": `url("${this.assets.bg1}")`,
        "--landing-navbar": `url("${this.assets.navbar}")`,
      };
    },
  },
  mounted() {
    this.unsubscribeAuth = authSession.subscribe((session) => {
      this.session = session;
    });
    this.loadPlans();
  },
  beforeUnmount() {
    this.unsubscribeAuth?.();
  },
  methods: {
    async logout() {
      if (this.loggingOut) return;
      this.loggingOut = true;
      try {
        if (authSession.read()) await authApiClient.logout();
      } catch (_error) {
        // Local sign-out remains available when the backend is unreachable.
      } finally {
        authSession.clear("logout");
        this.loggingOut = false;
        this.closeMenu();
      }
    },
    selectSection(targetId) {
      this.closeMenu();
      this.scrollTo(targetId);
    },
    scrollTo(targetId) {
      document.getElementById(targetId)?.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    },
    toggleMenu() {
      this.menuOpen = !this.menuOpen;
    },
    closeMenu() {
      this.menuOpen = false;
    },
    async loadPlans() {
      this.plansLoading = true;
      this.plansError = "";
      try {
        this.plans = await subscriptionPlanApiClient.list();
      } catch (_error) {
        this.plansError = "plan_catalog_unavailable";
      } finally {
        this.plansLoading = false;
      }
    },
  },
};
