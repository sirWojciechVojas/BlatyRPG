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
          <div class="stat-value">{{ $t(item.value) }}</div>
          <div class="stat-label">{{ $t(item.label) }}</div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const items = [
  {
    value: "landing.stats.values.account",
    label: "landing.stats.cards.account",
  },
  { value: "landing.stats.values.tables", label: "landing.stats.cards.tables" },
  {
    value: "landing.stats.values.together",
    label: "landing.stats.cards.together",
  },
  { value: "landing.stats.values.roles", label: "landing.stats.cards.roles" },
];
</script>
