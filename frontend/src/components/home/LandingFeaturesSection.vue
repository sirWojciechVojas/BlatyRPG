<template>
  <section class="section features-section parallax" id="features">
    <div class="section-inner">
      <div class="section-head">
        <p class="eyebrow">{{ $t("landing.features.eyebrow") }}</p>
        <h2>{{ $t("landing.features.title") }}</h2>
        <p class="section-lead">{{ $t("landing.features.lead") }}</p>
      </div>
      <div class="feature-grid">
        <article class="feature-card" v-for="item in items" :key="item.key">
          <div class="feature-icon" v-html="item.icon" aria-hidden="true"></div>
          <h3>{{ $t(item.title) }}</h3>
          <p>{{ $t(item.desc) }}</p>
          <div class="feature-meta">
            <span>{{ $t(item.meta1) }}</span>
            <span>{{ $t(item.meta2) }}</span>
            <span>{{ $t(item.meta3) }}</span>
          </div>
        </article>
      </div>
      <div class="feature-callout">
        <div class="callout-card">
          <h3>{{ $t("landing.features.callout.gm.title") }}</h3>
          <p>{{ $t("landing.features.callout.gm.desc") }}</p>
          <div class="callout-list">
            <span>{{ $t("landing.features.callout.gm.item1") }}</span>
            <span>{{ $t("landing.features.callout.gm.item2") }}</span>
            <span>{{ $t("landing.features.callout.gm.item3") }}</span>
          </div>
        </div>
        <div class="callout-card">
          <h3>{{ $t("landing.features.callout.player.title") }}</h3>
          <p>{{ $t("landing.features.callout.player.desc") }}</p>
          <div class="callout-list">
            <span>{{ $t("landing.features.callout.player.item1") }}</span>
            <span>{{ $t("landing.features.callout.player.item2") }}</span>
            <span>{{ $t("landing.features.callout.player.item3") }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const items = [
  {
    key: "maps",
    icon: '<svg viewBox="0 0 24 24"><path d="M4 5h16v10H4z" /><path d="M4 17h6v2H4zM14 17h6v2h-6z" /></svg>',
    title: "landing.features.items.maps.title",
    desc: "landing.features.items.maps.desc",
    meta1: "landing.features.items.maps.meta1",
    meta2: "landing.features.items.maps.meta2",
    meta3: "landing.features.items.maps.meta3",
  },
  {
    key: "time",
    icon: '<svg viewBox="0 0 24 24"><path d="M12 4c4 0 7 3 7 7s-3 7-7 7-7-3-7-7 3-7 7-7z" /><path d="M12 8v4l3 3" /></svg>',
    title: "landing.features.items.time.title",
    desc: "landing.features.items.time.desc",
    meta1: "landing.features.items.time.meta1",
    meta2: "landing.features.items.time.meta2",
    meta3: "landing.features.items.time.meta3",
  },
  {
    key: "characters",
    icon: '<svg viewBox="0 0 24 24"><path d="M6 6h12v12H6z" /><path d="M9 9h6v6H9z" /></svg>',
    title: "landing.features.items.characters.title",
    desc: "landing.features.items.characters.desc",
    meta1: "landing.features.items.characters.meta1",
    meta2: "landing.features.items.characters.meta2",
    meta3: "landing.features.items.characters.meta3",
  },
  {
    key: "narration",
    icon: '<svg viewBox="0 0 24 24"><path d="M5 4h14v6H5z" /><path d="M5 12h14v8H5z" /></svg>',
    title: "landing.features.items.narration.title",
    desc: "landing.features.items.narration.desc",
    meta1: "landing.features.items.narration.meta1",
    meta2: "landing.features.items.narration.meta2",
    meta3: "landing.features.items.narration.meta3",
  },
  {
    key: "audio",
    icon: '<svg viewBox="0 0 24 24"><path d="M4 12h16" /><path d="M8 8l-4 4 4 4" /><path d="M16 8l4 4-4 4" /></svg>',
    title: "landing.features.items.audio.title",
    desc: "landing.features.items.audio.desc",
    meta1: "landing.features.items.audio.meta1",
    meta2: "landing.features.items.audio.meta2",
    meta3: "landing.features.items.audio.meta3",
  },
  {
    key: "modules",
    icon: '<svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z" /><path d="M8 5v14" /><path d="M16 5v14" /></svg>',
    title: "landing.features.items.modules.title",
    desc: "landing.features.items.modules.desc",
    meta1: "landing.features.items.modules.meta1",
    meta2: "landing.features.items.modules.meta2",
    meta3: "landing.features.items.modules.meta3",
  },
];
</script>
