<template>
  <div class="home-page" :style="styleVars">
    <header class="topbar">
      <button type="button" class="brand" @click="scrollTo('hero')">
        <span class="brand-mark">BR</span>
        <span class="brand-copy">
          <span class="brand-title">{{ $t("landing.brand.title") }}</span>
          <span class="brand-sub">{{ $t("landing.brand.subtitle") }}</span>
        </span>
      </button>
      <nav class="nav-links">
        <button type="button" class="nav-link" @click="scrollTo('features')">
          {{ $t("landing.nav.features") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('gallery')">
          {{ $t("landing.nav.gallery") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('modules')">
          {{ $t("landing.nav.modules") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('stats')">
          {{ $t("landing.nav.stats") }}
        </button>
        <button type="button" class="nav-link" @click="scrollTo('cta')">
          {{ $t("landing.nav.start") }}
        </button>
      </nav>
      <div class="topbar-actions">
        <router-link class="ghost-link" to="/dice">
          {{ $t("landing.nav.dice") }}
        </router-link>
        <button type="button" class="cta-btn small" @click="scrollTo('cta')">
          {{ $t("landing.nav.startSession") }}
        </button>
      </div>
    </header>

    <section class="hero" id="hero">
      <div class="hero-content">
        <p class="eyebrow">{{ $t("landing.hero.eyebrow") }}</p>
        <img class="hero-logo" :src="assets.logo" :alt="$t('landing.hero.logoAlt')" />
        <h1>{{ $t("landing.hero.title") }}</h1>
        <p class="lead">{{ $t("landing.hero.lead") }}</p>
        <div class="hero-actions">
          <button type="button" class="cta-btn" @click="scrollTo('cta')">
            {{ $t("landing.hero.primaryCta") }}
          </button>
          <button type="button" class="ghost-btn" @click="scrollTo('gallery')">
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
            <span class="metric-label">{{ $t("landing.hero.metrics.scenes") }}</span>
          </div>
          <div class="metric">
            <span class="metric-value">48 ms</span>
            <span class="metric-label">{{ $t("landing.hero.metrics.latency") }}</span>
          </div>
          <div class="metric">
            <span class="metric-value">24/7</span>
            <span class="metric-label">{{ $t("landing.hero.metrics.hosting") }}</span>
          </div>
        </div>
      </div>
      <div class="hero-media">
        <div class="screen-mock">
          <div class="screen-top">
            <div class="screen-dots">
              <span></span>
              <span></span>
              <span></span>
            </div>
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
                <div class="side-title">{{ $t("landing.hero.screen.party") }}</div>
                <div class="avatar-row">
                  <span class="avatar"></span>
                  <span class="avatar"></span>
                  <span class="avatar"></span>
                  <span class="avatar"></span>
                </div>
                <div class="side-meter">
                  <span></span>
                </div>
              </div>
              <div class="side-card log-card">
                <div class="side-title">{{ $t("landing.hero.screen.journal") }}</div>
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
          <div class="floating-title">{{ $t("landing.hero.cards.fogTitle") }}</div>
          <p>{{ $t("landing.hero.cards.fogText") }}</p>
        </div>
      </div>
    </section>

    <section class="usp-strip" id="usp">
      <div class="usp-card">
        <span class="usp-title">{{ $t("landing.usp.scenes.title") }}</span>
        <span class="usp-desc">{{ $t("landing.usp.scenes.desc") }}</span>
      </div>
      <div class="usp-card">
        <span class="usp-title">{{ $t("landing.usp.narration.title") }}</span>
        <span class="usp-desc">{{ $t("landing.usp.narration.desc") }}</span>
      </div>
      <div class="usp-card">
        <span class="usp-title">{{ $t("landing.usp.characters.title") }}</span>
        <span class="usp-desc">{{ $t("landing.usp.characters.desc") }}</span>
      </div>
      <div class="usp-card">
        <span class="usp-title">{{ $t("landing.usp.library.title") }}</span>
        <span class="usp-desc">{{ $t("landing.usp.library.desc") }}</span>
      </div>
    </section>

    <section class="section features-section parallax" id="features">
      <div class="section-inner">
        <div class="section-head">
          <p class="eyebrow">{{ $t("landing.features.eyebrow") }}</p>
          <h2>{{ $t("landing.features.title") }}</h2>
          <p class="section-lead">{{ $t("landing.features.lead") }}</p>
        </div>
        <div class="feature-grid">
          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 5h16v10H4z" />
                <path d="M4 17h6v2H4zM14 17h6v2h-6z" />
              </svg>
            </div>
            <h3>{{ $t("landing.features.items.maps.title") }}</h3>
            <p>{{ $t("landing.features.items.maps.desc") }}</p>
            <div class="feature-meta">
              <span>{{ $t("landing.features.items.maps.meta1") }}</span>
              <span>{{ $t("landing.features.items.maps.meta2") }}</span>
              <span>{{ $t("landing.features.items.maps.meta3") }}</span>
            </div>
          </article>
          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 4c4 0 7 3 7 7s-3 7-7 7-7-3-7-7 3-7 7-7z" />
                <path d="M12 8v4l3 3" />
              </svg>
            </div>
            <h3>{{ $t("landing.features.items.time.title") }}</h3>
            <p>{{ $t("landing.features.items.time.desc") }}</p>
            <div class="feature-meta">
              <span>{{ $t("landing.features.items.time.meta1") }}</span>
              <span>{{ $t("landing.features.items.time.meta2") }}</span>
              <span>{{ $t("landing.features.items.time.meta3") }}</span>
            </div>
          </article>
          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 6h12v12H6z" />
                <path d="M9 9h6v6H9z" />
              </svg>
            </div>
            <h3>{{ $t("landing.features.items.characters.title") }}</h3>
            <p>{{ $t("landing.features.items.characters.desc") }}</p>
            <div class="feature-meta">
              <span>{{ $t("landing.features.items.characters.meta1") }}</span>
              <span>{{ $t("landing.features.items.characters.meta2") }}</span>
              <span>{{ $t("landing.features.items.characters.meta3") }}</span>
            </div>
          </article>
          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 4h14v6H5z" />
                <path d="M5 12h14v8H5z" />
              </svg>
            </div>
            <h3>{{ $t("landing.features.items.narration.title") }}</h3>
            <p>{{ $t("landing.features.items.narration.desc") }}</p>
            <div class="feature-meta">
              <span>{{ $t("landing.features.items.narration.meta1") }}</span>
              <span>{{ $t("landing.features.items.narration.meta2") }}</span>
              <span>{{ $t("landing.features.items.narration.meta3") }}</span>
            </div>
          </article>
          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 12h16" />
                <path d="M8 8l-4 4 4 4" />
                <path d="M16 8l4 4-4 4" />
              </svg>
            </div>
            <h3>{{ $t("landing.features.items.audio.title") }}</h3>
            <p>{{ $t("landing.features.items.audio.desc") }}</p>
            <div class="feature-meta">
              <span>{{ $t("landing.features.items.audio.meta1") }}</span>
              <span>{{ $t("landing.features.items.audio.meta2") }}</span>
              <span>{{ $t("landing.features.items.audio.meta3") }}</span>
            </div>
          </article>
          <article class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h16v10H4z" />
                <path d="M8 5v14" />
                <path d="M16 5v14" />
              </svg>
            </div>
            <h3>{{ $t("landing.features.items.modules.title") }}</h3>
            <p>{{ $t("landing.features.items.modules.desc") }}</p>
            <div class="feature-meta">
              <span>{{ $t("landing.features.items.modules.meta1") }}</span>
              <span>{{ $t("landing.features.items.modules.meta2") }}</span>
              <span>{{ $t("landing.features.items.modules.meta3") }}</span>
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

    <section class="section gallery-section" id="gallery">
      <div class="section-inner">
        <div class="section-head">
          <p class="eyebrow">{{ $t("landing.gallery.eyebrow") }}</p>
          <h2>{{ $t("landing.gallery.title") }}</h2>
          <p class="section-lead">{{ $t("landing.gallery.lead") }}</p>
        </div>
        <div class="gallery-grid">
          <div class="shot-card shot-one">
            <img class="shot-image" :src="assets.bg1" :alt="$t('landing.gallery.shots.table.alt')" />
            <div class="shot-title">{{ $t("landing.gallery.shots.table.title") }}</div>
            <div class="shot-sub">{{ $t("landing.gallery.shots.table.desc") }}</div>
            <div class="shot-ui">
              <div class="shot-bar"></div>
              <div class="shot-block"></div>
              <div class="shot-block short"></div>
            </div>
          </div>
          <div class="shot-card shot-two">
            <img class="shot-image" :src="assets.bg2" :alt="$t('landing.gallery.shots.hero.alt')" />
            <div class="shot-title">{{ $t("landing.gallery.shots.hero.title") }}</div>
            <div class="shot-sub">{{ $t("landing.gallery.shots.hero.desc") }}</div>
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
            <div class="shot-title">{{ $t("landing.gallery.shots.lore.title") }}</div>
            <div class="shot-sub">{{ $t("landing.gallery.shots.lore.desc") }}</div>
            <div class="shot-ui">
              <div class="shot-chip"></div>
              <div class="shot-chip"></div>
              <div class="shot-chip"></div>
            </div>
          </div>
        </div>
        <div class="gallery-strip">
          <div class="strip-card">
            <div class="strip-title">{{ $t("landing.gallery.strip.fantasy.title") }}</div>
            <p>{{ $t("landing.gallery.strip.fantasy.desc") }}</p>
          </div>
          <div class="strip-card">
            <div class="strip-title">{{ $t("landing.gallery.strip.infographics.title") }}</div>
            <p>{{ $t("landing.gallery.strip.infographics.desc") }}</p>
          </div>
        </div>
      </div>
    </section>

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
            <div class="module-node core">{{ $t("landing.modules.nodes.engine") }}</div>
            <div class="module-node map">{{ $t("landing.modules.nodes.maps") }}</div>
            <div class="module-node chars">{{ $t("landing.modules.nodes.characters") }}</div>
            <div class="module-node journal">{{ $t("landing.modules.nodes.journal") }}</div>
            <div class="module-node rules">{{ $t("landing.modules.nodes.rules") }}</div>
            <div class="module-node audio">{{ $t("landing.modules.nodes.audio") }}</div>
            <div class="module-node analytics">{{ $t("landing.modules.nodes.stats") }}</div>
          </div>
          <div class="modules-info">
            <div class="module-card">
              <h3>{{ $t("landing.modules.cards.integrations.title") }}</h3>
              <p>{{ $t("landing.modules.cards.integrations.desc") }}</p>
              <div class="module-tags">
                <span>{{ $t("landing.modules.cards.integrations.tag1") }}</span>
                <span>{{ $t("landing.modules.cards.integrations.tag2") }}</span>
                <span>{{ $t("landing.modules.cards.integrations.tag3") }}</span>
              </div>
            </div>
            <div class="module-card">
              <h3>{{ $t("landing.modules.cards.stability.title") }}</h3>
              <p>{{ $t("landing.modules.cards.stability.desc") }}</p>
              <div class="module-tags">
                <span>{{ $t("landing.modules.cards.stability.tag1") }}</span>
                <span>{{ $t("landing.modules.cards.stability.tag2") }}</span>
                <span>{{ $t("landing.modules.cards.stability.tag3") }}</span>
              </div>
            </div>
            <div class="module-card">
              <h3>{{ $t("landing.modules.cards.growth.title") }}</h3>
              <p>{{ $t("landing.modules.cards.growth.desc") }}</p>
              <div class="module-tags">
                <span>{{ $t("landing.modules.cards.growth.tag1") }}</span>
                <span>{{ $t("landing.modules.cards.growth.tag2") }}</span>
                <span>{{ $t("landing.modules.cards.growth.tag3") }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

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
            <div class="stat-label">{{ $t("landing.stats.cards.fasterTurns") }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">8k</div>
            <div class="stat-label">{{ $t("landing.stats.cards.textures") }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">30+</div>
            <div class="stat-label">{{ $t("landing.stats.cards.starterModules") }}</div>
          </div>
        </div>
        <div class="quote-grid">
          <div class="quote-card">
            <p>"{{ $t("landing.stats.quotes.first.text") }}"</p>
            <div class="quote-author">{{ $t("landing.stats.quotes.first.author") }}</div>
          </div>
          <div class="quote-card">
            <p>"{{ $t("landing.stats.quotes.second.text") }}"</p>
            <div class="quote-author">{{ $t("landing.stats.quotes.second.author") }}</div>
          </div>
        </div>
      </div>
    </section>

    <section class="section cta-section parallax" id="cta">
      <div class="cta-panel">
        <p class="eyebrow">{{ $t("landing.cta.eyebrow") }}</p>
        <h2>{{ $t("landing.cta.title") }}</h2>
        <p class="section-lead">{{ $t("landing.cta.lead") }}</p>
        <div class="cta-actions">
          <button type="button" class="cta-btn large">
            {{ $t("landing.cta.primaryCta") }}
          </button>
          <router-link class="ghost-link" to="/dice">
            {{ $t("landing.cta.secondaryCta") }}
          </router-link>
        </div>
        <div class="cta-foot">{{ $t("landing.cta.foot") }}</div>
        <img class="cta-seal" :src="assets.dice20" :alt="$t('landing.cta.diceAlt')" />
      </div>
    </section>

    <footer class="footer">
      <div>{{ $t("landing.footer.text") }}</div>
      <div class="footer-meta">{{ $t("landing.footer.meta") }}</div>
    </footer>
  </div>
</template>

<script>
import bg1 from "@/assets/app-ui/img/bg1.jpg";
import bg2 from "@/assets/app-ui/img/bg2.jpg";
import background from "@/assets/app-ui/img/background.jpg";
import navbar from "@/assets/app-ui/gfx/navbar-bg.jpg";
import logo from "@/assets/app-ui/img/BlatyRPG-logo.png";
import frame from "@/assets/app-ui/img/frameUni.png";
import gui from "@/assets/app-ui/gfx/GUISTBSC.png";
import diceFrame from "@/assets/app-ui/gfx/dice-frame.png";
import charStats from "@/assets/app-ui/img/CharacterStats-bg.png";
import chatbox from "@/assets/app-ui/img/chatbox.png";
import titleBar from "@/assets/app-ui/img/titleBar-center.png";
import dice20 from "@/assets/app-ui/img/dice20.png";

export default {
  name: "HomeView",
  data() {
    return {
      assets: {
        bg1,
        bg2,
        background,
        navbar,
        logo,
        frame,
        gui,
        diceFrame,
        charStats,
        chatbox,
        titleBar,
        dice20,
      },
    };
  },
  computed: {
    styleVars() {
      return {
        "--sb-bg-page": `url("${this.assets.bg2}")`,
        "--sb-bg-hero": `url("${this.assets.background}")`,
        "--sb-navbar": `url("${this.assets.navbar}")`,
        "--sb-logo": `url("${this.assets.logo}")`,
        "--sb-frame": `url("${this.assets.frame}")`,
        "--sb-gui": `url("${this.assets.gui}")`,
        "--sb-map": `url("${this.assets.bg1}")`,
        "--sb-dice-frame": `url("${this.assets.diceFrame}")`,
        "--sb-charstats": `url("${this.assets.charStats}")`,
        "--sb-chatbox": `url("${this.assets.chatbox}")`,
        "--sb-titlebar": `url("${this.assets.titleBar}")`,
      };
    },
  },
  methods: {
    scrollTo(targetId) {
      const target = document.getElementById(targetId);
      if (!target) {
        return;
      }
      target.scrollIntoView({ behavior: "smooth", block: "start" });
    },
  },
};
</script>

<style>
@import url("https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&display=swap");

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  background: #0a0908;
  color: #f6efe2;
}

body::-webkit-scrollbar {
  width: 10px;
}

body::-webkit-scrollbar-track {
  background: #120d0b;
}

body::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #c49a5a, #6a3f1f);
  border-radius: 10px;
  border: 1px solid #3b2b19;
}

</style>

<style scoped>
.home-page {
  --ink: #f6efe2;
  --muted: #c7b8a1;
  --gold: #d8b778;
  --gold-bright: #f1d59c;
  --ember: #b35830;
  --ice: #6da9bf;
  --panel: rgba(16, 11, 9, 0.82);
  --panel-soft: rgba(18, 12, 10, 0.6);
  --border: rgba(216, 183, 120, 0.3);
  --glow: rgba(241, 200, 129, 0.45);
  font-family: "EB Garamond", Garamond, "Times New Roman", serif;
  color: var(--ink);
  text-align: left;
  background:
    radial-gradient(1200px 600px at 10% 10%, rgba(84, 53, 27, 0.55), transparent 65%),
    radial-gradient(900px 500px at 85% 5%, rgba(39, 66, 86, 0.45), transparent 70%),
    linear-gradient(180deg, rgba(11, 8, 7, 0.92) 0%, rgba(25, 18, 13, 0.9) 45%, rgba(11, 8, 7, 0.92) 100%);
  background-size: auto, auto, auto, cover, cover;
  background-position: center;
  background-blend-mode: screen;
  min-height: 100vh;
  position: relative;
  overflow: hidden;
  padding-bottom: 40px;
}

.home-page::before {
  content: "";
  position: fixed;
  inset: 0;
  background:
    repeating-linear-gradient(
      135deg,
      rgba(255, 255, 255, 0.02) 0px,
      rgba(255, 255, 255, 0.02) 2px,
      transparent 2px,
      transparent 6px
    );
  opacity: 0.3;
  pointer-events: none;
  z-index: 0;
}

.home-page > * {
  position: relative;
  z-index: 1;
}

.topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 3px 6vw;
  background-image:
    var(--sb-navbar),
    linear-gradient(180deg, rgba(10, 8, 7, 0.92), rgba(10, 8, 7, 0.55));
  background-size: cover;
  background-position: top;
  background-blend-mode: multiply;
  border-bottom: 1px solid rgba(216, 183, 120, 0.2);
  backdrop-filter: blur(12px);
  z-index: 10;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  background: transparent;
  border: none;
  color: var(--ink);
  cursor: pointer;
  padding: 0;
}

.brand-mark {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  background: var(--sb-logo) center/cover no-repeat;
  border: 1px solid rgba(216, 183, 120, 0.6);
  color: transparent;
  text-indent: -9999px;
  overflow: hidden;
  box-shadow: 0 0 12px rgba(0, 0, 0, 0.5);
}

.brand-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: left;
}

.brand-title {
  font-size: 1.1rem;
  text-transform: uppercase;
  letter-spacing: 0.2em;
}

.brand-sub {
  font-size: 0.85rem;
  color: var(--muted);
}

.nav-links {
  display: flex;
  gap: 18px;
}

.nav-link {
  background: transparent;
  border: none;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.18em;
  font-size: 0.7rem;
  cursor: pointer;
  padding: 6px 0;
  transition: color 0.2s ease;
}

.nav-link:hover {
  color: var(--gold-bright);
}

.topbar-actions {
  display: flex;
  gap: 16px;
  align-items: center;
}

.ghost-link {
  color: var(--gold);
  text-decoration: none;
  border: 1px solid rgba(216, 183, 120, 0.4);
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 0.85rem;
  letter-spacing: 0.08em;
  transition: border 0.2s ease, color 0.2s ease;
}

.ghost-link:hover {
  border-color: var(--gold-bright);
  color: var(--gold-bright);
}

.cta-btn {
  background: linear-gradient(135deg, #f1d59c, #b3562d);
  color: #1d120b;
  border: none;
  padding: 12px 20px;
  border-radius: 999px;
  font-weight: 600;
  letter-spacing: 0.08em;
  cursor: pointer;
  box-shadow: 0 18px 30px rgba(0, 0, 0, 0.35);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.cta-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 26px 40px rgba(0, 0, 0, 0.45);
}

.cta-btn.small {
  padding: 10px 16px;
  font-size: 0.85rem;
}

.cta-btn.large {
  padding: 14px 28px;
  font-size: 1rem;
}

.ghost-btn {
  background: transparent;
  border: 1px solid rgba(216, 183, 120, 0.4);
  color: var(--ink);
  padding: 12px 20px;
  border-radius: 999px;
  font-size: 0.95rem;
  cursor: pointer;
  transition: border 0.2s ease, color 0.2s ease;
}

.ghost-btn:hover {
  color: var(--gold-bright);
  border-color: var(--gold-bright);
}

.hero {
  padding: 150px 6vw 80px;
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 36px;
  align-items: center;
}

.hero-content {
  grid-column: span 6;
}

.hero-media {
  grid-column: span 6;
  position: relative;
}

.eyebrow {
  text-transform: uppercase;
  letter-spacing: 0.22em;
  font-size: 0.72rem;
  color: var(--gold);
  margin-bottom: 14px;
}

.hero-logo {
  display: block;
  width: 220px;
  max-width: 80%;
  margin: 8px 0 14px;
  filter: drop-shadow(0 12px 20px rgba(0, 0, 0, 0.55));
}

.hero h1 {
  font-size: 3.2rem;
  line-height: 1.05;
  margin: 0 0 18px;
}

.lead {
  font-size: 1.2rem;
  color: var(--muted);
  line-height: 1.6;
  margin: 0 0 24px;
}

.hero-actions {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}

.hero-chips {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}

.hero-chips span {
  padding: 6px 14px;
  border-radius: 999px;
  background: rgba(16, 11, 9, 0.7);
  border: 1px solid rgba(216, 183, 120, 0.25);
  font-size: 0.85rem;
  letter-spacing: 0.06em;
}

.hero-metrics {
  display: flex;
  gap: 26px;
  flex-wrap: wrap;
}

.metric {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 120px;
}

.metric-value {
  font-size: 1.6rem;
  color: var(--gold-bright);
  font-weight: 600;
}

.metric-label {
  font-size: 0.9rem;
  color: var(--muted);
}

.screen-mock {
  position: relative;
  overflow: hidden;
  background: linear-gradient(160deg, rgba(36, 25, 18, 0.95), rgba(12, 9, 8, 0.92));
  border: 1px solid rgba(216, 183, 120, 0.35);
  border-radius: 18px;
  padding: 16px;
  box-shadow: 0 30px 70px rgba(0, 0, 0, 0.5);
}

.screen-mock::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--sb-frame) center/cover no-repeat;
  opacity: 0.32;
  pointer-events: none;
}

.screen-mock::after {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--sb-gui) center/cover no-repeat;
  opacity: 0.12;
  mix-blend-mode: screen;
  pointer-events: none;
}

.screen-mock > * {
  position: relative;
  z-index: 1;
}

.screen-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  font-size: 0.85rem;
  color: var(--muted);
}

.screen-dots {
  display: flex;
  gap: 6px;
}

.screen-dots span {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: rgba(216, 183, 120, 0.6);
}

.screen-title {
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-size: 0.7rem;
}

.screen-badge {
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(177, 88, 48, 0.25);
  border: 1px solid rgba(177, 88, 48, 0.6);
  color: var(--gold-bright);
  font-size: 0.65rem;
  letter-spacing: 0.2em;
}

.screen-body {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 16px;
}

.map-preview {
  position: relative;
  min-height: 280px;
  border-radius: 14px;
  background:
    linear-gradient(135deg, rgba(43, 31, 22, 0.95), rgba(10, 16, 22, 0.9)),
    repeating-linear-gradient(
      90deg,
      rgba(255, 255, 255, 0.06) 0px,
      rgba(255, 255, 255, 0.06) 1px,
      transparent 1px,
      transparent 32px
    ),
    repeating-linear-gradient(
      0deg,
      rgba(255, 255, 255, 0.06) 0px,
      rgba(255, 255, 255, 0.06) 1px,
      transparent 1px,
      transparent 32px
    ),
    var(--sb-map) center/cover no-repeat;
  overflow: hidden;
  border: 1px solid rgba(216, 183, 120, 0.2);
}

.map-preview::after {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 70% 40%, rgba(255, 255, 255, 0.1), transparent 60%);
  mix-blend-mode: screen;
}

.map-token {
  position: absolute;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: radial-gradient(circle, #f1d59c 0%, #6a3f1f 70%);
  box-shadow: 0 0 12px rgba(241, 200, 129, 0.8);
}

.token-one {
  top: 30%;
  left: 45%;
}

.token-two {
  top: 55%;
  left: 60%;
}

.token-three {
  top: 65%;
  left: 35%;
}

.map-ping {
  position: absolute;
  width: 70px;
  height: 70px;
  border-radius: 50%;
  border: 1px solid rgba(241, 200, 129, 0.6);
  top: 42%;
  left: 40%;
  animation: pulse 3s infinite;
}

.side-panels {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.side-card {
  background: rgba(12, 9, 8, 0.8);
  border: 1px solid rgba(216, 183, 120, 0.2);
  border-radius: 12px;
  padding: 12px;
}

.side-title {
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-size: 0.65rem;
  color: var(--gold);
  margin-bottom: 10px;
}

.avatar-row {
  display: flex;
  gap: 6px;
  margin-bottom: 10px;
}

.avatar {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: linear-gradient(135deg, rgba(241, 213, 156, 0.8), rgba(107, 63, 31, 0.8));
}

.side-meter {
  height: 6px;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 999px;
  overflow: hidden;
}

.side-meter span {
  display: block;
  height: 100%;
  width: 70%;
  background: linear-gradient(90deg, #f1d59c, #b3562d);
}

.log-card .log-line {
  height: 6px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  margin-bottom: 8px;
}

.log-card .log-line.wide {
  width: 90%;
}

.floating-card {
  position: absolute;
  right: -30px;
  top: 60px;
  width: 180px;
  background: rgba(12, 9, 8, 0.82);
  border: 1px solid rgba(216, 183, 120, 0.35);
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
  animation: float 6s ease-in-out infinite;
}

.card-two {
  top: 240px;
  right: 40px;
  animation-delay: 1.6s;
}

.floating-title {
  font-size: 0.95rem;
  color: var(--gold-bright);
  margin-bottom: 8px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.floating-card p {
  margin: 0;
  font-size: 0.85rem;
  color: var(--muted);
}

.usp-strip {
  padding: 0 6vw 80px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.usp-card {
  position: relative;
  overflow: hidden;
  background: var(--panel);
  border: 1px solid rgba(216, 183, 120, 0.25);
  border-radius: 16px;
  padding: 18px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.usp-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--sb-frame) center/cover no-repeat;
  opacity: 0.18;
  pointer-events: none;
}

.usp-card > * {
  position: relative;
  z-index: 1;
}

.usp-title {
  font-size: 1.1rem;
  color: var(--gold-bright);
  margin-bottom: 6px;
  display: block;
}

.usp-desc {
  font-size: 0.95rem;
  color: var(--muted);
}

.section {
  padding: 90px 6vw;
  scroll-margin-top: 100px;
}

.section-inner {
  max-width: 1200px;
  margin: 0 auto;
}

.section-head h2 {
  font-size: 2.6rem;
  margin: 0 0 16px;
}

.section-lead {
  font-size: 1.1rem;
  color: var(--muted);
  max-width: 640px;
  line-height: 1.6;
}

.parallax {
  background-image:
    var(--sb-map),
    radial-gradient(900px 600px at 20% 20%, rgba(177, 88, 48, 0.18), transparent 60%),
    radial-gradient(700px 500px at 80% 30%, rgba(109, 169, 191, 0.18), transparent 65%);
  background-size: cover;
  background-position: center;
  background-blend-mode: soft-light;
  background-attachment: fixed;
}

.feature-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 22px;
  margin-top: 40px;
}

.feature-card {
  background: var(--panel);
  border: 1px solid rgba(216, 183, 120, 0.25);
  border-radius: 18px;
  padding: 20px;
  box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.feature-icon {
  position: relative;
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: rgba(216, 183, 120, 0.15);
  display: grid;
  place-items: center;
  color: var(--gold-bright);
}

.feature-icon::before {
  content: "";
  position: absolute;
  inset: -6px;
  background: var(--sb-dice-frame) center/cover no-repeat;
  opacity: 0.5;
  pointer-events: none;
}

.feature-icon svg {
  width: 28px;
  height: 28px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  position: relative;
  z-index: 1;
}

.feature-card h3 {
  font-size: 1.3rem;
  margin: 0;
}

.feature-card p {
  margin: 0;
  color: var(--muted);
  line-height: 1.5;
}

.feature-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.feature-meta span {
  border: 1px solid rgba(216, 183, 120, 0.2);
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 0.75rem;
  color: var(--gold);
}

.feature-callout {
  margin-top: 36px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 22px;
}

.callout-card {
  background: linear-gradient(135deg, rgba(20, 13, 10, 0.9), rgba(12, 9, 8, 0.9));
  border: 1px solid rgba(216, 183, 120, 0.35);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
}

.callout-card h3 {
  margin: 0 0 10px;
  font-size: 1.4rem;
}

.callout-card p {
  margin: 0 0 16px;
  color: var(--muted);
  line-height: 1.5;
}

.callout-list {
  display: grid;
  gap: 8px;
}

.callout-list span {
  font-size: 0.9rem;
  color: var(--gold);
}

.gallery-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 22px;
  margin-top: 40px;
}

.shot-card {
  padding: 24px;
  border-radius: 20px;
  border: 1px solid rgba(216, 183, 120, 0.25);
  min-height: 220px;
  background: linear-gradient(140deg, rgba(28, 20, 14, 0.95), rgba(11, 9, 8, 0.9));
  position: relative;
  overflow: hidden;
}

.shot-card::after {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--sb-frame) center/cover no-repeat;
  opacity: 0.2;
  pointer-events: none;
}

.shot-card > * {
  position: relative;
  z-index: 1;
}

.shot-image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0.35;
  filter: saturate(0.85);
  z-index: 0;
}

.shot-one {
  background:
    radial-gradient(280px 160px at 20% 20%, rgba(216, 183, 120, 0.35), transparent 60%),
    linear-gradient(140deg, rgba(28, 20, 14, 0.95), rgba(11, 9, 8, 0.9));
}

.shot-two {
  background:
    radial-gradient(280px 160px at 80% 20%, rgba(109, 169, 191, 0.35), transparent 60%),
    linear-gradient(140deg, rgba(28, 20, 14, 0.95), rgba(11, 9, 8, 0.9));
}

.shot-three {
  background:
    radial-gradient(260px 160px at 20% 80%, rgba(177, 88, 48, 0.35), transparent 60%),
    linear-gradient(140deg, rgba(28, 20, 14, 0.95), rgba(11, 9, 8, 0.9));
}

.shot-title {
  font-size: 1.3rem;
  margin-bottom: 6px;
}

.shot-sub {
  color: var(--muted);
  margin-bottom: 18px;
}

.shot-ui {
  display: grid;
  gap: 8px;
}

.shot-bar {
  height: 12px;
  border-radius: 999px;
  background: rgba(216, 183, 120, 0.3);
}

.shot-block {
  height: 44px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.08);
}

.shot-block.short {
  height: 26px;
  width: 60%;
}

.shot-line {
  height: 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
}

.shot-line.wide {
  width: 85%;
}

.shot-chip {
  height: 22px;
  width: 50%;
  border-radius: 999px;
  background: rgba(177, 88, 48, 0.25);
  border: 1px solid rgba(177, 88, 48, 0.45);
}

.gallery-strip {
  margin-top: 32px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}

.strip-card {
  background: var(--panel-soft);
  border: 1px solid rgba(216, 183, 120, 0.2);
  border-radius: 16px;
  padding: 20px;
}

.strip-title {
  font-size: 1.2rem;
  color: var(--gold-bright);
  margin-bottom: 6px;
}

.modules-layout {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 28px;
  align-items: center;
  margin-top: 40px;
}

.module-diagram {
  position: relative;
  min-height: 360px;
  border-radius: 20px;
  background: rgba(10, 8, 7, 0.6);
  border: 1px solid rgba(216, 183, 120, 0.2);
  overflow: hidden;
}

.module-diagram::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--sb-gui) center/cover no-repeat;
  opacity: 0.16;
  pointer-events: none;
}

