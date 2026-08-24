<template>
  <div class="home-page" :style="styleVars">
    <header
      class="topbar"
      :class="{
        'topbar--menu-open': menuOpen,
        'topbar--authenticated': isAuthenticated,
      }"
      @keydown.esc="closeMenu"
    >
      <button type="button" class="brand" @click="selectSection('hero')">
        <img class="brand-mark" :src="assets.logo" alt="" />
        <span class="brand-copy">
          <span class="brand-title">{{ $t("landing.brand.title") }}</span>
          <span class="brand-sub">{{ $t("landing.brand.subtitle") }}</span>
        </span>
      </button>
      <nav class="nav-links" :aria-label="$t('landing.nav.primary')">
        <button
          v-for="link in sectionLinks"
          :key="link.target"
          type="button"
          class="nav-link"
          @click="selectSection(link.target)"
        >
          {{ $t(link.label) }}
        </button>
      </nav>
      <div
        class="topbar-actions"
        :class="{ 'topbar-actions--authenticated': isAuthenticated }"
      >
        <router-link class="ghost-link" :to="{ name: 'dice' }">
          {{ $t("landing.nav.dice") }}
        </router-link>
        <UserAccountMenu
          v-if="isAuthenticated"
          :session="session"
          :is-admin="isAdmin"
          :logging-out="loggingOut"
          @logout="logout"
        />
        <template v-else>
          <router-link class="ghost-link" :to="{ name: 'login' }">
            {{ $t("landing.nav.signIn") }}
          </router-link>
          <router-link class="cta-btn small" :to="{ name: 'register' }">
            {{ $t("landing.nav.startSession") }}
          </router-link>
        </template>
      </div>
      <button
        type="button"
        class="menu-toggle"
        :aria-expanded="String(menuOpen)"
        aria-controls="landing-mobile-menu"
        :aria-label="
          $t(menuOpen ? 'landing.nav.closeMenu' : 'landing.nav.openMenu')
        "
        @click="toggleMenu"
      >
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
      </button>

      <transition name="landing-menu">
        <div v-if="menuOpen" id="landing-mobile-menu" class="mobile-menu">
          <nav class="mobile-menu-links" :aria-label="$t('landing.nav.mobile')">
            <button
              v-for="link in sectionLinks"
              :key="link.target"
              type="button"
              class="nav-link"
              @click="selectSection(link.target)"
            >
              {{ $t(link.label) }}
            </button>
          </nav>
          <div class="mobile-menu-actions">
            <router-link :to="{ name: 'dice' }" @click="closeMenu">
              {{ $t("landing.nav.dice") }}
            </router-link>
            <template v-if="!isAuthenticated">
              <router-link :to="{ name: 'login' }" @click="closeMenu">
                {{ $t("landing.nav.signIn") }}
              </router-link>
              <router-link
                class="cta-btn"
                :to="{ name: 'register' }"
                @click="closeMenu"
              >
                {{ $t("landing.nav.startSession") }}
              </router-link>
            </template>
          </div>
        </div>
      </transition>
    </header>

    <main>
      <div class="landing-intro">
        <LandingHeroSection :assets="assets" @scroll="scrollTo" />
        <LandingUspStrip />
      </div>
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
