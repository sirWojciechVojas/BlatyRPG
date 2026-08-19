<template>
  <TradeModalShell
    :style-vars="styleVars"
    :is-g-m="isGM"
    :is-view-settings-open="showViewSettingsDialog"
    :api-status-label="apiStatusLabel"
    :wallet-alert="walletAlert"
    :wallet-alert-key="walletAlertKey"
    @toggle-view-settings="toggleViewSettingsDialog"
  >
    <TradeModalContent
      v-bind="tradeModalContentProps"
      :show-view-settings-dialog="showViewSettingsDialog"
      v-model:assortmentLeftContainerId="assortmentLeftContainerId"
      v-model:assortmentSearch="assortmentSearch"
      v-model:assortmentLeftTab="assortmentLeftTab"
      v-model:gmMoveItemKey="gmMoveItemKey"
      v-model:gmMoveTargetContainerId="gmMoveTargetContainerId"
      v-model:gmMoveQuantity="gmMoveQuantity"
      v-model:assortmentRightContainerId="assortmentRightContainerId"
      v-model:assortmentRightTab="assortmentRightTab"
      v-model:shopBuyContainerId="shopBuyContainerId"
      v-model:shopBuyItemKey="shopBuyItemKey"
      v-model:shopBuyTargetContainerId="shopBuyTargetContainerId"
      v-model:shopBuyQuantity="shopBuyQuantity"
      v-model:trashZoneOwnerCode="trashZoneOwnerCode"
      v-model:inventoryOwnerCodeFilter="inventoryOwnerCodeFilter"
      v-model:iconSize="iconSize"
      v-model:itemDetailNickname="itemDetailNickname"
      v-model:suggestionDetailVariantId="suggestionDetailVariantId"
      v-model:inventoryForm="inventoryForm"
      v-model:templateForm="templateForm"
      v-model:newTemplateForm="newTemplateForm"
      @left-flank-action="handleLeftFlankAction"
      @right-flank-action="handleRightFlankAction"
      @undo-container-action="undoContainerAction"
      @toggle-container-selection="handleToggleContainerSelection"
      @move-container-selection="moveContainerSelection"
      @gm-move="handleGmMove"
      @shop-buy="handleShopBuy"
      @buy-item-click="handleBuyItemClick"
      @buy-item-quantity-step="stepBuyItemSelectionQuantity"
      @buy-item-quantity-set="updateBuyItemSelectionQuantity"
      @sell-item-click="handleSellItemClick"
      @sell-item-quantity-step="stepSellItemSelectionQuantity"
      @sell-item-quantity-set="updateSellItemSelectionQuantity"
      @buy-action="handleBuyAction"
      @sell-action="handleSellAction"
      @delete-template="handleDeleteTemplateToTrash"
      @open-class-edit="handleOpenClassEdit"
      @apply-class-edit="applyClassEdit"
      @confirm-class-edit="confirmClassEdit"
      @close-field-edit-dialog="closeFieldEditDialog"
      @update-class-edit-draft-value="updateClassEditDraftValue"
      @update-class-edit-search="setClassEditSearch"
      @apply-class-edit-suggestion="applyClassEditSuggestion"
      @start-template-create="startTemplateCreate"
      @reset-new-template-form="resetNewTemplateForm"
      @close-class-edit-dialog="closeClassEditDialog"
      @close-weapon-stats-dialog="closeWeaponStatsDialog"
      @create-weapon-stats="createWeaponStatsDraft"
      @remove-weapon-stats="removeWeaponStats"
      @select-weapon-stats-item="selectWeaponStatsItem"
      @update-weapon-stats-draft="updateWeaponStatsDraft"
      @confirm-weapon-stats="confirmWeaponStats"
      @select-img-class="selectImgClass"
      @confirm-img-class="confirmImgClass"
      @close-owner-opt-dialog="closeOwnerOptDialog"
      @select-owner-opt="selectOwnerOpt"
      @confirm-owner-opt="confirmOwnerOpt"
      @open-item-detail-dialog="handleOpenItemDetailDialog"
      @close-item-detail-dialog="closeItemDetailDialog"
      @apply-item-detail-nickname="applyItemDetailNickname"
      @toggle-item-detail-nickname-mode="toggleItemDetailNicknameMode"
      @open-suggestion-detail-dialog="openSuggestionDetailDialog"
      @close-suggestion-detail-dialog="closeSuggestionDetailDialog"
      @confirm-suggestion-detail-action="confirmSuggestionDetailAction"
      @close-view-settings-dialog="closeViewSettingsDialog"
    />
    <PaymentConversionDialog
      :open="showPaymentConversionDialog"
      :quote="paymentQuote"
      :busy="paymentQuotePending || buyTransactionPending"
      :currency-definitions="$store.state.shop.currencyDefinitions"
      @close="closePaymentConversionDialog"
      @toggle-currency="togglePaymentCurrency"
      @confirm="confirmPaymentConversion"
    />
  </TradeModalShell>
