import { inventoryIconMetadataMap } from "@/data/trade/inventoryIconMetadata";
import { ref } from "vue";

let overrides = Object.freeze({});
export const iconMetadataRevision = ref(0);

const normalizeList = (value) => [
  ...new Set(
    (Array.isArray(value) ? value : [])
      .map((entry) =>
        String(entry || "")
          .trim()
          .toUpperCase(),
      )
      .filter(Boolean),
  ),
];

export const normalizeIconMetadata = (iconClass, value = {}) => ({
  ...(inventoryIconMetadataMap[iconClass] || {}),
  ...value,
  iconClass,
  name: String(
    value.name || inventoryIconMetadataMap[iconClass]?.name || iconClass,
  ).trim(),
  sourceName: String(
    value.sourceName ?? inventoryIconMetadataMap[iconClass]?.sourceName ?? "",
  ).trim(),
  imageUrl: String(value.imageUrl || "").trim(),
  imageUrlSmall: String(value.imageUrlSmall || value.imageUrl || "").trim(),
  imageUrlLarge: String(value.imageUrlLarge || value.imageUrl || "").trim(),
  description: String(
    value.description ?? inventoryIconMetadataMap[iconClass]?.description ?? "",
  ).trim(),
  specialMarks: String(
    value.specialMarks ??
      inventoryIconMetadataMap[iconClass]?.specialMarks ??
      "",
  ).trim(),
  typeKeys: normalizeList(
    value.typeKeys ?? inventoryIconMetadataMap[iconClass]?.typeKeys,
  ),
  subtypeKeys: normalizeList(
    value.subtypeKeys ?? inventoryIconMetadataMap[iconClass]?.subtypeKeys,
  ),
  itemClasses: normalizeList(
    value.itemClasses ?? inventoryIconMetadataMap[iconClass]?.itemClasses,
  ),
  itemGenres: normalizeList(
    value.itemGenres ?? inventoryIconMetadataMap[iconClass]?.itemGenres,
  ),
});

export const setIconMetadataOverrides = (records = []) => {
  const next = {};
  (Array.isArray(records) ? records : Object.values(records || {})).forEach(
    (record) => {
      const iconClass = String(record?.iconClass || "").toLowerCase();
      if (/^v\d{4}$/.test(iconClass))
        next[iconClass] = normalizeIconMetadata(iconClass, record);
    },
  );
  overrides = Object.freeze(next);
  iconMetadataRevision.value += 1;
  return overrides;
};

export const setIconMetadataOverride = (record = {}) => {
  const iconClass = String(record.iconClass || "").toLowerCase();
  if (!/^v\d{4}$/.test(iconClass)) return;
  overrides = Object.freeze({
    ...overrides,
    [iconClass]: normalizeIconMetadata(iconClass, record),
  });
  iconMetadataRevision.value += 1;
};

export const removeIconMetadataOverride = (iconClass) => {
  const next = { ...overrides };
  delete next[String(iconClass || "").toLowerCase()];
  overrides = Object.freeze(next);
  iconMetadataRevision.value += 1;
};

export const getIconMetadata = (iconClass) => {
  const code = String(iconClass || "").toLowerCase();
  return overrides[code] || inventoryIconMetadataMap[code] || null;
};

export const getIconMetadataOverrides = () => overrides;

export const getAvailableIconClasses = () =>
  [
    ...new Set([
      ...Object.keys(inventoryIconMetadataMap),
      ...Object.keys(overrides),
    ]),
  ].sort((left, right) => Number(left.slice(1)) - Number(right.slice(1)));
