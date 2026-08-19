import { computed, ref } from "vue";
import { createShopApiConfig, shopApiClient } from "@/lib/trade/shopApiClient";

const portableProfileFields = [
  "typeId",
  "worldProfileId",
  "locationType",
  "legalStatus",
  "wealthTier",
  "reputation",
  "seasonality",
  "counterfeitRisk",
  "marketSettings",
  "marketEvents",
];

export const installShopProfileOperations = (deps) => {
  const profileHistory = ref([]);
  const profileHistoryLoading = ref(false);
  const selectedProfileRevision = ref(null);
  const profilePresetName = ref("");
  const operationMessage = ref("");
  const apiConfig = () =>
    createShopApiConfig({
      campaignId: Number(
        deps.shopState.value.campaignId ||
          deps.shopState.value.context?.campaignId ||
          1,
      ),
      ownerCode: deps.profileDraft.ownerCode || "BG1",
    });
  const customProfilePresets = computed(
    () => deps.profileDraft.customPresets?.profiles || [],
  );
  const marketImpactSummary = computed(() => {
    const items = deps.pricingPricePreview?.value?.items || [];
    return items.slice(0, 5).map((item) => ({
      key: item.templateId,
      label: item.itemClass || item.templateName,
      value: Number(item.difference?.percent || 0),
      limited: item.after?.breakdown?.some(
        (row) => row.key === "wealthBuybackLimit" && row.amountChange < 0,
      ),
    }));
  });
  function saveCustomProfilePreset() {
    const name = String(profilePresetName.value || "").trim();
    if (!name) return;
    const values = Object.fromEntries(
      portableProfileFields.map((field) => [
        field,
        JSON.parse(JSON.stringify(deps.profileDraft[field] ?? null)),
      ]),
    );
    const presets = customProfilePresets.value.filter(
      (preset) =>
        preset.name.toLocaleLowerCase("pl") !== name.toLocaleLowerCase("pl"),
    );
    presets.push({ id: `profile-${Date.now()}`, name, values });
    deps.profileDraft.customPresets = {
      ...(deps.profileDraft.customPresets || {}),
      profiles: presets,
      policies: deps.profileDraft.customPresets?.policies || [],
    };
    profilePresetName.value = "";
    deps.markShopDirty();
  }
  function applyCustomProfilePreset(preset) {
    if (!preset?.values) return;
    Object.assign(deps.profileDraft, JSON.parse(JSON.stringify(preset.values)));
    deps.markShopDirty();
  }
  function removeCustomProfilePreset(id) {
    deps.profileDraft.customPresets.profiles =
      customProfilePresets.value.filter((preset) => preset.id !== id);
    deps.markShopDirty();
  }
  function resetProfileSection(section) {
    const archetype = deps.shopProfileArchetypes.find(
      (entry) => entry.id === deps.selectedProfileArchetype.value,
    );
    if (section === "setting") {
      const suggested = archetype?.values || {};
      Object.assign(deps.profileDraft, {
        worldProfileId: suggested.worldProfileId || "standard",
        locationType: suggested.locationType || "miasto",
        legalStatus: suggested.legalStatus || "legal",
        wealthTier: suggested.wealthTier || "standard",
        reputation: suggested.reputation || "neutralna",
        seasonality: suggested.seasonality || "caloroczny",
      });
    } else if (section === "economy") {
      deps.profileDraft.counterfeitRisk = deps.recommendedCounterfeitRisk.value;
      Object.assign(deps.profileDraft.marketSettings, {
        demandLevel: "normal",
        availabilityBias: 0,
        buybackBudget: null,
        maxBuybackItemValue: null,
        expensiveStockLimit: null,
        localCategories: [],
        importedCategories: [],
      });
    } else if (section === "identity") {
      deps.profileDraft.typeId = archetype?.values?.typeId || "";
      deps.profileDraft.signboardAltNamesText = "";
      deps.profileDraft.ownerName = "";
    }
    deps.markShopDirty();
  }

  function addMarketEvent() {
    deps.profileDraft.marketEvents.push({
      id: `market-event-${Date.now()}`,
      name: "",
      type: "custom",
      enabled: true,
      startsAt: null,
      endsAt: null,
      multiplier: 1,
      availabilityDelta: 0,
      modes: ["buy", "sell"],
      itemClasses: [],
      itemGenres: [],
      locationTypes: [],
      templateIds: [],
    });
    deps.markShopDirty();
  }

  function marketCategoryList(key) {
    return (deps.profileDraft.marketSettings?.[key] || []).join(", ");
  }

  function updateMarketCategoryList(key, value) {
    deps.profileDraft.marketSettings[key] = [
      ...new Set(
        String(value || "")
          .split(",")
          .map((entry) => entry.trim().toLowerCase())
          .filter(Boolean),
      ),
    ];
    deps.markShopDirty();
  }

  function removeMarketEvent(id) {
    deps.profileDraft.marketEvents = deps.profileDraft.marketEvents.filter(
      (event) => event.id !== id,
    );
    deps.markShopDirty();
  }

  function marketEventClasses(event) {
    return (event?.itemClasses || []).join(", ");
  }

  function marketEventList(event, key) {
    return (event?.[key] || []).join(", ");
  }

  function updateMarketEventClasses(event, value) {
    event.itemClasses = String(value || "")
      .split(",")
      .map((entry) => entry.trim().toUpperCase())
      .filter(Boolean);
    deps.markShopDirty();
  }

  function updateMarketEventList(event, key, value) {
    const entries = String(value || "")
      .split(",")
      .map((entry) => entry.trim())
      .filter(Boolean);
    event[key] =
      key === "templateIds"
        ? entries
            .map(Number)
            .filter((entry) => Number.isFinite(entry) && entry > 0)
        : key === "itemGenres"
          ? entries.map((entry) => entry.toUpperCase())
          : entries.map((entry) => entry.toLowerCase());
    deps.markShopDirty();
  }

  function toggleMarketEventMode(event, mode, enabled) {
    const modes = new Set(event.modes || []);
    if (enabled) modes.add(mode);
    else if (modes.size > 1) modes.delete(mode);
    event.modes = [...modes];
    deps.markShopDirty();
  }

  async function duplicateProfile() {
    const shop = await deps.store.dispatch("shop/duplicateShop", {
      shopId: deps.activeShopId.value,
      name: `${deps.t("shop.workspace.profile.tools.copyPrefix")} ${deps.profileDraft.signboardName}`,
      copyMode: "profile",
      ownerCode: deps.profileDraft.ownerCode,
    });
    if (shop?.id) deps.store.commit("shop/setActiveShop", Number(shop.id));
    return shop;
  }

  async function loadProfileHistory() {
    profileHistoryLoading.value = true;
    try {
      const result = await shopApiClient.getShopProfileHistory(
        apiConfig(),
        deps.activeShopId.value,
        30,
      );
      profileHistory.value = result?.items || [];
    } finally {
      profileHistoryLoading.value = false;
    }
  }

  function inspectProfileRevision(revision) {
    selectedProfileRevision.value = revision || null;
  }

  function applyProfileRevision() {
    const snapshot = selectedProfileRevision.value?.snapshot;
    if (!snapshot) return;
    Object.assign(deps.profileDraft, JSON.parse(JSON.stringify(snapshot)), {
      signboardAltNamesText: (snapshot.signboardAltNames || []).join(", "),
    });
    deps.markShopDirty();
  }

  async function exportProfileJson() {
    const document = await shopApiClient.exportShopProfile(
      apiConfig(),
      deps.activeShopId.value,
    );
    const blob = new Blob([JSON.stringify(document, null, 2)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);
    const link = window.document.createElement("a");
    link.href = url;
    link.download = `shop-profile-${deps.activeShopId.value}.json`;
    link.click();
    URL.revokeObjectURL(url);
  }

  async function importProfileJson(file) {
    if (!file) return;
    operationMessage.value = "";
    try {
      const document = JSON.parse(await file.text());
      const result = await shopApiClient.importShopProfile(
        apiConfig(),
        deps.activeShopId.value,
        document,
      );
      if (result?.profile) {
        deps.store.commit("shop/createOrUpdateShopProfile", result.profile);
        deps.hydrateProfile();
        operationMessage.value = deps.t(
          "shop.workspace.profile.tools.imported",
        );
      }
    } catch (error) {
      operationMessage.value = deps.t(
        "shop.workspace.profile.tools.importFailed",
      );
    }
  }

  Object.assign(deps, {
    profileHistory,
    profileHistoryLoading,
    selectedProfileRevision,
    profilePresetName,
    operationMessage,
    customProfilePresets,
    marketImpactSummary,
    saveCustomProfilePreset,
    applyCustomProfilePreset,
    removeCustomProfilePreset,
    resetProfileSection,
    marketCategoryList,
    updateMarketCategoryList,
    addMarketEvent,
    removeMarketEvent,
    marketEventClasses,
    marketEventList,
    updateMarketEventClasses,
    updateMarketEventList,
    toggleMarketEventMode,
    duplicateProfile,
    loadProfileHistory,
    inspectProfileRevision,
    applyProfileRevision,
    exportProfileJson,
    importProfileJson,
  });
};
