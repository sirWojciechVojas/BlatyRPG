import LandingCtaSection from "@/components/home/LandingCtaSection.vue";
import LandingFeaturesSection from "@/components/home/LandingFeaturesSection.vue";
import LandingGallerySection from "@/components/home/LandingGallerySection.vue";
import LandingHeroSection from "@/components/home/LandingHeroSection.vue";
import LandingModulesSection from "@/components/home/LandingModulesSection.vue";
import LandingPlansSection from "@/components/home/LandingPlansSection.vue";
import LandingStatsSection from "@/components/home/LandingStatsSection.vue";
import LandingUspStrip from "@/components/home/LandingUspStrip.vue";
import { subscriptionPlanApiClient } from "@/lib/subscription/subscriptionPlanApiClient";
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
  },
  data: () => ({
    assets: { bg1, bg2, background, logo, dice20, navbar },
    plans: [],
    plansLoading: false,
    plansError: "",
  }),
  computed: {
    styleVars() {
      return {
        "--landing-background": `url("${this.assets.bg2}")`,
        "--landing-hero": `url("${this.assets.background}")`,
        "--landing-map": `url("${this.assets.bg1}")`,
        "--landing-navbar": `url("${this.assets.navbar}")`,
      };
    },
  },
  mounted() {
    this.loadPlans();
  },
  methods: {
    scrollTo(targetId) {
      document.getElementById(targetId)?.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
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
