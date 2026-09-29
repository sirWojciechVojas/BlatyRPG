<template>
  <section
    v-show="visible"
    class="scene-dice-overlay"
    :class="`scene-dice-overlay--${mode}`"
    role="dialog"
    aria-modal="false"
    :aria-hidden="String(!visible)"
    :aria-label="$t('vtt.table.diceOverlay.label')"
    @pointerdown.stop
    @pointerup.stop
    @click.stop
    @dblclick.stop
    @wheel.stop.prevent
  >
    <DiceRoller
      ref="roller"
      class="scene-dice-overlay__roller"
      embedded
      :auto-start="true"
      :chat-enabled="false"
      :show-advanced-controls="false"
      :dice-display-enabled="true"
      :drag-throw-enabled="true"
      :dice-scale-throw="2"
      :dice-scale-selector="1.2"
      :dice-display-list="diceDisplayList"
      @ready="handleReady"
      @error="handleError"
      @roll-complete="handleRollComplete"
    />

    <button
      ref="closeButton"
      type="button"
      class="scene-dice-overlay__close"
      :aria-label="$t('vtt.table.diceOverlay.close')"
      :title="$t('vtt.table.diceOverlay.close')"
      @click="requestClose"
    >
      ×
    </button>

    <p v-if="error" class="scene-dice-overlay__error" role="alert">
      {{ $t("vtt.table.diceOverlay.loadError") }}
    </p>
  </section>
</template>

<script src="./SceneDiceOverlay.options.js"></script>

<style scoped src="./styles/scene-dice-overlay.css"></style>
