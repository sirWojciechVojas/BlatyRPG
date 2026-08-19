<template>
  <section class="section modules-section parallax" id="modules">
    <div class="section-inner">
      <div class="section-head">
        <p class="eyebrow">{{ $t("landing.modules.eyebrow") }}</p>
        <h2>{{ $t("landing.modules.title") }}</h2>
        <p class="section-lead">{{ $t("landing.modules.lead") }}</p>
      </div>
      <div class="modules-layout">
        <div class="module-diagram">
          <svg class="module-lines" viewBox="0 0 600 360" aria-hidden="true">
            <line x1="300" y1="180" x2="300" y2="40" />
            <line x1="300" y1="180" x2="110" y2="110" />
            <line x1="300" y1="180" x2="490" y2="110" />
            <line x1="300" y1="180" x2="130" y2="300" />
            <line x1="300" y1="180" x2="470" y2="300" />
            <line x1="300" y1="180" x2="300" y2="330" />
          </svg>
          <div class="module-node core">
            {{ $t("landing.modules.nodes.engine") }}
          </div>
          <div class="module-node map">
            {{ $t("landing.modules.nodes.maps") }}
          </div>
          <div class="module-node chars">
            {{ $t("landing.modules.nodes.characters") }}
          </div>
          <div class="module-node journal">
            {{ $t("landing.modules.nodes.journal") }}
          </div>
          <div class="module-node rules">
            {{ $t("landing.modules.nodes.rules") }}
          </div>
          <div class="module-node audio">
            {{ $t("landing.modules.nodes.audio") }}
          </div>
          <div class="module-node analytics">
            {{ $t("landing.modules.nodes.stats") }}
          </div>
        </div>
        <div class="modules-info">
          <div class="module-card" v-for="card in cards" :key="card.key">
            <h3>{{ $t(card.title) }}</h3>
            <p>{{ $t(card.desc) }}</p>
            <div class="module-tags">
              <span>{{ $t(card.tag1) }}</span>
              <span>{{ $t(card.tag2) }}</span>
              <span>{{ $t(card.tag3) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const cards = [
  {
    key: "integrations",
    title: "landing.modules.cards.integrations.title",
    desc: "landing.modules.cards.integrations.desc",
    tag1: "landing.modules.cards.integrations.tag1",
    tag2: "landing.modules.cards.integrations.tag2",
    tag3: "landing.modules.cards.integrations.tag3",
  },
  {
    key: "stability",
    title: "landing.modules.cards.stability.title",
    desc: "landing.modules.cards.stability.desc",
    tag1: "landing.modules.cards.stability.tag1",
    tag2: "landing.modules.cards.stability.tag2",
    tag3: "landing.modules.cards.stability.tag3",
  },
  {
    key: "growth",
    title: "landing.modules.cards.growth.title",
    desc: "landing.modules.cards.growth.desc",
    tag1: "landing.modules.cards.growth.tag1",
    tag2: "landing.modules.cards.growth.tag2",
    tag3: "landing.modules.cards.growth.tag3",
  },
];
</script>
