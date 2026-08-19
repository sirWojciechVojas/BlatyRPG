/* eslint-disable no-unused-vars -- split test keeps the shared canonical fixture */
import { describe, expect, it } from "vitest";
import {
  generateShopSuggestionBundle,
  generateShopSuggestions,
} from "@/lib/shopSuggestionEngine";
import { shopCatalogNetwork } from "@/mock/shopCatalogNetwork";
import { mockTemplates } from "@/mock/shopData";
const baseProfile = {
  shopId: 1,
  typeId: "apothecary",
  worldProfileId: "standard",
  locationType: "miasto",
  legalStatus: "legal",
  wealthTier: "standard",
  reputation: "neutralna",
  seasonality: "caloroczny",
  signboardName: "Alechemik Bazyl",
  signboardAltNames: ["alchemik", "bazyl"],
  categoryTags: ["typ:apothecary", "profil:aptekarz", "lok:miasto"],
};
const alchemistProfile = {
  ...baseProfile,
  typeId: "alchemist",
  signboardName: "Pod Szklaną Gwiazdą",
  signboardAltNames: ["alchemik", "retorta"],
  categoryTags: ["typ:alchemist", "profil:alchemia", "lok:miasto"],
};
const carpenterProfile = {
  ...baseProfile,
  typeId: "carpenter",
  signboardName: "Stolarnia pod Toporem",
  signboardAltNames: ["ciesla", "stolarnia"],
  categoryTags: ["typ:carpenter", "profil:ciesla", "lok:miasto"],
};
const blacksmithProfile = {
  ...baseProfile,
  typeId: "blacksmith",
  signboardName: "Kuznia pod Iskra",
  signboardAltNames: ["kowal", "kuznia"],
  categoryTags: ["typ:blacksmith", "profil:kowal", "lok:miasto"],
};
const fortuneTellerProfile = {
  ...baseProfile,
  typeId: "fortune_teller",
  signboardName: "Pod Srebrnym Okiem",
  signboardAltNames: ["wrozbita", "karty"],
  categoryTags: ["typ:fortune_teller", "profil:wrozbita", "lok:jarmark"],
};
const blockedSuggestionNamePattern =
  /\b(cechowy|cechowa|cechowe|seria|partia|pakiet|dostawa|edycja|wariant|zapas|zestaw)\b/i;
const hasBlockedSuggestionNameToken = (value) =>
  blockedSuggestionNamePattern.test(String(value || ""));
const generatedMaterialPrefixPattern =
  /^(stalowy|miedziany|skorzany|lniany|drewniany|mosiezny|srebrny|zelazny|szklany|granitowy|woskowany|debowy)\s/i;
const obviousMaterialGrammarErrorsPattern =
  /\b(miedziany|stalowy|drewniany|lniany|skorzany|mosiezny)\s+(szkatulka|sakiewka|pudelko)\b/i;
const hasMaterialMarker = (value) =>
  /\b(miedzian|stalow|skorzan|lnian|drewnian|mosiezn|srebrn|zelazn|szklan|granitow|woskowan|debow|z miedzi|ze stali|ze skory|z drewna|z mosiadzu)\b/i.test(
    String(value || ""),
  );
const bannedSuffixPattern =
  /\b(z uchwytem|do pasa|z zamkiem|do podrozy|do warsztatu|z pokrowcem|na pasek|z zaczepem)\b/i;

