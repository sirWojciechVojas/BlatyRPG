<template>
  <section class="section stats-section" id="stats">
    <div class="section-inner">
      <div class="section-head">
        <p class="eyebrow">{{ $t("landing.stats.eyebrow") }}</p>
        <h2>{{ $t("landing.stats.title") }}</h2>
        <p class="section-lead">{{ $t("landing.stats.lead") }}</p>
      </div>
      <div class="stats-grid">
        <div v-for="item in items" :key="item.value" class="stat-card">
          <div class="stat-value">{{ item.value }}</div>
          <div class="stat-label">{{ $t(item.label) }}</div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const items = [
  { value: "WebSocket", label: "landing.stats.cards.realtime" },
  { value: "3D", label: "landing.stats.cards.dice" },
  { value: "PL / EN", label: "landing.stats.cards.languages" },
  { value: "Self-hosted", label: "landing.stats.cards.hosting" },
];
</script>
