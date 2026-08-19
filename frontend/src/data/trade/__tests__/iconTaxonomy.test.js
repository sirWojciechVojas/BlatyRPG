import { describe, expect, it } from "vitest";
import {
  countIconFacet,
  createIconMetadataPayload,
  filterIconClasses,
  iconCategories,
  iconCategoriesByItemClass,
  iconItemClasses,
  iconItemGenres,
  iconSubcategories,
  reconcileMetadataSelection,
} from "@/data/trade/iconTaxonomy";
import { inventoryIconMetadataMap } from "@/data/trade/inventoryIconMetadata";

describe("icon taxonomy", () => {
  it("has unique codes, bilingual labels and valid relationships", () => {
    const categoryCodes = new Set(iconCategories.map((entry) => entry.code));
    const subcategoryCodes = new Set(
      iconSubcategories.map((entry) => entry.code),
    );
    const classCodes = new Set(iconItemClasses.map((entry) => entry.code));
    const genreCodes = new Set(iconItemGenres.map((entry) => entry.code));

    expect(categoryCodes.size).toBe(iconCategories.length);
    expect(subcategoryCodes.size).toBe(iconSubcategories.length);
    expect(new Set([...categoryCodes, ...subcategoryCodes]).size).toBe(
      iconCategories.length + iconSubcategories.length,
    );

    [...iconCategories, ...iconSubcategories].forEach((entry) => {
      expect(entry.labelPl.trim()).not.toBe("");
      expect(entry.labelEn.trim()).not.toBe("");
      entry.itemClasses.forEach((code) =>
        expect(classCodes.has(code)).toBe(true),
      );
      entry.itemGenres.forEach((code) =>
        expect(genreCodes.has(code)).toBe(true),
      );
    });
    iconSubcategories.forEach((entry) => {
      expect(categoryCodes.has(entry.categoryCode)).toBe(true);
    });
    expect(
      iconCategoriesByItemClass.get("MAGIC").map((entry) => entry.code),
    ).toEqual(["MISC"]);
  });

  it("models the agreed exceptional category and equipment relationships", () => {
    const classesFor = (category) =>
      iconCategories.find((entry) => entry.code === category).itemClasses;
    const genresFor = (subcategory) =>
      iconSubcategories.find((entry) => entry.code === subcategory).itemGenres;

    expect(classesFor("POWDER")).toEqual(["POWDER", "ALCHEMY"]);
    expect(classesFor("GADGET")).toEqual(["GADGET", "TOOL"]);
    expect(classesFor("CUTLERY")).toEqual(["CUTLERY", "TOOL"]);
    expect(classesFor("POTION")).toEqual(["POTION", "ALCHEMY"]);
    expect(classesFor("MISC")).toEqual(["MISC", "MAGIC"]);
    expect(genresFor("SWORDS")).toEqual(["MELEE"]);
    expect(genresFor("BOWS_CROSSBOWS")).toEqual(["RANGED"]);
    expect(genresFor("PLATE")).toEqual(["BODY"]);
    expect(genresFor("SHIELDS")).toEqual(["SHIELD"]);
    expect(genresFor("ANIMAL_PRODUCTS")).toEqual(["ANIMAL_PRODUCTS"]);
    expect(genresFor("QUEST")).toEqual(["QUEST"]);
    expect(genresFor("CURRENCY")).toEqual(["UTILITY"]);
    expect(genresFor("OTHER")).toEqual(["UTILITY"]);
  });

  it("generates one coherent value per controlled field", () => {
    const relationshipErrors = [];
    Object.values(inventoryIconMetadataMap).forEach((metadata) => {
      expect(metadata.typeKeys).toHaveLength(1);
      expect(metadata.subtypeKeys).toHaveLength(1);
      expect(metadata.itemClasses).toHaveLength(1);
      expect(metadata.itemGenres).toHaveLength(1);

      const category = iconCategories.find(
        (entry) => entry.code === metadata.typeKeys[0],
      );
      const subcategory = iconSubcategories.find(
        (entry) => entry.code === metadata.subtypeKeys[0],
      );
      expect(category).toBeTruthy();
      if (
        subcategory?.categoryCode !== category.code ||
        !subcategory.itemClasses.includes(metadata.itemClasses[0]) ||
        !subcategory.itemGenres.includes(metadata.itemGenres[0])
      ) {
        relationshipErrors.push({
          iconClass: metadata.iconClass,
          sourceName: metadata.sourceName,
          typeKeys: metadata.typeKeys,
          subtypeKeys: metadata.subtypeKeys,
          itemClasses: metadata.itemClasses,
          itemGenres: metadata.itemGenres,
        });
      }
    });
    expect(relationshipErrors).toEqual([]);

    const wand = Object.values(inventoryIconMetadataMap).find((metadata) =>
      /wand/iu.test(metadata.sourceName),
    );
    expect(wand).toMatchObject({
      typeKeys: ["MISC"],
      subtypeKeys: ["OTHER"],
      itemClasses: ["MAGIC"],
      itemGenres: ["UTILITY"],
    });
    const animalProduct = inventoryIconMetadataMap.v0169;
    expect(animalProduct).toMatchObject({
      typeKeys: ["ANIMAL"],
      subtypeKeys: ["ANIMAL_PRODUCTS"],
    });
  });

  it("uses category-first hierarchy and reports dependent corrections", () => {
    const fromSubtype = reconcileMetadataSelection(
      {
        typeKey: "WEAPON",
        subtypeKey: "SHIELDS",
        itemClass: "WEAPON",
        itemGenre: "MELEE",
      },
      "subtypeKey",
    );
    expect(fromSubtype.selection).toEqual({
      typeKey: "ARMOR",
      subtypeKey: "SHIELDS",
      itemClass: "ARMOR",
      itemGenre: "SHIELD",
    });
    expect(fromSubtype.removed).toEqual(
      expect.arrayContaining([
        { field: "typeKey", value: "WEAPON" },
        { field: "itemClass", value: "WEAPON" },
        { field: "itemGenre", value: "MELEE" },
      ]),
    );
    expect(fromSubtype.added).toEqual(
      expect.arrayContaining([
        { field: "typeKey", value: "ARMOR" },
        { field: "itemClass", value: "ARMOR" },
        { field: "itemGenre", value: "SHIELD" },
      ]),
    );

    const fromCategory = reconcileMetadataSelection(
      {
        typeKey: "ARMOR",
        subtypeKey: "CLOAKS",
        itemClass: "CLOTH",
        itemGenre: "UTILITY",
      },
      "typeKey",
    );
    expect(fromCategory.selection).toEqual({
      typeKey: "ARMOR",
      subtypeKey: "",
      itemClass: "ARMOR",
      itemGenre: "",
    });
    expect(fromCategory.removed).toEqual(
      expect.arrayContaining([
        { field: "subtypeKey", value: "CLOAKS" },
        { field: "itemClass", value: "CLOTH" },
        { field: "itemGenre", value: "UTILITY" },
      ]),
    );

    const invalidGenre = reconcileMetadataSelection(
      {
        typeKey: "WEAPON",
        subtypeKey: "SWORDS",
        itemClass: "WEAPON",
        itemGenre: "SHIELD",
      },
      "itemGenre",
    );
    expect(invalidGenre.selection).toEqual({
      typeKey: "WEAPON",
      subtypeKey: "SWORDS",
      itemClass: "WEAPON",
      itemGenre: "MELEE",
    });
  });

  it("reconciles selections against categories added in a campaign dictionary", () => {
    const customCategories = [
      ...iconCategories,
      {
        code: "RELIC",
        labelPl: "Relikty",
        labelEn: "Relics",
        itemClasses: ["MAGIC"],
        itemGenres: ["QUEST"],
        subcategoryCodes: ["HOLY_RELICS"],
      },
    ];
    const customSubcategories = [
      ...iconSubcategories,
      {
        code: "HOLY_RELICS",
        categoryCode: "RELIC",
        labelPl: "Święte relikty",
        labelEn: "Holy relics",
        itemClasses: ["MAGIC"],
        itemGenres: ["QUEST"],
      },
    ];

    const result = reconcileMetadataSelection(
      {
        typeKey: "RELIC",
        subtypeKey: "",
        itemClass: "",
        itemGenre: "",
      },
      "typeKey",
      {
        categories: customCategories,
        subcategories: customSubcategories,
      },
    );

    expect(result.selection).toEqual({
      typeKey: "RELIC",
      subtypeKey: "HOLY_RELICS",
      itemClass: "MAGIC",
      itemGenre: "QUEST",
    });
  });

  it("filters and counts legacy multi-value metadata with all active facets", () => {
    const metadata = {
      v0001: {
        name: "Stary miecz",
        sourceName: "sword.png",
        typeKeys: ["WEAPON", "MISC"],
        subtypeKeys: ["SWORDS", "OTHER"],
      },
      v0002: {
        name: "Tarcza",
        sourceName: "shield.png",
        typeKeys: ["ARMOR"],
        subtypeKeys: ["SHIELDS"],
      },
    };
    const metadataFor = (code) => metadata[code];

    expect(
      filterIconClasses(["v0001", "v0002"], metadataFor, {
        typeKey: "MISC",
        subtypeKey: "OTHER",
        query: "miecz",
        sourceName: "sword.png",
      }),
    ).toEqual(["v0001"]);
    expect(
      countIconFacet(
        ["v0001", "v0002"],
        metadataFor,
        { query: "tarcza" },
        "typeKey",
        "ARMOR",
      ),
    ).toBe(1);
    expect(
      filterIconClasses(["v0001", "v0002"], metadataFor, {
        query: "różne",
      }),
    ).toEqual(["v0001"]);
  });

  it("normalizes the API payload to empty or one-element arrays", () => {
    expect(
      createIconMetadataPayload({
        name: " Różdżka ",
        typeKey: "misc",
        subtypeKey: "other",
        itemClass: "magic",
        itemGenre: "utility",
      }),
    ).toMatchObject({
      name: "Różdżka",
      typeKeys: ["MISC"],
      subtypeKeys: ["OTHER"],
      itemClasses: ["MAGIC"],
      itemGenres: ["UTILITY"],
    });
  });
});
