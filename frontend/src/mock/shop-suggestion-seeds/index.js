import byTypePart1 from "./byTypePart1";
import byTypePart2 from "./byTypePart2";
import byClassPart1 from "./byClassPart1";
import byClassPart2 from "./byClassPart2";
import byClassPart3 from "./byClassPart3";
import byClassPart4 from "./byClassPart4";
import byClassPart5 from "./byClassPart5";
import byClassPart6 from "./byClassPart6";
import byClassPart7 from "./byClassPart7";
import byClassPart8 from "./byClassPart8";
import byClassPart9 from "./byClassPart9";
import byGenrePart1 from "./byGenrePart1";
import universal from "./universal";

export const relatedClassMap = {
  ALCHEMY: ["POTION", "TOOL"],
  POTION: ["ALCHEMY", "TOOL"],
  FOOD: ["TOOL", "CUTLERY"],
  TOOL: ["GADGET", "STATIONERY"],
  WEAPON: ["ARMAMENT", "TOOL"],
  ARMOR: ["TOOL", "CLOTH"],
  CLOTH: ["TOOL", "GADGET"],
  STATIONERY: ["TOOL", "GADGET"],
  GADGET: ["TOOL", "STATIONERY"],
  ANIMAL: ["FOOD", "TOOL"],
  POWDER: ["ALCHEMY", "ARMAMENT"],
  ARMAMENT: ["WEAPON", "TOOL"],
  JEWELLERY: ["TOOL", "STATIONERY"],
  MAGIC: ["ALCHEMY", "JEWELLERY"],
  CUTLERY: ["FOOD", "TOOL"],
  FORAGE: ["FOOD", "TOOL"],
  MISC: ["TOOL", "STATIONERY"],
};
export const shopSuggestionSeedLibrary = {
  byType: Object.assign({}, byTypePart1, byTypePart2),
  byClass: Object.assign(
    {},
    byClassPart1,
    byClassPart2,
    byClassPart3,
    byClassPart4,
    byClassPart5,
    byClassPart6,
    byClassPart7,
    byClassPart8,
    byClassPart9,
  ),
  byGenre: Object.assign({}, byGenrePart1),
  universal,
};

export default shopSuggestionSeedLibrary;
