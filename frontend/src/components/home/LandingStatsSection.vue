<template>
  <section class="section stats-section" id="stats">
    <div class="section-inner">
      <div class="section-head">
        <p class="eyebrow">{{ $t("landing.stats.eyebrow") }}</p>
        <h2>{{ $t("landing.stats.title") }}</h2>
        <p class="section-lead">{{ $t("landing.stats.lead") }}</p>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">99.98%</div>
          <div class="stat-label">{{ $t("landing.stats.cards.uptime") }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">1.6x</div>
          <div class="stat-label">
            {{ $t("landing.stats.cards.fasterTurns") }}
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-value">8k</div>
          <div class="stat-label">{{ $t("landing.stats.cards.textures") }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">30+</div>
          <div class="stat-label">
            {{ $t("landing.stats.cards.starterModules") }}
          </div>
        </div>
      </div>
      <div class="quote-grid">
        <div class="quote-card">
          <p>"{{ $t("landing.stats.quotes.first.text") }}"</p>
          <div class="quote-author">
            {{ $t("landing.stats.quotes.first.author") }}
          </div>
        </div>
        <div class="quote-card">
          <p>"{{ $t("landing.stats.quotes.second.text") }}"</p>
          <div class="quote-author">
            {{ $t("landing.stats.quotes.second.author") }}
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
