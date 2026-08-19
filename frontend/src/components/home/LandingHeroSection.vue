<template>
  <section class="hero" id="hero">
    <div class="hero-content">
      <p class="eyebrow">{{ $t("landing.hero.eyebrow") }}</p>
      <img
        class="hero-logo"
        :src="assets.logo"
        :alt="$t('landing.hero.logoAlt')"
      />
      <h1>{{ $t("landing.hero.title") }}</h1>
      <p class="lead">{{ $t("landing.hero.lead") }}</p>
      <div class="hero-actions">
        <button type="button" class="cta-btn" @click="$emit('scroll', 'cta')">
          {{ $t("landing.hero.primaryCta") }}
        </button>
        <button
          type="button"
          class="cta-btn shop-btn"
          @click="$emit('open-shop-trade')"
        >
          Sklep / Handel
        </button>
        <router-link class="ghost-btn" to="/campaigns/1/shop">
          {{ $t("shop.workspace.title") }}
        </router-link>
        <button
          type="button"
          class="ghost-btn"
          @click="$emit('scroll', 'gallery')"
        >
          {{ $t("landing.hero.secondaryCta") }}
        </button>
      </div>
      <div class="hero-chips">
        <span>{{ $t("landing.hero.chips.maps") }}</span>
        <span>{{ $t("landing.hero.chips.fog") }}</span>
        <span>{{ $t("landing.hero.chips.narration") }}</span>
        <span>{{ $t("landing.hero.chips.live") }}</span>
      </div>
      <div class="hero-metrics">
        <div class="metric">
          <span class="metric-value">120+</span>
          <span class="metric-label">{{
            $t("landing.hero.metrics.scenes")
          }}</span>
        </div>
        <div class="metric">
          <span class="metric-value">48 ms</span>
          <span class="metric-label">{{
            $t("landing.hero.metrics.latency")
          }}</span>
        </div>
        <div class="metric">
          <span class="metric-value">24/7</span>
          <span class="metric-label">{{
            $t("landing.hero.metrics.hosting")
          }}</span>
        </div>
      </div>
    </div>

    <div class="hero-media">
      <div class="screen-mock">
        <div class="screen-top">
          <div class="screen-dots"><span></span><span></span><span></span></div>
          <div class="screen-title">{{ $t("landing.hero.screen.title") }}</div>
          <div class="screen-badge">{{ $t("landing.hero.screen.badge") }}</div>
        </div>
        <div class="screen-body">
          <div class="map-preview">
            <span class="map-token token-one"></span>
            <span class="map-token token-two"></span>
            <span class="map-token token-three"></span>
            <span class="map-ping"></span>
          </div>
          <div class="side-panels">
            <div class="side-card">
              <div class="side-title">
                {{ $t("landing.hero.screen.party") }}
              </div>
              <div class="avatar-row">
                <span class="avatar"></span><span class="avatar"></span>
                <span class="avatar"></span><span class="avatar"></span>
              </div>
              <div class="side-meter"><span></span></div>
            </div>
            <div class="side-card log-card">
              <div class="side-title">
                {{ $t("landing.hero.screen.journal") }}
              </div>
              <div class="log-line"></div>
              <div class="log-line"></div>
              <div class="log-line wide"></div>
              <div class="log-line"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="floating-card card-one">
        <div class="floating-title">{{ $t("landing.hero.cards.gmTitle") }}</div>
        <p>{{ $t("landing.hero.cards.gmText") }}</p>
      </div>
      <div class="floating-card card-two">
        <div class="floating-title">
          {{ $t("landing.hero.cards.fogTitle") }}
        </div>
        <p>{{ $t("landing.hero.cards.fogText") }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  assets: {
    type: Object,
    required: true,
  },
});

defineEmits(["open-shop-trade", "scroll"]);
</script>
