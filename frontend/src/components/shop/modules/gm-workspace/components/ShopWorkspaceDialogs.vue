<!-- Panel GM sklepu: ShopWorkspaceDialogs. -->
<template>
  <ConfirmDialog
    :open="Boolean(duplicateMode)"
    :title="$t('shop.workspace.duplicate')"
    :message="
      duplicateMode === 'profile_with_offer'
        ? $t('shop.workspace.duplicateOfferQuestion')
        : $t('shop.workspace.duplicateProfileQuestion')
    "
    :confirm-label="$t('shop.workspace.duplicate')"
    :cancel-label="$t('actions.cancel')"
    @confirm="duplicateActiveShop"
    @cancel="duplicateMode = ''"
  />
  <ConfirmDialog
    :open="confirmDeleteShop"
    :title="$t('shop.workspace.deleteShop')"
    :message="$t('shop.workspace.deleteShopQuestion')"
    :confirm-label="$t('actions.delete')"
    :cancel-label="$t('actions.cancel')"
    @confirm="deleteActiveShop"
    @cancel="confirmDeleteShop = false"
  />
  <ConfirmDialog
    :open="Boolean(detailItem)"
    :title="
      detailItem?.PERSONAL_PSEU ||
      detailItem?.NAME ||
      $t('shop.workspace.details')
    "
    :message="
      detailItem?.PERSONAL_DESC ||
      detailItem?.DESCRIPTION ||
      detailItem?.DETAILS ||
      $t('shop.workspace.noDescription')
    "
    :confirm-label="$t('actions.close')"
    :cancel-label="$t('actions.close')"
    @confirm="detailItem = null"
    @cancel="detailItem = null"
  />
  <IconPickerDialog
    :open="iconPickerOpen"
    :model-value="selectedIconCode"
    :campaign-id="shopState.campaignId"
    :can-edit="Boolean(shopState.permissions?.isGm)"
    :item-dictionaries="shopState.itemDictionaries"
    @update:model-value="setSelectedIcon"
    @close="iconPickerOpen = false"
  />
</template>
<script>
import ConfirmDialog from "@/components/shop/common/ConfirmDialog.vue";
import IconPickerDialog from "@/components/shop/common/IconPickerDialog.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceDialogs",
  components: { ConfirmDialog, IconPickerDialog },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
