import { beforeEach, describe, expect, it } from "vitest";
import iconSourceNames from "@/data/trade/inventory-icon-source-names";
import {
  resolveItemIcon,
  resolveItemIconClass,
  withResolvedSuggestionIcon,
} from "@/lib/trade/shopItemIconResolver";
import { generateShopSuggestionBundle } from "@/lib/shopSuggestionEngine";
import { shopCatalogNetwork } from "@/mock/shopCatalogNetwork";
import { setIconMetadataOverrides } from "@/lib/trade/iconMetadataRegistry";

const item = (name, itemClass, imgClass, itemGenre = "UTILITY") => ({
  NAME: name,
  DESCRIPTION: "",
  ITEM_CLASS: itemClass,
  ITEM_GENRE: itemGenre,
  IMG_CLASS: imgClass,
});

describe("shopItemIconResolver", () => {
  beforeEach(() => setIconMetadataOverrides([]));

  it("uses metadata edited by the GM for automatic matching", () => {
    setIconMetadataOverrides([
      {
        iconClass: "v1250",
        name: "Tarcza herbowa",
        specialMarks: "tarcza herbowa, tarcza rodowa",
        typeKeys: ["ARMOR"],
        subtypeKeys: ["SHIELDS"],
        itemClasses: ["ARMOR"],
        itemGenres: ["SHIELD_HERALDIC"],
      },
    ]);

    expect(
      resolveItemIconClass(
        item("Tarcza rodowa", "ARMOR", "v0619", "SHIELD_HERALDIC"),
      ),
    ).toBe("v1250");
  });
  it("replaces body armour with an actual shield", () => {
    const result = resolveItemIcon(item("Tarcza okuta", "ARMOR", "v0619"));

    expect(result.iconClass).toBe("v1240");
    expect(result.ruleId).toBe("shield");
    expect(iconSourceNames[result.iconClass].toLowerCase()).toContain("shield");
  });

  it("distinguishes a pavise from a buckler", () => {
    expect(
      resolveItemIconClass(item("Pawęż drewniana", "ARMOR", "v0619")),
    ).toBe("v1233");
    expect(
      resolveItemIconClass(item("Puklerz stalowy", "ARMOR", "v0619")),
    ).toBe("v1252");
  });

  it("keeps a manually selected icon when it belongs to the same family", () => {
    expect(resolveItemIconClass(item("Tarcza herbowa", "ARMOR", "v1250"))).toBe(
      "v1250",
    );
  });

  it.each([
    ["Chleb razowy", "FOOD", "v1148", "v0112", "loaf"],
    ["Pergamin kupiecki", "STATIONERY", "v0724", "v1195", "scroll"],
    ["Hełm żelazny", "ARMOR", "v0619", "v0496", "helm"],
    ["Miecz cechowy", "WEAPON", "v0170", "v1289", "sword"],
    ["Pierścień srebrny", "JEWELLERY", "v1041", "v1151", "ring"],
    ["Pochodnia smolna", "TOOL", "v1030", "v1304", "torch"],
    ["Koc podróżny", "CLOTH", "v1030", "v1361", "cloth"],
    ["Pies stróżujący", "ANIMAL", "v1030", "v0650", "beagle"],
    ["Saletra oczyszczona", "POWDER", "v1030", "v1032", "powder"],
  ])(
    "maps %s to a matching sprite",
    (name, itemClass, current, expected, source) => {
      const resolved = resolveItemIconClass(item(name, itemClass, current));
      expect(resolved).toBe(expected);
      expect(iconSourceNames[resolved].toLowerCase()).toContain(source);
    },
  );

  it("normalizes both suggestion preview and its draft template", () => {
    const suggestion = withResolvedSuggestionIcon({
      suggestionId: "draft:shield",
      imgClass: "v0619",
      draftTemplate: item("Tarcza okuta", "ARMOR", "v0619", "SHIELD"),
    });

    expect(suggestion.imgClass).toBe("v1240");
    expect(suggestion.draftTemplate.IMG_CLASS).toBe("v1240");
  });

  it("uses the resolver in generated armorer recommendations", () => {
    const bundle = generateShopSuggestionBundle({
      templates: [],
      catalogNodes: shopCatalogNetwork,
      profile: {
        typeId: "armorer",
        worldProfileId: "standard",
        locationType: "miasto",
        legalStatus: "legal",
        wealthTier: "standard",
        reputation: "neutralna",
        seasonality: "caloroczny",
      },
      nextTemplateId: 9000,
    });
    const entries = bundle.recommendations || [];
    const pavise = entries.find(
      (entry) => entry.displayName === "Pawęż drewniana",
    );
    const buckler = entries.find(
      (entry) => entry.displayName === "Puklerz stalowy",
    );

    expect(pavise?.imgClass).toBe("v1233");
    expect(buckler?.imgClass).toBe("v1252");
  });
});