describe("shopSuggestionEngine", () => {
  it("does not generate generic fallback name for apothecary", () => {
    const suggestions = generateShopSuggestions({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: baseProfile,
      nextTemplateId: 1000,
    });
    expect(suggestions.length).toBeGreaterThan(0);
    expect(
      suggestions.some((entry) =>
        String(entry?.displayName || "")
          .toLowerCase()
          .includes("towar podstawowy"),
      ),
    ).toBe(false);
  });
  it("builds deterministic personalized variants and reason details", () => {
    const first = generateShopSuggestions({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: baseProfile,
      nextTemplateId: 2000,
    });
    const second = generateShopSuggestions({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: baseProfile,
      nextTemplateId: 2000,
    });
    expect(first.length).toBeGreaterThan(0);
    expect(first).toEqual(second);
    first.forEach((entry) => {
      expect(Array.isArray(entry.personalizedVariants)).toBe(true);
      expect(entry.personalizedVariants.length).toBeGreaterThanOrEqual(2);
      expect(entry.personalizedVariants.length).toBeLessThanOrEqual(6);
      entry.personalizedVariants.forEach((variant) => {
        expect(typeof variant.variantId).toBe("string");
        expect(typeof variant.personalPseu).toBe("string");
        expect(typeof variant.personalDesc).toBe("string");
        expect(Number(variant.personalCost)).toBeGreaterThan(0);
      });
      expect(Array.isArray(entry.reasonDetails)).toBe(true);
    });
  });
  it("emits recommendation decision for every template", () => {
    const bundle = generateShopSuggestionBundle({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: baseProfile,
      nextTemplateId: 3000,
    });
    const recommendations = Array.isArray(bundle?.recommendations)
      ? bundle.recommendations
      : [];
    const templateRecommendations = recommendations.filter(
      (entry) => entry?.action === "use_existing",
    );
    expect(templateRecommendations.length).toBe(mockTemplates.length);
    templateRecommendations.forEach((entry) => {
      expect(["add", "consider", "skip"]).toContain(entry.recommendationCode);
      expect(typeof entry.recommendationLabelPl).toBe("string");
      expect(entry.recommendationLabelPl.length).toBeGreaterThan(0);
    });
  });
  it("creates draft candidates from class/genre examples when template is missing", () => {
    const bundle = generateShopSuggestionBundle({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: alchemistProfile,
      nextTemplateId: 4000,
    });
    const names = (bundle?.recommendations || []).map((entry) =>
      String(entry?.displayName || "").toLowerCase(),
    );
    expect(names.some((entry) => entry.includes("mikstura leczenia"))).toBe(
      true,
    );
    expect(names.some((entry) => entry.includes("eliksir odporności"))).toBe(
      true,
    );
  });
  it("returns 20-30 positive suggestions and recommendations at least 5x larger", () => {
    const bundle = generateShopSuggestionBundle({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: alchemistProfile,
      nextTemplateId: 5000,
    });
    const suggestions = Array.isArray(bundle?.suggestions)
      ? bundle.suggestions
      : [];
    const recommendations = Array.isArray(bundle?.recommendations)
      ? bundle.recommendations
      : [];
    expect(suggestions.length).toBeGreaterThanOrEqual(20);
    expect(suggestions.length).toBeLessThanOrEqual(30);
    expect(recommendations.length).toBeGreaterThanOrEqual(
      suggestions.length * 5,
    );
    expect(recommendations.length).toBeLessThanOrEqual(180);
  });
  it("filters out series/bundle style names from generated entries", () => {
    const noisyTemplates = [
      ...mockTemplates,
      {
        ...mockTemplates[0],
        ID: 9999,
        NAME: "Pakiet handlowy 5 seria",
      },
    ];
    const bundle = generateShopSuggestionBundle({
      templates: noisyTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: alchemistProfile,
      nextTemplateId: 5500,
    });
    const allEntries = [
      ...(Array.isArray(bundle?.suggestions) ? bundle.suggestions : []),
      ...(Array.isArray(bundle?.recommendations) ? bundle.recommendations : []),
    ];
    expect(allEntries.length).toBeGreaterThan(0);
    allEntries.forEach((entry) => {
      const displayName = String(
        entry?.displayName || entry?.templateName || entry?.label || "",
      );
      expect(hasBlockedSuggestionNameToken(displayName)).toBe(false);
      (entry?.personalizedVariants || []).forEach((variant) => {
        expect(hasBlockedSuggestionNameToken(variant?.personalPseu)).toBe(
          false,
        );
      });
    });
  });
  it("covers products, ingredients and equipment segments for alchemist", () => {
    const bundle = generateShopSuggestionBundle({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: alchemistProfile,
      nextTemplateId: 6000,
    });
    const segments = new Set(
      (bundle?.recommendations || [])
        .map((entry) => String(entry?.segment || ""))
        .filter(Boolean),
    );
    expect(segments.has("products")).toBe(true);
    expect(segments.has("ingredients")).toBe(true);
    expect(segments.has("equipment")).toBe(true);
  });
  it("keeps segment coverage in final suggestions for alchemist", () => {
    const bundle = generateShopSuggestionBundle({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: alchemistProfile,
      nextTemplateId: 6050,
    });
    const segments = new Set(
      (bundle?.suggestions || [])
        .map((entry) => String(entry?.segment || ""))
        .filter(Boolean),
    );
    expect(segments.has("products")).toBe(true);
    expect(segments.has("ingredients")).toBe(true);
    expect(segments.has("equipment")).toBe(true);
  });
  it("does not add generated material prefixes to template and draft names", () => {
    const bundle = generateShopSuggestionBundle({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: alchemistProfile,
      nextTemplateId: 6500,
    });
    const allEntries = Array.isArray(bundle?.recommendations)
      ? bundle.recommendations
      : [];
    expect(allEntries.length).toBeGreaterThan(0);
    allEntries.forEach((entry) => {
      const displayName = String(
        entry?.displayName || entry?.templateName || entry?.label || "",
      );
      expect(generatedMaterialPrefixPattern.test(displayName)).toBe(false);
    });
  });
  it("materializes materials only in personalized variants with safe grammar", () => {
    const templates = [
      ...mockTemplates,
      {
        ...mockTemplates[0],
        ID: 91001,
        NAME: "Szkatulka na fiolki",
        DESCRIPTION: "Pojemnik warsztatowy.",
        ITEM_CLASS: "TOOL",
        ITEM_GENRE: "UTILITY",
      },
    ];
    const bundle = generateShopSuggestionBundle({
      templates,
      catalogNodes: shopCatalogNetwork,
      profile: carpenterProfile,
      nextTemplateId: 7000,
    });
    const target = (bundle?.recommendations || []).find(
      (entry) => Number(entry?.templateId) === 91001,
    );
    expect(target).toBeTruthy();
    expect(String(target?.displayName || "")).toBe("Szkatulka na fiolki");
    const variantNames = (target?.personalizedVariants || []).map((entry) =>
      String(entry?.personalPseu || ""),
    );
    expect(variantNames.length).toBeGreaterThan(0);
    expect(variantNames.some((name) => hasMaterialMarker(name))).toBe(true);
    variantNames.forEach((name) => {
      expect(obviousMaterialGrammarErrorsPattern.test(name)).toBe(false);
    });
  });
});
