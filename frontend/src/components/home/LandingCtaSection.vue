<template>
  <section class="section cta-section" id="cta">
    <div class="cta-panel">
      <p class="eyebrow">{{ $t("landing.cta.eyebrow") }}</p>
      <h2>{{ $t("landing.cta.title") }}</h2>
      <p class="section-lead">{{ $t("landing.cta.lead") }}</p>
      <div class="cta-actions">
        <router-link class="cta-btn large" :to="{ name: 'register' }">
          {{ $t("landing.cta.primaryCta") }}
        </router-link>
        <router-link class="ghost-btn" :to="{ name: 'dice' }">
          {{ $t("landing.cta.secondaryCta") }}
        </router-link>
      </div>
      <div class="cta-foot">{{ $t("landing.cta.foot") }}</div>
      <img
        class="cta-seal"
        :src="assets.dice20"
        :alt="$t('landing.cta.diceAlt')"
      />
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
