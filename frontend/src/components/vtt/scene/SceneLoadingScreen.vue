<template>
  <section
    class="scene-loading-screen"
    :class="{ 'scene-loading-screen--page': page }"
    role="status"
    aria-live="polite"
    :aria-label="$t('vtt.scene.loader.ariaLabel')"
  >
    <article class="scene-loading-card">
      <div
        class="scene-loading-card__art"
        :class="{ 'scene-loading-card__art--placeholder': imageFailed }"
      >
        <img
          v-if="campaignImage && !imageFailed"
          :src="campaignImage"
          alt=""
          @error="imageFailed = true"
        />
        <span class="scene-loading-card__art-mark" aria-hidden="true">✦</span>
      </div>

      <div class="scene-loading-card__body">
        <p class="scene-loading-card__eyebrow">
          {{ campaignName || $t("vtt.scene.loader.campaignFallback") }}
        </p>
        <h1>{{ heading }}</h1>
        <p class="scene-loading-card__scene-name">{{ sceneName }}</p>

        <div class="scene-loading-card__status">
          <span class="scene-loading-card__pulse" aria-hidden="true" />
          <span>{{ currentLabel }}</span>
          <small v-if="detail">{{ detail }}</small>
        </div>

        <ol class="scene-loading-card__steps">
          <li
            v-for="step in visibleSteps"
            :key="step.id"
            :class="`scene-loading-card__step--${step.status}`"
          >
            <span class="scene-loading-card__step-mark" aria-hidden="true">
              <svg
                v-if="step.status === 'ready'"
                viewBox="0 0 16 16"
                focusable="false"
              >
                <path d="m3 8.2 3.1 3L13 4.7" />
              </svg>
              <span v-else-if="step.status === 'loading'" />
              <span v-else-if="step.status === 'error'">!</span>
              <span v-else>·</span>
            </span>
            <span>{{ stepLabel(step.id) }}</span>
          </li>
        </ol>

        <div
          class="scene-loading-card__progress"
          role="progressbar"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-valuenow="percentage"
          :aria-label="$t('vtt.scene.loader.progressLabel')"
        >
          <div class="scene-loading-card__progress-meta">
            <span>{{ $t("vtt.scene.loader.progressLabel") }}</span>
            <strong>{{ percentage }}%</strong>
          </div>
          <div class="scene-loading-card__track">
            <span :style="{ width: `${percentage}%` }" />
          </div>
        </div>
      </div>
    </article>
  </section>
</template>

<script>
const FALLBACK_STEPS = Object.freeze([
  { id: "catalog", status: "loading" },
  { id: "scene", status: "pending" },
  { id: "tokens", status: "pending" },
  { id: "tiles", status: "pending" },
  { id: "walls", status: "pending" },
  { id: "lights", status: "pending" },
  { id: "fog", status: "pending" },
  { id: "regions", status: "pending" },
  { id: "combat", status: "pending" },
  { id: "movement", status: "pending" },
  { id: "assets", status: "pending" },
  { id: "render", status: "pending" },
]);

export default {
  name: "SceneLoadingScreen",
  props: {
    loading: { type: Object, default: () => ({}) },
    sceneName: { type: String, default: "" },
    campaignName: { type: String, default: "" },
    campaignImage: { type: String, default: "" },
    page: { type: Boolean, default: false },
  },
  data: () => ({ imageFailed: false }),
  computed: {
    steps() {
      return this.loading?.steps?.length ? this.loading.steps : FALLBACK_STEPS;
    },
    heading() {
      return this.$t("vtt.scene.loader.title");
    },
    detail() {
      return String(this.loading?.detail || "").trim();
    },
    currentStep() {
      return (
        this.steps.find((step) => step.status === "loading") ||
        this.steps.find((step) => step.status === "pending") ||
        this.steps.at(-1)
      );
    },
    currentLabel() {
      return this.currentStep
        ? this.stepLabel(this.currentStep.id)
        : this.$t("vtt.scene.loader.preparing");
    },
    percentage() {
      if (!this.steps.length) return 0;
      const finished = this.steps.filter((step) =>
        ["ready", "error"].includes(step.status),
      ).length;
      const active = this.steps.some((step) => step.status === "loading")
        ? 0.35
        : 0;
      return Math.round(((finished + active) / this.steps.length) * 100);
    },
    visibleSteps() {
      const currentIndex = Math.max(
        0,
        this.steps.findIndex((step) => step.id === this.currentStep?.id),
      );
      const start = Math.max(0, currentIndex - 1);
      return this.steps.slice(start, Math.min(this.steps.length, start + 3));
    },
  },
  watch: {
    campaignImage() {
      this.imageFailed = false;
    },
  },
  methods: {
    stepLabel(id) {
      return this.$t(`vtt.scene.loader.steps.${id}`);
    },
  },
};
</script>

<style scoped src="./styles/scene-loading-screen.css"></style>
