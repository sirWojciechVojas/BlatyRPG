<!-- Groups modal dialogs that consume the provided trade context. -->
<template>
  <FieldEditDialog />
  <IconClassDialog />
  <ItemDetailDialog />
  <OwnerOptionDialog />
  <SuggestionDetailDialog />
  <AssortmentMergeDialog />
  <ShopActivationDialog />
  <ViewSettingsDialog />
  <WeaponStatsDialog />
</template>

<script setup>
import FieldEditDialog from "@/components/shop/common/dialogs/FieldEditDialog.vue";
import ItemDetailDialog from "@/components/shop/common/dialogs/ItemDetailDialog.vue";
import SuggestionDetailDialog from "@/components/shop/common/dialogs/SuggestionDetailDialog.vue";
import AssortmentMergeDialog from "@/components/shop/common/dialogs/AssortmentMergeDialog.vue";
import IconClassDialog from "@/components/shop/common/dialogs/IconClassDialog.vue";
import ShopActivationDialog from "@/components/shop/common/dialogs/ShopActivationDialog.vue";
import OwnerOptionDialog from "@/components/shop/common/dialogs/OwnerOptionDialog.vue";
import ViewSettingsDialog from "@/components/shop/common/dialogs/ViewSettingsDialog.vue";
import WeaponStatsDialog from "@/components/shop/common/dialogs/WeaponStatsDialog.vue";
</script>
