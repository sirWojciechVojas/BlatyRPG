<!-- Moduł GM: Sklep - ustal asortyment. Ten plik jest wejściem modułu odpowiedzialnego za zarządzanie źródłem, celem i trybem edycji asortymentu sklepu. -->
<template>
  <component :is="currentView" />
</template>

<script setup>
import { computed } from "vue";
import AssortmentEditorManager from "@/components/shop/modules/assortment/components/AssortmentEditorManager.vue";
import AssortmentSourceManager from "@/components/shop/modules/assortment/components/AssortmentSourceManager.vue";
import AssortmentTargetManager from "@/components/shop/modules/assortment/components/AssortmentTargetManager.vue";
import AssortmentWorkspaceManager from "@/components/shop/modules/assortment/components/AssortmentWorkspaceManager.vue";

const props = defineProps({
  side: {
    type: String,
    default: "left",
  },
});

const currentView = computed(() => {
  if (props.side === "workspace") {
    return AssortmentWorkspaceManager;
  }
  if (props.side === "right") {
    return AssortmentTargetManager;
  }
  if (props.side === "editor") {
    return AssortmentEditorManager;
  }
  return AssortmentSourceManager;
});
</script>

<style src="@/styles/trade/assortment-manager.css"></style>
<style src="@/styles/trade/assortment-workspace.css"></style>