.module-diagram > * {
  position: relative;
  z-index: 1;
}

.module-lines {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  stroke: rgba(216, 183, 120, 0.35);
  stroke-width: 1.2;
}

.module-node {
  position: absolute;
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(16, 11, 9, 0.85);
  border: 1px solid rgba(216, 183, 120, 0.4);
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--gold-bright);
  transform: translate(-50%, -50%);
}

.module-node.core {
  top: 50%;
  left: 50%;
  background: linear-gradient(135deg, rgba(241, 213, 156, 0.3), rgba(177, 88, 48, 0.4));
  font-weight: 600;
}

.module-node.map {
  top: 12%;
  left: 50%;
}

.module-node.chars {
  top: 30%;
  left: 18%;
}

.module-node.journal {
  top: 30%;
  left: 82%;
}

.module-node.rules {
  top: 80%;
  left: 22%;
}

.module-node.audio {
  top: 80%;
  left: 78%;
}

.module-node.analytics {
  top: 92%;
  left: 50%;
}

.modules-info {
  display: grid;
  gap: 16px;
}

.module-card {
  position: relative;
  overflow: hidden;
  background: var(--panel);
  border: 1px solid rgba(216, 183, 120, 0.25);
  border-radius: 16px;
  padding: 18px;
}

.module-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--sb-charstats) center/cover no-repeat;
  opacity: 0.12;
  pointer-events: none;
}

