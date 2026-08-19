import { excludedAdvancedKeys } from "./itemDetailExcludedKeys";
export const buildItemDetailMetaSections = (vm, runtime) => {
  const item = vm.itemDetailItem;
  if (!item) {
    return [];
  }
  const missing = runtime.t("shop.itemDetailDialog.noDataShort");
  const firstValue = (keys) => {
    for (const key of keys) {
      if (!Object.prototype.hasOwnProperty.call(item, key)) {
        continue;
      }
      const value = item[key];
      if (value === null || value === undefined) {
        continue;
      }
      if (typeof value === "string" && !value.trim()) {
        continue;
      }
      return value;
    }
    return "";
  };
  const detailValue = (value) => {
    if (value === null || value === undefined || value === "") {
      return missing;
    }
    const formatted = vm.formatItemDetailValue("", value);
    return formatted || missing;
  };
  const line = (key, label, value, options = {}) => {
    const hasValue =
      value !== null &&
      value !== undefined &&
      value !== "" &&
      (!Array.isArray(value) || value.length > 0);
    return {
      key,
      label,
      value: detailValue(value),
      type: options.type || "text",
      brass: options.brass ?? null,
      currencyCode: options.currencyCode || "wfrp_empire",
      concealed: options.concealed === true,
      hasValue,
    };
  };
  const currentPrice = vm.itemDetailPersonalCostRaw;
  const basePrice = vm.resolveItemBasePrice(item);
  const description = firstValue([
    "PERSONAL_DESC",
    "personalDesc",
    "DESCRIPTION",
    "description",
  ]);
  const fullDescription = firstValue([
    "DETAILS",
    "details",
    "FULL_DESCRIPTION",
    "fullDescription",
    "LONG_DESCRIPTION",
    "longDescription",
  ]);
  const rawClass = item.ITEM_CLASS || item.itemClass || item.class || "";
  const normalizedClass = String(rawClass || "").toUpperCase();
  const itemClass = runtime.detailItemClasses.has(normalizedClass)
    ? normalizedClass
    : rawClass;
  const category = firstValue([
    "ITEM_GENRE",
    "itemGenre",
    "CATEGORY",
    "category",
    "GENRE",
    "genre",
  ]);
  const stats = vm.extractItemDetailStats(item);
  const weight = vm.extractItemDetailWeight(item) || vm.itemDetailChargeText;
  const weaponData =
    item.WEAPON && typeof item.WEAPON === "object"
      ? item.WEAPON
      : item.weapon && typeof item.weapon === "object"
        ? item.weapon
        : {};
  const special =
    firstValue([
      "QUALITIES",
      "qualities",
      "FEATURES",
      "features",
      "SPECIAL_FEATURES",
      "specialFeatures",
      "SPECIAL",
      "special",
    ]) ||
    weaponData.QUALITIES ||
    weaponData.qualities ||
    weaponData.FEATURES ||
    weaponData.features;
  const requirements =
    firstValue([
      "REQUIREMENTS",
      "requirements",
      "REQUIRED",
      "required",
      "REQ",
      "req",
    ]) ||
    weaponData.REQUIREMENTS ||
    weaponData.requirements;
  const tags = firstValue([
    "TAGS",
    "tags",
    "CATEGORY_TAGS",
    "categoryTags",
    "AUTO_TAGS",
    "autoTags",
  ]);
  const owner = firstValue([
    "OWNER",
    "owner",
    "OWNER_OPT",
    "ownerOpt",
    "ownerCode",
  ]);
  const shopOwner = firstValue(["SHOP_OWNER", "shopOwner", "ownerName"]);
  const activeShopOwner =
    shopOwner || vm.activeShopProfile?.ownerName || vm.shopName;
  const section = (key, titleKey, lines, advanced = false) => ({
    key,
    title: runtime.t(titleKey),
    advanced,
    lines: lines.filter((entry) => entry.hasValue || entry.concealed),
  });
  const advancedLines = Object.keys(item)
    .filter((key) => !excludedAdvancedKeys.has(key))
    .sort((left, right) => left.localeCompare(right, "pl"))
    .map((key) =>
      line(`advanced-${key}`, key, item[key], {
        type: "technical",
      }),
    );
  return [
    section("overview", "shop.itemDetailDialog.sections.overview", [
      line(
        "class",
        runtime.t("shop.itemDetailDialog.metaLabels.class"),
        itemClass,
      ),
      line(
        "category",
        runtime.t("shop.itemDetailDialog.metaLabels.category"),
        category,
      ),
      line(
        "quantity",
        runtime.t("shop.itemDetailDialog.metaLabels.quantity"),
        firstValue(["QUANTITY", "quantity"]),
      ),
      line(
        "availability",
        runtime.t("shop.itemDetailDialog.metaLabels.availability"),
        vm.resolveItemDetailAvailability(item),
      ),
      line(
        "source",
        runtime.t("shop.itemDetailDialog.sourcePrefix"),
        vm.itemDetailSourceLabel,
      ),
      line("owner", runtime.t("shop.itemDetailDialog.metaLabels.owner"), owner),
      line(
        "shop-owner",
        runtime.t("shop.itemDetailDialog.metaLabels.shop"),
        activeShopOwner,
      ),
      line(
        "short-description",
        runtime.t("shop.itemDetailDialog.metaLabels.shortDescription"),
        description,
      ),
      line(
        "full-description",
        runtime.t("shop.itemDetailDialog.metaLabels.fullDescription"),
        fullDescription,
      ),
    ]),
    section("commerce", "shop.itemDetailDialog.sections.commerce", [
      line(
        "price",
        runtime.t("shop.itemDetailDialog.metaLabels.price"),
        currentPrice,
        {
          type: "currency",
          brass: currentPrice,
          currencyCode: vm.itemDetailCurrencyCode,
        },
      ),
      line(
        "value",
        runtime.t("shop.itemDetailDialog.metaLabels.value"),
        basePrice,
        {
          type: "currency",
          brass: basePrice,
          currencyCode: vm.itemDetailCurrencyCode,
          concealed: true,
        },
      ),
      line(
        "currency",
        runtime.t("shop.itemDetailDialog.metaLabels.currency"),
        vm.itemDetailCurrencyLabel,
      ),
      line(
        "weight",
        runtime.t("shop.itemDetailDialog.metaLabels.weight"),
        weight,
      ),
    ]),
    section("mechanics", "shop.itemDetailDialog.sections.mechanics", [
      line("stats", runtime.t("shop.itemDetailDialog.metaLabels.stats"), stats),
      line(
        "special",
        runtime.t("shop.itemDetailDialog.metaLabels.special"),
        special,
      ),
      line(
        "requirements",
        runtime.t("shop.itemDetailDialog.metaLabels.requirements"),
        requirements,
      ),
      line("tags", runtime.t("shop.itemDetailDialog.metaLabels.tags"), tags),
    ]),
    section(
      "advanced",
      "shop.itemDetailDialog.sections.advanced",
      advancedLines,
      true,
    ),
  ].filter((entry) => entry.lines.length);
};
