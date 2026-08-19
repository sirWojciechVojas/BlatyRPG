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
  it("uses scoreRaw/score/tie-breaker and avoids top score ties for carpenter", () => {
    const bundle = generateShopSuggestionBundle({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: carpenterProfile,
      nextTemplateId: 7100,
    });
    const topSuggestions = (bundle?.suggestions || []).slice(0, 6);
    expect(topSuggestions.length).toBeGreaterThanOrEqual(3);
    const roundedScores = topSuggestions.map((entry) =>
      Number(entry?.score || 0).toFixed(2),
    );
    expect(new Set(roundedScores).size).toBe(roundedScores.length);
    topSuggestions.forEach((entry, index) => {
      expect(Number.isFinite(Number(entry?.scoreRaw))).toBe(true);
      expect(Number.isFinite(Number(entry?.score))).toBe(true);
      expect(Number.isFinite(Number(entry?.scoreTieBreaker))).toBe(true);
      expect(Number(entry?.score)).toBe(
        Number(Number(entry?.scoreRaw || 0).toFixed(2)),
      );
      expect(Number(entry?.score).toFixed(2)).toBe(roundedScores[index]);
    });
  });
  it("builds at least one suggestion for every shop type", () => {
    const typeNodes = (shopCatalogNetwork || []).filter(
      (entry) => entry?.level === "type",
    );
    expect(typeNodes.length).toBeGreaterThan(0);
    typeNodes.forEach((node, index) => {
      const bundle = generateShopSuggestionBundle({
        templates: [],
        catalogNodes: shopCatalogNetwork,
        profile: {
          ...baseProfile,
          shopId: index + 1,
          typeId: String(node.id),
          signboardName: String(node.namePl || node.id || "Sklep"),
          categoryTags: [`typ:${String(node.id)}`],
        },
        nextTemplateId: 8000 + index * 100,
      });
      const suggestions = Array.isArray(bundle?.suggestions)
        ? bundle.suggestions
        : [];
      expect(suggestions.length).toBeGreaterThan(0);
      const requiredClasses = Array.isArray(
        node?.suggestionRules?.requiredItemClasses,
      )
        ? node.suggestionRules.requiredItemClasses.map((entry) =>
            String(entry || "").toUpperCase(),
          )
        : [];
      if (requiredClasses.length) {
        expect(
          suggestions.some((entry) =>
            requiredClasses.includes(
              String(entry?.classKey || "").toUpperCase(),
            ),
          ),
        ).toBe(true);
      }
    });
  });
  it("enforces hard class rules for blacksmith and blocks map-like stationery entries", () => {
    const bundle = generateShopSuggestionBundle({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: blacksmithProfile,
      nextTemplateId: 9000,
    });
    const entries = [
      ...(Array.isArray(bundle?.suggestions) ? bundle.suggestions : []),
      ...(Array.isArray(bundle?.recommendations) ? bundle.recommendations : []),
    ];
    expect(entries.length).toBeGreaterThan(0);
    entries.forEach((entry) => {
      expect(["TOOL", "WEAPON", "GADGET"]).toContain(
        String(entry?.classKey || "").toUpperCase(),
      );
      const displayName = String(
        entry?.displayName || entry?.templateName || entry?.label || "",
      ).toLowerCase();
      expect(displayName.includes("map")).toBe(false);
      expect(displayName.includes("pergamin")).toBe(false);
      expect(displayName.includes("dokument")).toBe(false);
    });
  });
  it("does not fabricate utility suffix variants in generated suggestion names", () => {
    const bundle = generateShopSuggestionBundle({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: blacksmithProfile,
      nextTemplateId: 9100,
    });
    const entries = [
      ...(Array.isArray(bundle?.suggestions) ? bundle.suggestions : []),
      ...(Array.isArray(bundle?.recommendations) ? bundle.recommendations : []),
    ];
    expect(entries.length).toBeGreaterThan(0);
    entries.forEach((entry) => {
      const displayName = String(
        entry?.displayName || entry?.templateName || entry?.label || "",
      );
      expect(bannedSuffixPattern.test(displayName)).toBe(false);
    });
  });
  it("reuses a matching icon for lina konopna draft suggestions", () => {
    const bundle = generateShopSuggestionBundle({
      templates: mockTemplates,
      catalogNodes: shopCatalogNetwork,
      profile: carpenterProfile,
      nextTemplateId: 9200,
    });
    const target = (bundle?.recommendations || []).find(
      (entry) => String(entry?.displayName || "") === "Lina konopna",
    );
    expect(target).toBeTruthy();
    expect(String(target?.imgClass || "")).toBe("v1030");
  });
  it("deduplicates identical suggestion labels across template and draft sources", () => {
    const templates = [
      ...mockTemplates,
      {
        ...mockTemplates[6],
        ID: 99001,
        NAME: "Lina konopna",
        ITEM_CLASS: "TOOL",
        ITEM_GENRE: "UTILITY",
      },
    ];
    const bundle = generateShopSuggestionBundle({
      templates,
      catalogNodes: shopCatalogNetwork,
      profile: carpenterProfile,
      nextTemplateId: 9300,
    });
    const labels = (bundle?.recommendations || []).map((entry) =>
      String(entry?.displayName || entry?.templateName || entry?.label || ""),
    );
    const linaMatches = labels.filter((entry) => entry === "Lina konopna");
    expect(linaMatches).toHaveLength(1);
  });
  it("keeps horse and metal related suggestions for blacksmith", () => {
    const bundle = generateShopSuggestionBundle({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: blacksmithProfile,
      nextTemplateId: 9400,
    });
    const labels = [
      ...(bundle?.suggestions || []),
      ...(bundle?.recommendations || []),
    ].map((entry) =>
      String(entry?.displayName || entry?.templateName || entry?.label || ""),
    );
    expect(labels.some((entry) => entry.includes("Podkowy"))).toBe(true);
    expect(
      labels.some(
        (entry) => entry.includes("Sztabka") || entry.includes("Pręt"),
      ),
    ).toBe(true);
  });
  it("offers divination cards for fortune teller", () => {
    const bundle = generateShopSuggestionBundle({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: fortuneTellerProfile,
      nextTemplateId: 9500,
    });
    const labels = [
      ...(bundle?.suggestions || []),
      ...(bundle?.recommendations || []),
    ].map((entry) =>
      String(entry?.displayName || entry?.templateName || entry?.label || ""),
    );
    expect(
      labels.some(
        (entry) =>
          entry.includes("kart wróżebnych") || entry.includes("Karty tarota"),
      ),
    ).toBe(true);
  });
});