.module-card > * {
  position: relative;
  z-index: 1;
}

.module-card h3 {
  margin: 0 0 8px;
}

.module-card p {
  margin: 0 0 12px;
  color: var(--muted);
}

.module-tags {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.module-tags span {
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(216, 183, 120, 0.25);
  color: var(--gold);
  font-size: 0.75rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
  margin-top: 32px;
}

.stat-card {
  background: var(--panel);
  border: 1px solid rgba(216, 183, 120, 0.2);
  border-radius: 16px;
  padding: 18px;
  text-align: center;
}

.stat-value {
  font-size: 1.8rem;
  color: var(--gold-bright);
  font-weight: 600;
}

.stat-label {
  margin-top: 6px;
  color: var(--muted);
}

.quote-grid {
  margin-top: 28px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.quote-card {
  position: relative;
  overflow: hidden;
  background: rgba(14, 10, 9, 0.7);
  border: 1px solid rgba(216, 183, 120, 0.2);
  border-radius: 16px;
  padding: 20px;
  color: var(--muted);
  line-height: 1.6;
}

.quote-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--sb-chatbox) center/cover no-repeat;
  opacity: 0.18;
  pointer-events: none;
}

.quote-card > * {
  position: relative;
  z-index: 1;
}

.quote-card p {
  margin: 0 0 12px;
}

