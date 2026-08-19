import taxonomySource from "./iconTaxonomy.json";

const CATEGORY_CLASS_CODES = Object.freeze({
  WEAPON: ["WEAPON"],
  ARMOR: ["ARMOR"],
  CLOTH: ["CLOTH"],
  POWDER: ["POWDER", "ALCHEMY"],
  ANIMAL: ["ANIMAL"],
  JEWELLERY: ["JEWELLERY"],
  STATIONERY: ["STATIONERY"],
  FORAGE: ["FORAGE"],
  GADGET: ["GADGET", "TOOL"],
  ARMAMENT: ["ARMAMENT"],
  CUTLERY: ["CUTLERY", "TOOL"],
  POTION: ["POTION", "ALCHEMY"],
  FOOD: ["FOOD"],
  MISC: ["MISC", "MAGIC"],
});

const ITEM_CLASS_LABELS = Object.freeze({
  ALCHEMY: ["Alchemia", "Alchemy"],
  ANIMAL: ["Zwierzęta", "Animal"],
  ARMAMENT: ["Uzbrojenie", "Armament"],
  ARMOR: ["Pancerz", "Armor"],
  CLOTH: ["Ubrania", "Cloth"],
  CUTLERY: ["Sztućce", "Cutlery"],
  FOOD: ["Żywność", "Food"],
  FORAGE: ["Surowce", "Forage"],
  GADGET: ["Gadżety", "Gadget"],
  JEWELLERY: ["Biżuteria", "Jewellery"],
  MAGIC: ["Magia", "Magic"],
  MISC: ["Różne", "Miscellaneous"],
  POTION: ["Mikstury", "Potion"],
  POWDER: ["Prochy", "Powder"],
  STATIONERY: ["Piśmiennicze", "Stationery"],
  TOOL: ["Narzędzia", "Tool"],
  WEAPON: ["Broń", "Weapon"],
});

const ITEM_GENRE_LABELS = Object.freeze({
  MELEE: ["Broń do walki wręcz", "Melee"],
  RANGED: ["Broń dystansowa", "Ranged"],
  BODY: ["Pancerz ciała", "Body armor"],
  SHIELD: ["Tarcza", "Shield"],
  MOUNTS: ["Wierzchowce", "Mounts"],
  PETS: ["Zwierzęta towarzyszące", "Pets"],
  LIVESTOCK: ["Zwierzęta hodowlane", "Livestock"],
  ANIMAL_PRODUCTS: ["Produkty odzwierzęce", "Animal products"],
  HEALING: ["Leczenie", "Healing"],
  BUFFS: ["Wzmocnienia", "Buffs"],
  RESISTANCE: ["Odporność", "Resistance"],
  TOXINS: ["Toksyny", "Toxins"],
  MEALS: ["Posiłki", "Meals"],
  BAKERY: ["Pieczywo i wypieki", "Bakery"],
  PRESERVES: ["Przetwory", "Preserves"],
  DRINKS: ["Napoje", "Drinks"],
  SPICES_HERBS: ["Przyprawy i zioła", "Spices and herbs"],
  QUEST: ["Przedmioty fabularne", "Quest items"],
  OTHER: ["Inne", "Other"],
  UTILITY: ["Użytkowe", "Utility"],
});

const SUBTYPE_GENRE_CODES = Object.freeze({
  SWORDS: ["MELEE"],
  AXES_MACES: ["MELEE"],
  BOWS_CROSSBOWS: ["RANGED"],
  THROWING: ["RANGED"],
  POLEARMS: ["MELEE"],
  FIREARMS: ["RANGED"],
  LEATHER: ["BODY"],
  SCALE_MAIL: ["BODY"],
  PLATE: ["BODY"],
  SHIELDS: ["SHIELD"],
  BOOTS_GLOVES: ["BODY"],
  MOUNTS: ["MOUNTS"],
  PETS: ["PETS"],
  LIVESTOCK: ["LIVESTOCK"],
  ANIMAL_PRODUCTS: ["ANIMAL_PRODUCTS"],
  AMMUNITION: ["RANGED"],
  HEALING: ["HEALING"],
  BUFFS: ["BUFFS"],
  RESISTANCE: ["RESISTANCE"],
  TOXINS: ["TOXINS"],
  MEAT_FISH: ["MEALS"],
  BREAD_BAKERY: ["BAKERY"],
  FRUIT_VEGETABLES: ["MEALS", "PRESERVES"],
  DRINKS: ["DRINKS"],
  SPICES_HERBS: ["SPICES_HERBS"],
  PREPARED_MEALS: ["MEALS"],
  QUEST: ["QUEST"],
  CURRENCY: ["UTILITY"],
  OTHER: ["UTILITY"],
});

const normalizeCode = (value) =>
  String(value || "")
    .trim()
    .toUpperCase();

