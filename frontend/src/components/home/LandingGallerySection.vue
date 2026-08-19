<template>
  <section class="section gallery-section" id="gallery">
    <div class="section-inner">
      <div class="section-head">
        <p class="eyebrow">{{ $t("landing.gallery.eyebrow") }}</p>
        <h2>{{ $t("landing.gallery.title") }}</h2>
        <p class="section-lead">{{ $t("landing.gallery.lead") }}</p>
      </div>
      <div class="gallery-grid">
        <div class="shot-card shot-one">
          <img
            class="shot-image"
            :src="assets.bg1"
            :alt="$t('landing.gallery.shots.table.alt')"
          />
          <div class="shot-title">
            {{ $t("landing.gallery.shots.table.title") }}
          </div>
          <div class="shot-sub">
            {{ $t("landing.gallery.shots.table.desc") }}
          </div>
          <div class="shot-ui">
            <div class="shot-bar"></div>
            <div class="shot-block"></div>
            <div class="shot-block short"></div>
          </div>
        </div>
        <div class="shot-card shot-two">
          <img
            class="shot-image"
            :src="assets.bg2"
            :alt="$t('landing.gallery.shots.hero.alt')"
          />
          <div class="shot-title">
            {{ $t("landing.gallery.shots.hero.title") }}
          </div>
          <div class="shot-sub">
            {{ $t("landing.gallery.shots.hero.desc") }}
          </div>
          <div class="shot-ui">
            <div class="shot-line"></div>
            <div class="shot-line"></div>
            <div class="shot-line wide"></div>
          </div>
        </div>
        <div class="shot-card shot-three">
          <img
            class="shot-image"
            :src="assets.background"
            :alt="$t('landing.gallery.shots.lore.alt')"
          />
          <div class="shot-title">
            {{ $t("landing.gallery.shots.lore.title") }}
          </div>
          <div class="shot-sub">
            {{ $t("landing.gallery.shots.lore.desc") }}
          </div>
          <div class="shot-ui">
            <div class="shot-chip"></div>
            <div class="shot-chip"></div>
            <div class="shot-chip"></div>
          </div>
        </div>
      </div>
      <div class="gallery-strip">
        <div class="strip-card">
          <div class="strip-title">
            {{ $t("landing.gallery.strip.fantasy.title") }}
          </div>
          <p>{{ $t("landing.gallery.strip.fantasy.desc") }}</p>
        </div>
        <div class="strip-card">
          <div class="strip-title">
            {{ $t("landing.gallery.strip.infographics.title") }}
          </div>
          <p>{{ $t("landing.gallery.strip.infographics.desc") }}</p>
        </div>
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
</script>
