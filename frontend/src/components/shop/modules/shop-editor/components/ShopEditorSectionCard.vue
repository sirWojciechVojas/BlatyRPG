<!-- Komponent modułu Sklep - dodaj/edytuj. Ten plik dostarcza wspólną kartę sekcji używaną przez kolejne bloki formularza edytora sklepu. -->
<template>
  <section class="trade-form-section shop-editor-panel" :class="sectionClass">
    <div class="trade-form-section-title">
      {{ title }}
    </div>
    <slot />
  </section>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  sectionClass: {
    type: [String, Array, Object],
    default: "m-0 px-2 pt-1",
  },
});
</script>
