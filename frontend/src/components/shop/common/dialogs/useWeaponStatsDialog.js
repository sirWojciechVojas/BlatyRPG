import { computed, ref, watch } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import i18n from "@/i18n";

export const useWeaponStatsDialog = () => {
  const ctx = useTradeModalContext();
  const t = (key, values = {}) => i18n.global.t(key, values);

  const weaponOptions = computed(() =>
    Array.isArray(ctx.weaponStatsItemOptions) ? ctx.weaponStatsItemOptions : [],
  );
  const featureOptions = computed(() =>
    Array.isArray(ctx.weaponFeatureOptions) ? ctx.weaponFeatureOptions : [],
  );

  const draftValue = (field) => String(ctx.weaponStatsDraft?.[field] ?? "");
  const selectedWeaponId = computed(() => draftValue("ITEM_ID"));
  const sourceLabel = computed(() =>
    ctx.weaponStatsSourceType === "instance"
      ? t("modals.weaponStats.sourceItem")
      : t("modals.weaponStats.sourceTemplate"),
  );
  const topBarTitle = computed(() =>
    t("modals.weaponStats.topBarTitle", {
      context: sourceLabel.value,
    }),
  );
  const selectedWeaponTitle = computed(() => {
    const selected = weaponOptions.value.find(
      (option) => String(option?.value) === selectedWeaponId.value,
    );
    return selected?.label || t("modals.weaponStats.emptySelection");
  });
  const selectedFeatureIds = computed(() =>
    String(ctx.weaponStatsDraft?.FEATURES_ID || "")
      .split(/[;,]/)
      .map((entry) => String(entry || "").trim())
      .filter(Boolean),
  );
  const selectedFeatureSet = computed(() => new Set(selectedFeatureIds.value));

  const selectedFeatureDetailsId = ref("");

  const selectWeapon = (value) => {
    ctx.selectWeaponStatsItem(value);
  };

  const updateField = (field, event) => {
    ctx.updateWeaponStatsDraft({
      [field]: String(event?.target?.value ?? ""),
    });
  };

  const isFeatureActive = (featureId) =>
    selectedFeatureSet.value.has(String(featureId));

  const applyFeatureSelection = (featureIds) => {
    const orderedUniqueIds = Array.from(new Set(featureIds.map(String)));
    const byId = featureOptions.value.reduce((acc, feature) => {
      acc[String(feature?.id)] = feature;
      return acc;
    }, {});
    const qualityNames = orderedUniqueIds
      .map((featureId) => byId[featureId]?.name || "")
      .filter(Boolean);
    ctx.updateWeaponStatsDraft({
      FEATURES_ID: orderedUniqueIds.join(";"),
      QUALITIES: qualityNames.join(", "),
    });
  };

  const toggleFeature = (featureId) => {
    const normalizedId = String(featureId || "").trim();
    if (!normalizedId) {
      return;
    }
    const nextIds = selectedFeatureIds.value.filter(Boolean);
    if (nextIds.includes(normalizedId)) {
      applyFeatureSelection(nextIds.filter((id) => id !== normalizedId));
      return;
    }
    applyFeatureSelection([...nextIds, normalizedId]);
  };

  const selectedFeatureDetails = computed(() => {
    const targetId =
      selectedFeatureDetailsId.value || selectedFeatureIds.value[0] || "";
    return (
      featureOptions.value.find(
        (feature) => String(feature?.id || "") === String(targetId),
      ) || null
    );
  });

  watch(
    selectedFeatureIds,
    (ids) => {
      if (!ids.length) {
        selectedFeatureDetailsId.value = "";
        return;
      }
      if (!ids.includes(selectedFeatureDetailsId.value)) {
        selectedFeatureDetailsId.value = ids[0];
      }
    },
    { immediate: true },
  );

  return {
    ctx,
    draftValue,
    featureOptions,
    isFeatureActive,
    selectWeapon,
    selectedFeatureDetails,
    selectedFeatureDetailsId,
    selectedWeaponId,
    selectedWeaponTitle,
    toggleFeature,
    topBarTitle,
    updateField,
    weaponOptions,
  };
};
