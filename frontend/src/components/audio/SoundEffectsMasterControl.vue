<template>
  <div class="sound-effects__master">
    <span aria-hidden="true">◖</span>
    <input
      :value="volume"
      type="range"
      min="0"
      max="1"
      step="0.01"
      :aria-label="$t('audio.soundEffects.masterVolume')"
      @input="$emit('volume', Number($event.target.value))"
    />
    <output>{{ Math.round(Number(volume) * 100) }}%</output>
    <button type="button" :disabled="!canControl" @click="$emit('stop')">
      ■ {{ $t("audio.soundEffects.stop") }}
    </button>
  </div>
</template>

<script>
export default {
  name: "SoundEffectsMasterControl",
  props: { volume: { type: Number, default: 1 }, canControl: Boolean },
  emits: ["volume", "stop"],
};
</script>