export const iconCategories = Object.freeze(
  taxonomySource.types.map((category) =>
    Object.freeze({
      code: category.key,
      categoryCode: null,
      labelPl: category.label.pl,
      labelEn: category.label.en,
      itemClasses: Object.freeze([
        ...(CATEGORY_CLASS_CODES[category.key] || [category.key]),
      ]),
      itemGenres: Object.freeze([
        ...new Set(
          category.subtypes.flatMap(
            (subcategory) =>
              SUBTYPE_GENRE_CODES[subcategory.key] || ["UTILITY"],
          ),
        ),
      ]),
      subcategoryCodes: Object.freeze(
        category.subtypes.map((subcategory) => subcategory.key),
      ),
    }),
  ),
);

export const iconSubcategories = Object.freeze(
  taxonomySource.types.flatMap((category) =>
    category.subtypes.map((subcategory) =>
      Object.freeze({
        code: subcategory.key,
        categoryCode: category.key,
        labelPl: subcategory.label.pl,
        labelEn: subcategory.label.en,
        itemClasses: Object.freeze([
          ...(CATEGORY_CLASS_CODES[category.key] || [category.key]),
        ]),
        itemGenres: Object.freeze([
          ...(SUBTYPE_GENRE_CODES[subcategory.key] || ["UTILITY"]),
        ]),
      }),
    ),
  ),
);

const dictionaryEntries = (labels) =>
  Object.freeze(
    Object.entries(labels).map(([code, [labelPl, labelEn]]) =>
      Object.freeze({ code, labelPl, labelEn }),
    ),
  );

export const iconItemClasses = dictionaryEntries(ITEM_CLASS_LABELS);
export const iconItemGenres = dictionaryEntries(ITEM_GENRE_LABELS);
export const iconItemClassByCode = new Map(
  iconItemClasses.map((entry) => [entry.code, entry]),
);
export const iconItemGenreByCode = new Map(
  iconItemGenres.map((entry) => [entry.code, entry]),
);

export const iconCategoryByCode = new Map(
  iconCategories.map((entry) => [entry.code, entry]),
);
export const iconSubcategoryByCode = new Map(
  iconSubcategories.map((entry) => [entry.code, entry]),
);

const createReverseIndex = (entries, field) => {
  const index = new Map();
  entries.forEach((entry) => {
    (entry[field] || []).forEach((code) => {
      index.set(code, [...(index.get(code) || []), entry]);
    });
  });
  return index;
};

export const iconCategoriesByItemClass = createReverseIndex(
  iconCategories,
  "itemClasses",
);
export const iconCategoriesByItemGenre = createReverseIndex(
  iconCategories,
  "itemGenres",
);
export const iconSubcategoriesByItemClass = createReverseIndex(
  iconSubcategories,
  "itemClasses",
);
export const iconSubcategoriesByItemGenre = createReverseIndex(
  iconSubcategories,
  "itemGenres",
);

const taxonomyEntries = new Map([
  ...iconCategoryByCode.entries(),
  ...iconSubcategoryByCode.entries(),
]);

export const getIconTaxonomyEntry = (code) =>
  taxonomyEntries.get(normalizeCode(code)) || null;

export const iconTaxonomyLabel = (code, locale = "pl") => {
  const entry = getIconTaxonomyEntry(code);
  if (!entry) return normalizeCode(code);
  return String(locale).startsWith("pl") ? entry.labelPl : entry.labelEn;
};

export const iconTaxonomyEnglishLabel = (code) =>
  getIconTaxonomyEntry(code)?.labelEn || normalizeCode(code);

const branches = Object.freeze(
  iconSubcategories.flatMap((subcategory) =>
    subcategory.itemClasses.flatMap((itemClass) =>
      subcategory.itemGenres.map((itemGenre) =>
        Object.freeze({
          typeKey: subcategory.categoryCode,
          subtypeKey: subcategory.code,
          itemClass,
          itemGenre,
        }),
      ),
    ),
  ),
);

export const iconTaxonomyBranches = branches;
export const metadataFieldNames = Object.freeze([
  "typeKey",
  "subtypeKey",
  "itemClass",
  "itemGenre",
]);

const branchesMatching = (selection = {}, ignoredField = "") =>
  branches.filter((branch) =>
    metadataFieldNames.every((field) => {
      if (field === ignoredField) return true;
      const selected = normalizeCode(selection[field]);
      return !selected || branch[field] === selected;
    }),
  );

export const compatibleMetadataCodes = (
  selection = {},
  field,
  ignoredField = field,
) => [
  ...new Set(
    branchesMatching(selection, ignoredField).map((branch) => branch[field]),
  ),
];

