import { Modal } from "bootstrap";
import { defineAsyncComponent } from "vue";
import { ensureShopStoreModule } from "@/store/modules/loadShopModule";
import LandingCtaSection from "@/components/home/LandingCtaSection.vue";
import LandingFeaturesSection from "@/components/home/LandingFeaturesSection.vue";
import LandingGallerySection from "@/components/home/LandingGallerySection.vue";
import LandingHeroSection from "@/components/home/LandingHeroSection.vue";
import LandingModulesSection from "@/components/home/LandingModulesSection.vue";
import LandingStatsSection from "@/components/home/LandingStatsSection.vue";
import LandingUspStrip from "@/components/home/LandingUspStrip.vue";
import bg1 from "@/assets/app-ui/img/bg1.jpg";
import bg2 from "@/assets/app-ui/img/bg2.jpg";
import background from "@/assets/app-ui/img/background.jpg";
import navbar from "@/assets/app-ui/gfx/navbar-bg.jpg";
import logo from "@/assets/app-ui/img/BlatyRPG-logo.png";
import frame from "@/assets/app-ui/img/frameUni.png";
import gui from "@/assets/app-ui/gfx/GUISTBSC.png";
import diceFrame from "@/assets/app-ui/gfx/dice-frame.png";
import charStats from "@/assets/app-ui/img/CharacterStats-bg.png";
import chatbox from "@/assets/app-ui/img/chatbox.png";
import titleBar from "@/assets/app-ui/img/titleBar-center.png";
import dice20 from "@/assets/app-ui/img/dice20.png";

export default {
  name: "HomeView",
  components: {
    LandingCtaSection,
    LandingFeaturesSection,
    LandingGallerySection,
    LandingHeroSection,
    LandingModulesSection,
    LandingStatsSection,
    LandingUspStrip,
    ShopTradeModal: defineAsyncComponent(
      () =>
        import(
          /* webpackChunkName: "shop-trade" */ "@/components/ShopTradeModal.vue"
        ),
    ),
  },
  data() {
    return {
      assets: {
        bg1,
        bg2,
        background,
        navbar,
        logo,
        frame,
        gui,
        diceFrame,
        charStats,
        chatbox,
        titleBar,
        dice20,
      },
      isShopTradeMounted: false,
      tradeModalLifecycle: null,
      tradeLockedScrollY: 0,
      isTradeScrollLocked: false,
    };
  },
  beforeUnmount() {
    this.teardownShopTradeModalLifecycle();
    this.clearTradeModalLayoutCompensation();
  },
  computed: {
    styleVars() {
      return {
        "--sb-bg-page": `url("${this.assets.bg2}")`,
        "--sb-bg-hero": `url("${this.assets.background}")`,
        "--sb-navbar": `url("${this.assets.navbar}")`,
        "--sb-logo": `url("${this.assets.logo}")`,
        "--sb-frame": `url("${this.assets.frame}")`,
        "--sb-gui": `url("${this.assets.gui}")`,
        "--sb-map": `url("${this.assets.bg1}")`,
        "--sb-dice-frame": `url("${this.assets.diceFrame}")`,
        "--sb-charstats": `url("${this.assets.charStats}")`,
        "--sb-chatbox": `url("${this.assets.chatbox}")`,
        "--sb-titlebar": `url("${this.assets.titleBar}")`,
      };
    },
  },
  methods: {
    bindShopTradeModalLifecycle(modalElement) {
      if (
        !modalElement ||
        this.tradeModalLifecycle?.modalElement === modalElement
      ) {
        return;
      }

      this.teardownShopTradeModalLifecycle();

      const onShow = () => {
        this.applyTradeModalLayoutCompensation();
      };
      const onHidden = () => {
        this.clearTradeModalLayoutCompensation();
      };

      modalElement.addEventListener("show.bs.modal", onShow);
      modalElement.addEventListener("hidden.bs.modal", onHidden);

      this.tradeModalLifecycle = {
        modalElement,
        onShow,
        onHidden,
      };
    },
    teardownShopTradeModalLifecycle() {
      const lifecycle = this.tradeModalLifecycle;
      if (!lifecycle?.modalElement) {
        return;
      }
      lifecycle.modalElement.removeEventListener(
        "show.bs.modal",
        lifecycle.onShow,
      );
      lifecycle.modalElement.removeEventListener(
        "hidden.bs.modal",
        lifecycle.onHidden,
      );
      this.tradeModalLifecycle = null;
    },
    applyTradeModalLayoutCompensation() {
      if (this.isTradeScrollLocked) {
        return;
      }
      this.tradeLockedScrollY = Math.max(
        0,
        window.scrollY || document.documentElement.scrollTop || 0,
      );
      document.body.style.position = "fixed";
      document.body.style.top = `-${this.tradeLockedScrollY}px`;
      document.body.style.left = "0";
      document.body.style.right = "0";
      document.body.style.width = "100%";
      this.isTradeScrollLocked = true;
    },
    clearTradeModalLayoutCompensation() {
      if (!this.isTradeScrollLocked) {
        return;
      }
      const restoreY = this.tradeLockedScrollY;
      document.body.style.position = "";
      document.body.style.top = "";
      document.body.style.left = "";
      document.body.style.right = "";
      document.body.style.width = "";
      window.scrollTo(0, restoreY);
      this.tradeLockedScrollY = 0;
      this.isTradeScrollLocked = false;
    },
    scrollTo(targetId) {
      const target = document.getElementById(targetId);
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    },
    async openShopTrade() {
      await ensureShopStoreModule(this.$store);
      if (!this.isShopTradeMounted) {
        this.isShopTradeMounted = true;
      }

      const startedAt = Date.now();
      let modalElement = null;
      while (!modalElement && Date.now() - startedAt < 4000) {
        await this.$nextTick();
        modalElement = document.getElementById("trading");
        if (!modalElement) {
          await new Promise((resolve) => setTimeout(resolve, 50));
        }
      }

      if (!modalElement) {
        return;
      }

      this.bindShopTradeModalLifecycle(modalElement);
      Modal.getOrCreateInstance(modalElement).show();
    },
  },
};
