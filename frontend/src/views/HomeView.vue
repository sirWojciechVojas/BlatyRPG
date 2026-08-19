<template>
  <div class="home-page" :style="styleVars">
    <header class="topbar">
      <button type="button" class="brand" @click="scrollTo('hero')">
        <img class="brand-mark" :src="assets.logo" alt="" />
        <span class="brand-copy">
          <span class="brand-title">{{ $t("landing.brand.title") }}</span>
          <span class="brand-sub">{{ $t("landing.brand.subtitle") }}</span>
        </span>
      </button>
      <nav class="nav-links" :aria-label="$t('landing.nav.primary')">
        <button type="button" class="nav-link" @click="scrollTo('features')">
          {{ $t("landing.nav.features") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('modules')">
          {{ $t("landing.nav.modules") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('plans')">
          {{ $t("landing.nav.plans") }}
        </button>
      </nav>
      <div class="topbar-actions">
        <router-link class="ghost-link" :to="{ name: 'dice' }">
          {{ $t("landing.nav.dice") }}
        </router-link>
        <router-link class="ghost-link" :to="{ name: 'login' }">
          {{ $t("landing.nav.signIn") }}
        </router-link>
        <router-link class="cta-btn small" :to="{ name: 'register' }">
          {{ $t("landing.nav.createAccount") }}
        </router-link>
      </div>
    </header>

    <main>
      <LandingHeroSection :assets="assets" @scroll="scrollTo" />
      <LandingUspStrip />
      <LandingFeaturesSection />
      <LandingGallerySection :assets="assets" />
      <LandingModulesSection />
      <LandingPlansSection
        :plans="plans"
        :loading="plansLoading"
        :error="plansError"
        @retry="loadPlans"
      />
      <LandingStatsSection />
      <LandingCtaSection :assets="assets" />
    </main>

    <footer class="footer">
      <div>{{ $t("landing.footer.text") }}</div>
      <div class="footer-meta">{{ $t("landing.footer.meta") }}</div>
    </footer>
  </div>
</template>

<script src="./options/HomeView.options.js"></script>
<style src="./styles/HomeView.css"></style>
