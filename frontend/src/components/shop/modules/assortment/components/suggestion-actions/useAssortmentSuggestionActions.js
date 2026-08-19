import { computed } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import i18n from "@/i18n";

export const useAssortmentSuggestionActions = (mode) => {
  const ctx = useTradeModalContext();
  const t = (key, values = {}) => i18n.global.t(key, values);
  const editorMode = computed(() => mode.value === "editor");
  const primaryRefreshLabel = computed(() =>
    editorMode.value
      ? t("shop.assortment.refreshSuggestions")
      : t("shop.assortment.generateSuggestions"),
  );
  const selectedLabel = computed(() =>
    editorMode.value
      ? t("shop.assortment.addSelectedInstances")
      : t("shop.assortment.addSelected"),
  );
  const allLabel = computed(() =>
    editorMode.value
      ? t("shop.assortment.addAllInstances")
      : t("shop.assortment.addAll"),
  );
  const rollLabel = computed(() =>
    editorMode.value
      ? t("shop.assortment.rollStarterBatch")
      : t("shop.assortment.rollStarter"),
  );
  const rollTargetId = computed(() =>
    editorMode.value ? "shop-editor-roll-target" : "assort-roll-target",
  );
  const loadMoreLabel = computed(() =>
    ctx.shopRemainingRecommendations.length
      ? t("shop.assortment.loadMoreSuggestions", { count: 30 })
      : t("shop.assortment.noMoreSuggestions"),
  );
  return {
    ctx,
    primaryRefreshLabel,
    selectedLabel,
    allLabel,
    rollLabel,
    rollTargetId,
    loadMoreLabel,
  };
};
