import { computed, ref } from "vue";
import { buildShopProfileWarnings } from "../options/profileWarningRules";
import { drawShopSignboard } from "@/lib/shopSignboardService";

const REQUIRED_PROFILE_FIELDS = [
  "signboardName",
  "typeId",
  "ownerCode",
  "worldProfileId",
  "locationType",
  "legalStatus",
  "wealthTier",
  "reputation",
  "seasonality",
];

const clampRisk = (value) => Math.max(0, Math.min(100, Math.round(value)));

export const installShopProfileWorkspace = (deps) => {
  const profileSaveAttempted = ref(false);
  const selectedProfileArchetype = computed(
    () =>
      deps.shopProfileArchetypes.find((archetype) =>
        Object.entries(archetype.values).every(
          ([field, value]) => deps.profileDraft[field] === value,
        ),
      )?.id || "",
  );

  const missingProfileFields = computed(() =>
    REQUIRED_PROFILE_FIELDS.filter(
      (field) => !String(deps.profileDraft[field] ?? "").trim(),
    ),
  );
  const profileCompletion = computed(() =>
    Math.round(
      ((REQUIRED_PROFILE_FIELDS.length - missingProfileFields.value.length) /
        REQUIRED_PROFILE_FIELDS.length) *
        100,
    ),
  );
  const canSaveProfile = computed(
    () =>
      missingProfileFields.value.length === 0 &&
      deps.formStatus.value.shop !== "saving",
  );
  const recommendedCounterfeitRisk = computed(() => {
    const legalBase = {
      legal: 10,
      licensed: 5,
      mixed: 28,
      grey: 48,
      illegal: 72,
    }[deps.profileDraft.legalStatus];
    const reputationDelta = {
      fatalna: 18,
      zla: 12,
      podejrzana: 8,
      neutralna: 0,
      dobra: -4,
      znakomita: -7,
    }[deps.profileDraft.reputation];
    const locationDelta = {
      port: 8,
      przedmiescie: 7,
      trakt: 6,
      jarmark: 5,
      rynek: 2,
      zamek: -4,
      klasztor: -5,
    }[deps.profileDraft.locationType];
    return clampRisk(
      Number(legalBase ?? 10) +
        Number(reputationDelta ?? 0) +
        Number(locationDelta ?? 0),
    );
  });
  const profileRiskTone = computed(() => {
    const risk = Number(deps.profileDraft.counterfeitRisk || 0);
    if (risk < 20) return "low";
    if (risk < 45) return "guarded";
    if (risk < 70) return "high";
    return "severe";
  });
  const profileLocationChoices = computed(() => {
    const choices = [...deps.profileLocationOptions];
    const current = String(deps.profileDraft.locationType || "");
    if (current && !choices.includes(current)) choices.push(current);
    return choices.map((id) => ({
      id,
      label: deps.te(`shop.workspace.profile.locations.${id}.label`)
        ? deps.t(`shop.workspace.profile.locations.${id}.label`)
        : id,
      description: deps.te(`shop.workspace.profile.locations.${id}.description`)
        ? deps.t(`shop.workspace.profile.locations.${id}.description`)
        : "",
    }));
  });
  const profileTypeGroups = computed(() => {
    const groups = new Map();
    deps.typeOptions.value.forEach((type) => {
      const category =
        String(type.category || "").trim() ||
        deps.t("shop.workspace.profile.typeAdvisor.otherCategory");
      if (!groups.has(category)) groups.set(category, []);
      groups.get(category).push(type);
    });
    return Array.from(groups, ([label, options]) => ({ label, options }));
  });
  const profileSuggestedTypes = computed(() => {
    const available = new Map(
      deps.typeOptions.value.map((type) => [String(type.id), type]),
    );
    const ranked = deps.lateMedievalTypeSuggestions
      .map((meta, index) => {
        const type = available.get(meta.id);
        if (!type) return null;
        let score = (deps.lateMedievalTypeSuggestions.length - index) / 100;
        if (meta.locations.includes(deps.profileDraft.locationType)) score += 6;
        if (meta.legalStatuses.includes(deps.profileDraft.legalStatus))
          score += 4;
        if (meta.wealthTiers.includes(deps.profileDraft.wealthTier)) score += 3;
        return { ...type, suggestionIcon: meta.icon, suggestionScore: score };
      })
      .filter(Boolean)
      .sort((left, right) => right.suggestionScore - left.suggestionScore);
    return ranked.length
      ? ranked.slice(0, 6)
      : deps.typeOptions.value.slice(0, 6);
  });
  const selectedTypeLabel = computed(() => {
    const selected = deps.typeOptions.value.find(
      (type) => String(type.id) === String(deps.profileDraft.typeId),
    );
    return selected
      ? deps.localizedRecordLabel(selected, selected.id)
      : deps.t("shop.workspace.profile.preview.noType");
  });
  const selectedTypeDescription = computed(() => {
    const selected = deps.typeOptions.value.find(
      (type) => String(type.id) === String(deps.profileDraft.typeId),
    );
    if (!selected) return "";
    return String(deps.locale.value).startsWith("pl")
      ? selected.descriptionPl || selected.descriptionEn || ""
      : selected.descriptionEn || selected.descriptionPl || "";
  });
  const selectedWorldLabel = computed(() => {
    const selected = deps.worldProfiles.value.find(
      (profile) =>
        String(profile.id) === String(deps.profileDraft.worldProfileId),
    );
    return selected
      ? deps.localizedRecordLabel(selected, selected.id)
      : String(deps.profileDraft.worldProfileId || "—");
  });
  const selectedLocationLabel = computed(
    () =>
      profileLocationChoices.value.find(
        (entry) => entry.id === deps.profileDraft.locationType,
      )?.label ||
      deps.profileDraft.locationType ||
      "—",
  );
  const profileWarnings = computed(() =>
    buildShopProfileWarnings(
      deps.profileDraft,
      recommendedCounterfeitRisk.value,
    ),
  );

  function profileFieldError(field) {
    if (
      !profileSaveAttempted.value ||
      !missingProfileFields.value.includes(field)
    )
      return "";
    return deps.t("shop.workspace.profile.validation.required");
  }
  function profileGuidance(field, value) {
    const key = `shop.workspace.profile.guidance.${field}.${value}`;
    return value && deps.te(key) ? deps.t(key) : "";
  }
  function applyProfileArchetype(archetype) {
    if (!archetype?.values) return;
    Object.assign(deps.profileDraft, archetype.values);
    deps.markShopDirty();
  }
  function selectProfileType(typeId) {
    deps.profileDraft.typeId = String(typeId || "");
    deps.markShopDirty();
  }
  function applyRecommendedCounterfeitRisk() {
    deps.profileDraft.counterfeitRisk = recommendedCounterfeitRisk.value;
    deps.markShopDirty();
  }
  function rollProfileSignboard() {
    if (!String(deps.profileDraft.typeId || "").trim()) return null;
    const result = drawShopSignboard({
      typeId: deps.profileDraft.typeId,
      typeOptions: deps.typeOptions.value,
      locale: deps.locale.value,
      profile: deps.profileDraft,
      ownerName: deps.profileDraft.ownerName,
      existingNames: deps.shops.value.map((shop) => shop.name).filter(Boolean),
    });
    if (!result?.signboardName) return null;
    deps.profileDraft.signboardName = result.signboardName;
    deps.markShopDirty();
    return result;
  }
  function discardProfileChanges() {
    if (
      deps.formStatus.value.shop === "dirty" &&
      !window.confirm(deps.t("shop.workspace.profile.discardQuestion"))
    )
      return;
    deps.hydrateProfile();
    profileSaveAttempted.value = false;
  }
  async function createNewShop(payload = {}) {
    if (
      deps.formStatus.value.shop === "dirty" &&
      !window.confirm(deps.t("shop.workspace.unsavedQuestion"))
    )
      return undefined;
    const name = String(payload.name || "").trim();
    const typeId = String(payload.typeId || "").trim();
    if (!name || !typeId) return null;
    const ownerCode = String(
      payload.ownerCode || deps.profileDraft.ownerCode || "NPC",
    ).toUpperCase();
    const shopId = await deps.store.dispatch("shop/createShop", {
      name,
      typeId,
      ownerCode,
      ownerName: String(payload.ownerName || "").trim(),
    });
    if (!shopId) return null;
    deps.store.commit("shop/setActiveShop", Number(shopId));
    deps.hydrateProfile();
    profileSaveAttempted.value = false;
    return shopId;
  }
  async function saveProfileAndGenerateOffer() {
    profileSaveAttempted.value = true;
    if (!canSaveProfile.value) return null;
    const saved = await deps.saveProfile();
    if (!saved) return null;
    await deps.generateSuggestions();
    return saved;
  }

  Object.assign(deps, {
    profileSaveAttempted,
    selectedProfileArchetype,
    missingProfileFields,
    profileCompletion,
    canSaveProfile,
    recommendedCounterfeitRisk,
    profileRiskTone,
    profileLocationChoices,
    profileTypeGroups,
    profileSuggestedTypes,
    selectedTypeLabel,
    selectedTypeDescription,
    selectedWorldLabel,
    selectedLocationLabel,
    profileWarnings,
    profileFieldError,
    profileGuidance,
    applyProfileArchetype,
    selectProfileType,
    applyRecommendedCounterfeitRisk,
    rollProfileSignboard,
    discardProfileChanges,
    createNewShop,
    saveProfileAndGenerateOffer,
  });
};
