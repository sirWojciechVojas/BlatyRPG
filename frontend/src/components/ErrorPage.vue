<template>
  <section class="error-page">
    <div class="error-page__card">
      <p class="eyebrow">{{ eyebrow }}</p>
      <div class="error-page__headline">
        <span class="error-page__code">{{ code }}</span>
        <span class="error-page__title">{{ title }}</span>
      </div>
      <p class="lead">{{ lead }}</p>
      <div class="actions">
        <router-link class="cta-btn" :to="primaryTo">{{ primaryCta }}</router-link>
        <button type="button" class="ghost-btn" @click="goBack">
          {{ secondaryCta }}
        </button>
      </div>
      <p class="hint">
        {{ hint }}
        <router-link class="ghost-link" :to="aboutTo">{{ aboutLinkLabel }}</router-link>.
      </p>
    </div>
    <img class="error-page__dice" :src="assets.dice20" :alt="diceAlt" />
  </section>
</template>

<script>
import dice20 from "@/assets/app-ui/img/dice20.png";

export default {
  name: "ErrorPage",
  props: {
    code: {
      type: [String, Number],
      required: true,
    },
    eyebrow: {
      type: String,
      required: true,
    },
    title: {
      type: String,
      required: true,
    },
    lead: {
      type: String,
      required: true,
    },
    primaryCta: {
      type: String,
      required: true,
    },
    secondaryCta: {
      type: String,
      required: true,
    },
    hint: {
      type: String,
      required: true,
    },
    aboutLinkLabel: {
      type: String,
      required: true,
    },
    diceAlt: {
      type: String,
      required: true,
    },
    primaryTo: {
      type: String,
      default: "/",
    },
    aboutTo: {
      type: String,
      default: "/about",
    },
  },
  data() {
    return {
      assets: {
        dice20,
      },
    };
  },
  methods: {
    goBack() {
      this.$router.back();
    },
  },
};
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&display=swap");

.error-page {
  --ink: #f6efe2;
  --muted: #c7b8a1;
  --gold: #d8b778;
  --gold-bright: #f1d59c;
  --ember: #b35830;
  --panel: rgba(16, 11, 9, 0.88);
  --border: rgba(216, 183, 120, 0.35);
  min-height: 100vh;
  display: grid;
  align-items: center;
  justify-items: center;
  padding: 120px 6vw 90px;
  background:
    radial-gradient(900px 520px at 15% 10%, rgba(84, 53, 27, 0.6), transparent 70%),
    radial-gradient(700px 420px at 85% 15%, rgba(39, 66, 86, 0.5), transparent 65%),
    linear-gradient(180deg, rgba(11, 8, 7, 0.95) 0%, rgba(24, 17, 12, 0.94) 55%, rgba(11, 8, 7, 0.95) 100%);
  color: var(--ink);
  font-family: "EB Garamond", Garamond, "Times New Roman", serif;
  position: relative;
  overflow: hidden;
}

.error-page::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    repeating-linear-gradient(
      135deg,
      rgba(255, 255, 255, 0.02) 0px,
      rgba(255, 255, 255, 0.02) 2px,
      transparent 2px,
      transparent 6px
    );
  opacity: 0.35;
  pointer-events: none;
}

.error-page__card {
  position: relative;
  z-index: 2;
  max-width: 640px;
  text-align: center;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 32px 28px;
  box-shadow: 0 30px 60px rgba(0, 0, 0, 0.55);
}

.error-page__headline {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  margin: 8px 0 16px;
}

.error-page__code {
  font-size: clamp(3.6rem, 10vw, 7.2rem);
  letter-spacing: 0.12em;
  color: var(--gold-bright);
  text-shadow: 0 0 24px rgba(241, 200, 129, 0.35);
}

.error-page__title {
  text-transform: uppercase;
  letter-spacing: 0.28em;
  font-size: 0.82rem;
  color: var(--gold);
}

.eyebrow {
  text-transform: uppercase;
  letter-spacing: 0.22em;
  font-size: 0.72rem;
  color: var(--gold);
  margin: 0 0 10px;
}

.lead {
  margin: 0;
  font-size: 1.1rem;
  line-height: 1.6;
  color: var(--muted);
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  justify-content: center;
  margin: 22px 0 12px;
}

.cta-btn {
  background: linear-gradient(135deg, #f1d59c, #b3562d);
  color: #1d120b;
  border: none;
  padding: 12px 22px;
  border-radius: 999px;
  font-weight: 600;
  letter-spacing: 0.08em;
  cursor: pointer;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: 0 18px 30px rgba(0, 0, 0, 0.35);
}

.cta-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 24px 40px rgba(0, 0, 0, 0.45);
}

.ghost-btn {
  background: transparent;
  border: 1px solid rgba(216, 183, 120, 0.4);
  color: var(--ink);
  padding: 12px 22px;
  border-radius: 999px;
  font-size: 0.95rem;
  cursor: pointer;
  transition: border 0.2s ease, color 0.2s ease;
}

.ghost-btn:hover {
  color: var(--gold-bright);
  border-color: var(--gold-bright);
}

.ghost-link {
  color: var(--gold);
  text-decoration: none;
  border-bottom: 1px solid transparent;
  transition: color 0.2s ease, border 0.2s ease;
}

.ghost-link:hover {
  color: var(--gold-bright);
  border-color: var(--gold-bright);
}

.hint {
  margin: 0;
  color: var(--muted);
  font-size: 0.95rem;
}

.error-page__dice {
  position: absolute;
  right: 8vw;
  bottom: 60px;
  width: clamp(120px, 18vw, 180px);
  filter: drop-shadow(0 18px 28px rgba(0, 0, 0, 0.45));
  animation: float 5.5s ease-in-out infinite;
  z-index: 1;
}

@keyframes float {
  0%,
  100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-12px);
  }
}

@media (max-width: 900px) {
  .error-page {
    padding-top: 140px;
  }

  .error-page__dice {
    position: static;
    margin-top: 24px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .error-page__dice {
    animation: none;
  }
}
</style>
