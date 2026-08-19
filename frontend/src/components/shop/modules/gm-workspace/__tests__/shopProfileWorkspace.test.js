import { reactive, ref } from "vue";
import { describe, expect, it, vi } from "vitest";
import { installShopProfileWorkspace } from "@/components/shop/modules/gm-workspace/composables/useShopProfileWorkspace";
import { lateMedievalTypeSuggestions } from "@/components/shop/modules/gm-workspace/options/profileTypeOptions";
import { shopProfileArchetypes } from "@/components/shop/modules/gm-workspace/options/profileArchetypes";

const completeProfile = () => ({
  signboardName: "Pod Trzema Kluczami",
  typeId: "blacksmith",
  ownerCode: "NPC",
  ownerName: "Mistrz Ortwin",
  worldProfileId: "standard",
  locationType: "miasto",
  legalStatus: "legal",
  wealthTier: "standard",
  reputation: "neutralna",
  seasonality: "caloroczny",
  counterfeitRisk: 10,
});

const createDeps = (overrides = {}) => {
  const calls = [];
  const deps = {
    profileDraft: reactive(completeProfile()),
    profileLocationOptions: ["miasto", "port", "przedmiescie"],
    shopProfileArchetypes,
    lateMedievalTypeSuggestions,
    typeOptions: ref(
      lateMedievalTypeSuggestions.map((entry) => ({
        id: entry.id,
        labelPl: entry.id,
        descriptionPl: `Opis ${entry.id}`,
        category: "Handel",
      })),
    ),
    worldProfiles: ref([]),
    shops: ref([{ id: 1, name: "Pod Trzema Kluczami" }]),
    locale: ref("pl"),
    formStatus: ref({ shop: "clean" }),
    activeShopId: ref(1),
    localizedRecordLabel: (_entry, fallback) => fallback,
    t: (key) => key,
    te: () => false,
    markShopDirty: vi.fn(),
    hydrateProfile: vi.fn(),
    saveProfile: vi.fn(async () => {
      calls.push("save");
      return { shopId: 1 };
    }),
    generateSuggestions: vi.fn(async () => {
      calls.push("suggestions");
    }),
    store: {
      dispatch: vi.fn(async () => 12),
      commit: vi.fn(),
    },
    ...overrides,
  };
  installShopProfileWorkspace(deps);
  return { deps, calls };
};

describe("shop profile workspace logic", () => {
  it("applies a late-medieval archetype without replacing name or owner", () => {
    const { deps } = createDeps();
    const fence = shopProfileArchetypes.find((entry) => entry.id === "fence");

    deps.applyProfileArchetype(fence);

    expect(deps.profileDraft.signboardName).toBe("Pod Trzema Kluczami");
    expect(deps.profileDraft.ownerName).toBe("Mistrz Ortwin");
    expect(deps.profileDraft.typeId).toBe("paserska_komora");
    expect(deps.profileDraft.legalStatus).toBe("grey");
    expect(deps.profileDraft.locationType).toBe("przedmiescie");
    expect(deps.profileDraft.counterfeitRisk).toBe(62);
    expect(deps.selectedProfileArchetype.value).toBe("fence");
    expect(deps.markShopDirty).toHaveBeenCalledOnce();
  });

  it("ranks historically plausible business types for the current context", () => {
    const { deps } = createDeps();
    deps.profileDraft.locationType = "port";
    deps.profileDraft.legalStatus = "illegal";
    deps.profileDraft.wealthTier = "standard";

    expect(deps.profileSuggestedTypes.value[0].id).toBe("paserska_komora");

    deps.selectProfileType("sklad_kontrabandy");
    expect(deps.profileDraft.typeId).toBe("sklad_kontrabandy");
    expect(deps.markShopDirty).toHaveBeenCalledOnce();
  });

  it("validates required profile foundations and detects implausible risk", () => {
    const { deps } = createDeps();
    deps.profileDraft.signboardName = "";
    deps.profileDraft.typeId = "";
    deps.profileDraft.legalStatus = "illegal";
    deps.profileDraft.reputation = "podejrzana";
    deps.profileDraft.locationType = "port";
    deps.profileDraft.counterfeitRisk = 5;

    expect(deps.canSaveProfile.value).toBe(false);
    expect(deps.missingProfileFields.value).toEqual([
      "signboardName",
      "typeId",
    ]);
    expect(deps.recommendedCounterfeitRisk.value).toBe(88);
    expect(deps.profileWarnings.value.map((entry) => entry.key)).toEqual(
      expect.arrayContaining(["missingType", "lowRiskIllegal", "riskMismatch"]),
    );

    deps.profileSaveAttempted.value = true;
    expect(deps.profileFieldError("signboardName")).toContain("required");
  });

  it("creates a persisted shop and selects its profile for editing", async () => {
    const { deps } = createDeps();

    const shopId = await deps.createNewShop({
      name: "Pod Czarnym Młotem",
      typeId: "blacksmith",
      ownerCode: "npc",
      ownerName: "Mistrz Ortwin",
    });

    expect(shopId).toBe(12);
    expect(deps.store.dispatch).toHaveBeenCalledWith("shop/createShop", {
      name: "Pod Czarnym Młotem",
      typeId: "blacksmith",
      ownerCode: "NPC",
      ownerName: "Mistrz Ortwin",
    });
    expect(deps.store.commit).toHaveBeenCalledWith("shop/setActiveShop", 12);
    expect(deps.hydrateProfile).toHaveBeenCalledOnce();
  });

  it("does not create a shop without a name", async () => {
    const { deps } = createDeps();

    expect(await deps.createNewShop({ name: "   " })).toBeNull();
    expect(deps.store.dispatch).not.toHaveBeenCalled();
  });

  it("does not create a shop without a business type", async () => {
    const { deps } = createDeps();

    expect(await deps.createNewShop({ name: "Pod Czarnym Młotem" })).toBeNull();
    expect(deps.store.dispatch).not.toHaveBeenCalled();
  });

  it("rolls a signboard from the current profile type", () => {
    const { deps } = createDeps();

    const result = deps.rollProfileSignboard();

    expect(result.signboardName).toBe(deps.profileDraft.signboardName);
    expect(result.meta.generatorTypeId).toBe("blacksmith");
    expect(deps.markShopDirty).toHaveBeenCalledOnce();
  });

  it("saves before generating stock suggestions", async () => {
    const { deps, calls } = createDeps();

    await deps.saveProfileAndGenerateOffer();

    expect(calls).toEqual(["save", "suggestions"]);
  });
});