.quote-author {
  color: var(--gold-bright);
  font-size: 0.9rem;
}

.cta-section {
  padding: 110px 6vw 140px;
  text-align: center;
}

.cta-panel {
  position: relative;
  max-width: 760px;
  margin: 0 auto;
  background: linear-gradient(145deg, rgba(20, 13, 10, 0.95), rgba(12, 9, 8, 0.95));
  border: 1px solid rgba(216, 183, 120, 0.35);
  border-radius: 24px;
  padding: 36px;
  box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
}

.cta-panel::before {
  content: "";
  position: absolute;
  top: 14px;
  left: 50%;
  width: 220px;
  height: 36px;
  transform: translateX(-50%);
  background: var(--sb-titlebar) center/contain no-repeat;
  opacity: 0.6;
  pointer-events: none;
}

.cta-panel h2 {
  margin: 12px 0 14px;
  font-size: 2.4rem;
}

.cta-panel .section-lead {
  margin: 0 auto 20px;
}

.cta-actions {
  display: flex;
  justify-content: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 18px;
}

.cta-foot {
  margin-top: 18px;
  color: var(--muted);
}

.cta-seal {
  position: absolute;
  right: 24px;
  bottom: -30px;
  width: 96px;
  height: auto;
  filter: drop-shadow(0 12px 20px rgba(0, 0, 0, 0.6));
}

