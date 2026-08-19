export const createCoreRuntimePart2Segment2 = (runtime) => {
  const classOptionsMap = {
    ITEM_CLASS: [
      "WEAPON",
      "ARMOR",
      "CLOTH",
      "POWDER",
      "ANIMAL",
      "JEWELLERY",
      "STATIONERY",
      "FORAGE",
      "GADGET",
      "ARMAMENT",
      "CUTLERY",
      "POTION",
      "FOOD",
      "TOOL",
      "ALCHEMY",
      "MAGIC",
    ],
    ITEM_ID: ["13", "17", "21", "44", "45", "88", "93", "112"],
    IMG_CLASS: runtime.legacySelectableIconOptions,
    OWNER_OPT: [runtime.OWNER_CODES.DEFAULT, runtime.OWNER_CODES.TRASH],
    ITEM_PLACE: [
      runtime.t("shop.dataLabels.itemPlace.head"),
      runtime.t("shop.dataLabels.itemPlace.neck"),
      runtime.t("shop.dataLabels.itemPlace.rightHand"),
      runtime.t("shop.dataLabels.itemPlace.leftHand"),
      runtime.t("shop.dataLabels.itemPlace.torso"),
      runtime.t("shop.dataLabels.itemPlace.belt"),
      runtime.t("shop.dataLabels.itemPlace.backpack"),
    ],
  };
  Object.assign(runtime, {
    classOptionsMap,
  });
  runtime.classOptionsMap.SLOT = [...runtime.classOptionsMap.ITEM_PLACE];
  const detailItemClasses = new Set([
    "ANIMAL",
    "ARMAMENT",
    "CLOTH",
    "CUTLERY",
    "FOOD",
    "FORAGE",
    "GADGET",
    "JEWELLERY",
    "POTION",
    "POWDER",
    "STATIONERY",
    "WEAPON",
  ]);
  Object.assign(runtime, {
    detailItemClasses,
  });
  const ownerOptDescriptionKeys = {
    DEFAULT: "modals.ownerOption.optionDescriptions.DEFAULT",
    TRASH: "modals.ownerOption.optionDescriptions.TRASH",
    BG1: "modals.ownerOption.optionDescriptions.BG1",
    BG2: "modals.ownerOption.optionDescriptions.BG2",
    BG3: "modals.ownerOption.optionDescriptions.BG3",
  };
  Object.assign(runtime, {
    ownerOptDescriptionKeys,
  });
  const normalizeTagToken = (value) =>
    String(value || "")
      .toLowerCase()
      .trim()
      .replace(/[^\p{L}\p{N}]+/gu, "_")
      .replace(/^_+|_+$/g, "");
  Object.assign(runtime, {
    normalizeTagToken,
  });
  const SHOP_AUTO_TAG_PREFIXES = Object.freeze({
    type: "typ",
    profile: "profil",
    location: "lok",
    world: "swiat",
    legalStatus: "legal",
    wealthTier: "majetnosc",
    reputation: "reputacja",
    seasonality: "sezon",
  });
  Object.assign(runtime, {
    SHOP_AUTO_TAG_PREFIXES,
  });
  const buildShopAutoTag = (prefix, value) =>
    `${prefix}:${runtime.normalizeTagToken(value)}`;
  Object.assign(runtime, {
    buildShopAutoTag,
  });
  const shopEditorOptionValues = {
    locationType: [
      "metropolia",
      "miasto",
      "miasteczko",
      "wies",
      "jarmark",
      "port",
      "port_morski",
      "port_rzeczny",
      "forteca",
      "przy_trakcie",
      "obrzeza",
      "dzielnica_bogata",
      "dzielnica_biedna",
      "strefa_swiatynna",
      "strefa_cechowa",
    ],
    legalStatus: ["legal", "licensed", "grey", "illegal", "mixed"],
    wealthTier: [
      "nedzny",
      "biedny",
      "standard",
      "bogaty",
      "elitarny",
      "luksusowy",
    ],
    reputation: [
      "fatalna",
      "zla",
      "podejrzana",
      "neutralna",
      "dobra",
      "znakomita",
    ],
    seasonality: [
      "caloroczny",
      "sezonowy",
      "wiosna",
      "lato",
      "jesien",
      "zima",
      "zniwa",
      "jarmark",
      "swieta",
    ],
  };
  Object.assign(runtime, {
    shopEditorOptionValues,
  });
  const toShopEditorOptions = (group) =>
    (runtime.shopEditorOptionValues[group] || []).map((value) => ({
      value,
      label: runtime.t(`shop.shopEditor.options.${group}.${value}`),
    }));
  Object.assign(runtime, {
    toShopEditorOptions,
  });
  return {
    classOptionsMap,
    detailItemClasses,
    ownerOptDescriptionKeys,
    normalizeTagToken,
    SHOP_AUTO_TAG_PREFIXES,
    buildShopAutoTag,
    shopEditorOptionValues,
    toShopEditorOptions,
  };
};
