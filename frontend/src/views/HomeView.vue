<template>
  <div class="home-page" :style="styleVars">
    <header class="topbar">
      <button type="button" class="brand" @click="scrollTo('hero')">
        <span class="brand-mark">BR</span>
        <span class="brand-copy">
          <span class="brand-title">{{ $t("landing.brand.title") }}</span>
          <span class="brand-sub">{{ $t("landing.brand.subtitle") }}</span>
        </span>
      </button>
      <nav class="nav-links">
        <button type="button" class="nav-link" @click="scrollTo('features')">
          {{ $t("landing.nav.features") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('gallery')">
          {{ $t("landing.nav.gallery") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('modules')">
          {{ $t("landing.nav.modules") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('stats')">
          {{ $t("landing.nav.stats") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('cta')">
          {{ $t("landing.nav.start") }}
        </button>
      </nav>
      <div class="topbar-actions">
        <router-link class="ghost-link" to="/dice">
          {{ $t("landing.nav.dice") }}
        </router-link>
        <button type="button" class="cta-btn small" @click="scrollTo('cta')">
          {{ $t("landing.nav.startSession") }}
        </button>
      </div>
    </header>

    <LandingHeroSection
      :assets="assets"
      @open-shop-trade="openShopTrade"
      @scroll="scrollTo"
    />
    <LandingUspStrip />
    <LandingFeaturesSection />
    <LandingGallerySection :assets="assets" />
    <LandingModulesSection />
    <LandingStatsSection />
    <LandingCtaSection :assets="assets" />

    <footer class="footer">
      <div>{{ $t("landing.footer.text") }}</div>
      <div class="footer-meta">{{ $t("landing.footer.meta") }}</div>
    </footer>

    <teleport to="body">
      <ShopTradeModal v-if="isShopTradeMounted" />
    </teleport>
  </div>
</template>

<script src="./options/HomeView.options.js"></script>
<style src="./styles/HomeView.css"></style>
