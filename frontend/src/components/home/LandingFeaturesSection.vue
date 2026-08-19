<template>
  <section class="section features-section" id="features">
    <div class="section-inner">
      <div class="section-head">
        <p class="eyebrow">{{ $t("landing.features.eyebrow") }}</p>
        <h2>{{ $t("landing.features.title") }}</h2>
        <p class="section-lead">{{ $t("landing.features.lead") }}</p>
      </div>
      <div class="feature-grid">
        <article v-for="item in items" :key="item.key" class="feature-card">
          <span class="feature-icon" aria-hidden="true">{{ item.icon }}</span>
          <h3>{{ $t("landing.features.items." + item.key + ".title") }}</h3>
          <p>{{ $t("landing.features.items." + item.key + ".desc") }}</p>
        </article>
      </div>
      <div class="feature-callout">
        <div class="callout-card">
          <h3>{{ $t("landing.features.callout.gm.title") }}</h3>
          <p>{{ $t("landing.features.callout.gm.desc") }}</p>
        </div>
        <div class="callout-card">
          <h3>{{ $t("landing.features.callout.player.title") }}</h3>
          <p>{{ $t("landing.features.callout.player.desc") }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const items = [
  { key: "campaigns", icon: "◎" },
  { key: "maps", icon: "▦" },
  { key: "characters", icon: "♙" },
  { key: "chat", icon: "◌" },
  { key: "dice", icon: "◆" },
  { key: "handouts", icon: "▤" },
  { key: "scenarios", icon: "◇" },
  { key: "graphics", icon: "▧" },
  { key: "shop", icon: "¤" },
  { key: "music", icon: "♫" },
  { key: "gmTools", icon: "⚙" },
];
</script>
