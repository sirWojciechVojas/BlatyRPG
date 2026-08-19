import { iconCategories, iconSubcategories } from "@/data/trade/iconTaxonomy";

export const createRuntimePart1 = () => {
  const TYPE_LABELS_PL = Object.freeze(
    Object.fromEntries(
      iconCategories.map((entry) => [entry.code, entry.labelPl]),
    ),
  );
  const SUBTYPE_LABELS_PL = Object.freeze(
    Object.fromEntries(
      iconSubcategories.map((entry) => [entry.code, entry.labelPl]),
    ),
  );
  const STOP_TOKENS = new Set([
    "icon",
    "poe",
    "poe2",
    "px",
    "lax",
    "laxi",
    "backer",
    "tex",
    "beta",
    "gen",
    "u",
    "cl",
    "01",
    "02",
    "03",
    "04",
    "05",
    "06",
    "07",
    "08",
    "09",
    "10",
    "11",
    "12",
    "13",
    "256",
  ]);
  return { TYPE_LABELS_PL, SUBTYPE_LABELS_PL, STOP_TOKENS };
};