export const reconcileMetadataSelection = (
  selection = {},
  changedField = "typeKey",
  taxonomy = {},
) => {
  const categories = Array.isArray(taxonomy.categories)
    ? taxonomy.categories
    : iconCategories;
  const subcategories = Array.isArray(taxonomy.subcategories)
    ? taxonomy.subcategories
    : iconSubcategories;
  const categoryByCode = new Map(
    categories.map((entry) => [normalizeCode(entry.code), entry]),
  );
  const subcategoryByCode = new Map(
    subcategories.map((entry) => [normalizeCode(entry.code), entry]),
  );
  const next = Object.fromEntries(
    metadataFieldNames.map((field) => [field, normalizeCode(selection[field])]),
  );
  const removed = [];
  const added = [];

  if (next.subtypeKey && changedField !== "typeKey") {
    const parent = subcategoryByCode.get(next.subtypeKey)?.categoryCode || "";
    if (parent && next.typeKey !== parent) {
      if (next.typeKey) removed.push({ field: "typeKey", value: next.typeKey });
      next.typeKey = parent;
      if (changedField !== "typeKey") {
        added.push({ field: "typeKey", value: parent });
      }
    }
  }

  const category = categoryByCode.get(next.typeKey) || null;
  const allowedSubtypes = category?.subcategoryCodes || [];
  if (next.subtypeKey && !allowedSubtypes.includes(next.subtypeKey)) {
    removed.push({ field: "subtypeKey", value: next.subtypeKey });
    next.subtypeKey = "";
  }
  if (!next.subtypeKey && allowedSubtypes.length === 1) {
    next.subtypeKey = allowedSubtypes[0];
    added.push({ field: "subtypeKey", value: next.subtypeKey });
  }

  const subcategory = subcategoryByCode.get(next.subtypeKey) || null;
  const allowedClasses =
    subcategory?.itemClasses || category?.itemClasses || [];
  const allowedGenres = subcategory?.itemGenres || category?.itemGenres || [];

  [
    ["itemClass", allowedClasses],
    ["itemGenre", allowedGenres],
  ].forEach(([field, allowed]) => {
    if (next[field] && !allowed.includes(next[field])) {
      removed.push({ field, value: next[field] });
      next[field] = "";
    }
    if (!next[field] && allowed.length === 1) {
      next[field] = allowed[0];
      added.push({ field, value: next[field] });
    }
  });

  return { selection: next, removed, added };
};

const metadataSearchText = (code, metadata = {}, searchAliases = {}) =>
  [
    code,
    metadata.name,
    metadata.description,
    metadata.specialMarks,
    metadata.sourceName,
    ...(metadata.typeKeys || []),
    ...(metadata.subtypeKeys || []),
    ...(metadata.itemClasses || []),
    ...(metadata.itemGenres || []),
    ...(metadata.typeKeys || []).flatMap((value) => {
      const entry = iconCategoryByCode.get(value);
      return entry ? [entry.labelPl, entry.labelEn] : [];
    }),
    ...(metadata.subtypeKeys || []).flatMap((value) => {
      const entry = iconSubcategoryByCode.get(value);
      return entry ? [entry.labelPl, entry.labelEn] : [];
    }),
    ...(metadata.itemClasses || []).flatMap((value) => {
      const entry = iconItemClassByCode.get(value);
      return entry ? [entry.labelPl, entry.labelEn] : [];
    }),
    ...(metadata.itemGenres || []).flatMap((value) => {
      const entry = iconItemGenreByCode.get(value);
      return entry ? [entry.labelPl, entry.labelEn] : [];
    }),
    ...[
      ...(metadata.typeKeys || []),
      ...(metadata.subtypeKeys || []),
      ...(metadata.itemClasses || []),
      ...(metadata.itemGenres || []),
    ].flatMap((value) => searchAliases[value] || []),
  ].join(" ");

export const filterIconClasses = (
  iconClasses = [],
  metadataFor,
  filters = {},
) => {
  const locale = filters.locale || "pl";
  const needle = String(filters.query || "").toLocaleLowerCase(locale);
  return iconClasses.filter((code) => {
    const metadata = metadataFor(code) || {};
    if (
      filters.typeKey &&
      !(metadata.typeKeys || []).includes(filters.typeKey)
    ) {
      return false;
    }
    if (
      filters.subtypeKey &&
      !(metadata.subtypeKeys || []).includes(filters.subtypeKey)
    ) {
      return false;
    }
    if (filters.sourceName && metadata.sourceName !== filters.sourceName) {
      return false;
    }
    return (
      !needle ||
      metadataSearchText(code, metadata, filters.searchAliases)
        .toLocaleLowerCase(locale)
        .includes(needle)
    );
  });
};

export const countIconFacet = (
  iconClasses,
  metadataFor,
  filters,
  field,
  code,
) =>
  filterIconClasses(iconClasses, metadataFor, {
    ...filters,
    [field]: code,
  }).length;

export const getCategoryClassCodes = (categoryCode) => [
  ...(CATEGORY_CLASS_CODES[normalizeCode(categoryCode)] || []),
];

export const getSubtypeGenreCodes = (subcategoryCode) => [
  ...(SUBTYPE_GENRE_CODES[normalizeCode(subcategoryCode)] || ["UTILITY"]),
];

export const createIconMetadataPayload = (draft = {}) => ({
  name: String(draft.name || "").trim(),
  description: String(draft.description || "").trim(),
  sourceName: String(draft.sourceName || "").trim(),
  specialMarks: String(draft.specialMarks || "").trim(),
  typeKeys: draft.typeKey ? [normalizeCode(draft.typeKey)] : [],
  subtypeKeys: draft.subtypeKey ? [normalizeCode(draft.subtypeKey)] : [],
  itemClasses: draft.itemClass ? [normalizeCode(draft.itemClass)] : [],
  itemGenres: draft.itemGenre ? [normalizeCode(draft.itemGenre)] : [],
});