.footer {
  padding: 20px 6vw 40px;
  display: flex;
  justify-content: space-between;
  color: var(--muted);
  font-size: 0.85rem;
}

.footer-meta {
  color: rgba(216, 183, 120, 0.7);
}

@keyframes float {
  0%,
  100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

@keyframes pulse {
  0% {
    transform: scale(0.9);
    opacity: 0.7;
  }
  70% {
    transform: scale(1.2);
    opacity: 0;
  }
  100% {
    opacity: 0;
  }
}

@media (max-width: 1100px) {
  .hero {
    grid-template-columns: 1fr;
    padding-top: 140px;
  }

  .hero-content,
  .hero-media {
    grid-column: span 12;
  }

  .screen-body {
    grid-template-columns: 1fr;
  }

  .floating-card {
    position: static;
    margin-top: 16px;
    width: 100%;
  }

  .usp-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .feature-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .gallery-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .modules-layout {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .topbar {
    flex-direction: column;
    gap: 12px;
  }

  .nav-links {
    flex-wrap: wrap;
    justify-content: center;
  }

  .hero h1 {
    font-size: 2.4rem;
  }

  .hero-logo {
    width: 180px;
  }

  .hero-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .usp-strip {
    grid-template-columns: 1fr;
  }

  .feature-grid,
  .feature-callout,
  .gallery-grid,
  .gallery-strip,
  .quote-grid,
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .footer {
    flex-direction: column;
    gap: 6px;
    text-align: center;
  }

  .parallax {
    background-attachment: scroll;
  }

  .cta-seal {
    position: static;
    margin: 18px auto 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .floating-card,
  .map-ping {
    animation: none;
  }
}
</style>

