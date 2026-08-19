<!-- Selects the appropriate assortment suggestion action layout. -->
<template>
  <AssortmentSuggestionFooterActions v-if="mode === 'right'" :mode="mode" />
  <AssortmentSuggestionCompactActions v-else :mode="mode" />
</template>

<script setup>
import AssortmentSuggestionFooterActions from "./suggestion-actions/AssortmentSuggestionFooterActions.vue";
import AssortmentSuggestionCompactActions from "./suggestion-actions/AssortmentSuggestionCompactActions.vue";

defineProps({
  mode: { type: String, default: "editor" },
});
</script>