</template>
<script>
import { reactive, toRefs, watch } from "vue";
import { useStore } from "vuex";
import TradeModalShell from "@/components/trade/TradeModalShell.vue";
import TradeModalContent from "@/components/trade/TradeModalContent.vue";
import PaymentConversionDialog from "@/components/trade/PaymentConversionDialog.vue";
import { createContainerState } from "@/lib/containerModel";
import { useShopTradeModalCore } from "@/composables/trade/useShopTradeModalCore";
import { useShopTradeModalEncumbrance } from "@/composables/trade/useShopTradeModalEncumbrance";
import { useShopTradeModalContainers } from "@/composables/trade/useShopTradeModalContainers";
import { useShopTradeModalActions } from "@/composables/trade/useShopTradeModalActions";
import { useShopTradeModalDetails } from "@/composables/trade/useShopTradeModalDetails";

export default {
  name: "ShopTradeModal",
  components: {
    TradeModalShell,
    TradeModalContent,
    PaymentConversionDialog,
  },
  setup() {
    const store = useStore();
    const state = reactive({
      tempFieldNames: [
        "ID",
        "NAME",
        "DESCRIPTION",
        "DETAILS",
        "ITEM_CLASS",
        "ITEM_ID",
        "ITEM_GENRE",
        "IMG_CLASS",
        "PRIZE",
        "CHARGE",
      ],
      indFieldNames: [
        "ID",
        "INV_ID",
        "IMG_CLASS",
        "ITEM_PLACE",
        "PERSONAL_PSEU",
        "PERSONAL_DESC",
        "PERSONAL_COST",
        "QUANTITY",
        "OWNER_OPT",
      ],
      templateForm: {},
      newTemplateForm: {},
      inventoryForm: {},
      templateFormErrors: {},
      newTemplateFormErrors: {},
      inventoryFormErrors: {},
      classEditType: null,
      classEditTarget: null,
      classEditDraftValue: "",
      classEditSearch: "",
      classEditValidationError: "",
      selectedImgClass: "",
      selectedOwnerOpt: "",
      containerState: createContainerState(),
      containerInstanceMeta: {},
      containerUndoStack: [],
      assortmentSearch: "",
      assortmentLeftContainerId: null,
      assortmentRightContainerId: null,
      assortmentLeftSelectedKeys: [],
      assortmentRightSelectedKeys: [],
      assortmentLeftTab: "transfer",
      assortmentRightTab: "transfer",
      gmMoveItemKey: "",
      gmMoveTargetContainerId: null,
      gmMoveQuantity: 1,
      shopBuyContainerId: null,
      shopBuyItemKey: "",
      shopBuyTargetContainerId: null,
      shopBuyQuantity: 1,
      assortmentRollPreview: [],
      assortmentRollPreviewMeta: null,
      trashZoneOwnerCode: "TRASH",
      inventoryOwnerCodeFilter: "all",
      showItemDetailDialog: false,
      showSuggestionDetailDialog: false,
      showViewSettingsDialog: false,
      itemDetailItem: null,
      itemDetailSource: "",
      itemDetailNickname: "",
      itemDetailNicknameMode: "append",
      suggestionDetailEntry: null,
      suggestionDetailVariantId: "",
      iconSize: 42,
      walletAlert: "",
      walletAlertTimeout: null,
      walletAlertKey: 0,
      buyTransactionPending: false,
      sellTransactionPending: false,
      showShopActivationDialog: false,
      showAssortmentMergeDialog: false,
      assortmentMergeContainerId: null,
      assortmentMergeLeftInstanceId: null,
      assortmentMergeRightInstanceId: null,
      assortmentMergeLeftItem: null,
      assortmentMergeRightItem: null,
      assortmentMergeChoices: {},
      showPaymentConversionDialog: false,
      paymentQuotePending: false,
      paymentQuote: {},
      pendingPaymentPurchase: null,
    });

    const ctx = { store, state };
    const deps = {};

    Object.assign(deps, useShopTradeModalCore(ctx, deps));
    Object.assign(deps, useShopTradeModalEncumbrance(ctx, deps));
    Object.assign(deps, useShopTradeModalContainers(ctx, deps));
    Object.assign(deps, useShopTradeModalActions(ctx, deps));
    Object.assign(deps, useShopTradeModalDetails(ctx, deps));

    const toggleViewSettingsDialog = () => {
      state.showViewSettingsDialog = !state.showViewSettingsDialog;
    };

    const closeViewSettingsDialog = () => {
      state.showViewSettingsDialog = false;
    };

    watch(
      () => [
        store.state.shop.context?.ownerCode,
        store.state.shop.activeShopId,
      ],
      (current, previous) => {
        if (
          previous &&
          current.some((value, index) => value !== previous[index])
        ) {
          state.showPaymentConversionDialog = false;
          state.paymentQuote = {};
          state.pendingPaymentPurchase = null;
        }
      },
    );

    return {
      ...toRefs(state),
      ...deps,
      toggleViewSettingsDialog,
      closeViewSettingsDialog,
    };
  },
};
</script>
