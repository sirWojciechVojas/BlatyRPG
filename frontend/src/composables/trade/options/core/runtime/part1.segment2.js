export const createCoreRuntimePart1Segment2 = (runtime) => {
  const weaponFeatureCatalog = Object.freeze([
    {
      id: "1",
      nameKey: "modals.weaponStats.features.heavy.name",
      descriptionKey: "modals.weaponStats.features.heavy.description",
      mechanicsKey: "modals.weaponStats.features.heavy.mechanics",
      aliases: ["ciezki", "heavy"],
    },
    {
      id: "2",
      nameKey: "modals.weaponStats.features.crushing.name",
      descriptionKey: "modals.weaponStats.features.crushing.description",
      mechanicsKey: "modals.weaponStats.features.crushing.mechanics",
      aliases: ["druzgocacy", "crushing"],
    },
    {
      id: "5",
      nameKey: "modals.weaponStats.features.stunning.name",
      descriptionKey: "modals.weaponStats.features.stunning.description",
      mechanicsKey: "modals.weaponStats.features.stunning.mechanics",
      aliases: ["ogluszajacy", "stunning"],
    },
    {
      id: "8",
      nameKey: "modals.weaponStats.features.fast.name",
      descriptionKey: "modals.weaponStats.features.fast.description",
      mechanicsKey: "modals.weaponStats.features.fast.mechanics",
      aliases: ["szybki", "fast"],
    },
    {
      id: "11",
      nameKey: "modals.weaponStats.features.precise.name",
      descriptionKey: "modals.weaponStats.features.precise.description",
      mechanicsKey: "modals.weaponStats.features.precise.mechanics",
      aliases: ["precyzyjny", "precise"],
    },
    {
      id: "14",
      nameKey: "modals.weaponStats.features.balanced.name",
      descriptionKey: "modals.weaponStats.features.balanced.description",
      mechanicsKey: "modals.weaponStats.features.balanced.mechanics",
      aliases: ["wywazona", "balanced"],
    },
  ]);
  Object.assign(runtime, {
    weaponFeatureCatalog,
  });
  const weaponFeatureById = runtime.weaponFeatureCatalog.reduce(
    (acc, feature) => {
      acc[String(feature.id)] = feature;
      return acc;
    },
    {},
  );
  Object.assign(runtime, {
    weaponFeatureById,
  });
  const normalizeFeatureToken = (value) =>
    String(value || "")
      .toLowerCase()
      .trim()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "");
  Object.assign(runtime, {
    normalizeFeatureToken,
  });
  const weaponFeatureIdByToken = runtime.weaponFeatureCatalog.reduce(
    (acc, feature) => {
      const tokens = [feature.nameKey, ...(feature.aliases || [])];
      tokens.forEach((token) => {
        const normalized = runtime.normalizeFeatureToken(token);
        if (normalized) {
          acc[normalized] = String(feature.id);
        }
      });
      return acc;
    },
    {},
  );
  Object.assign(runtime, {
    weaponFeatureIdByToken,
  });
  const uniqueWeaponFeatureIds = (values = []) =>
    Array.from(
      new Set(
        (Array.isArray(values) ? values : [])
          .map((entry) => String(entry || "").trim())
          .filter((entry) => runtime.weaponFeatureById[entry]),
      ),
    );
  Object.assign(runtime, {
    uniqueWeaponFeatureIds,
  });
  const parseWeaponFeatureIds = (value) =>
    runtime.uniqueWeaponFeatureIds(
      String(value || "")
        .split(/[;,]/)
        .map((entry) => entry.trim())
        .filter(Boolean),
    );
  Object.assign(runtime, {
    parseWeaponFeatureIds,
  });
  const weaponFeatureIdsFromQualities = (value) =>
    runtime.uniqueWeaponFeatureIds(
      String(value || "")
        .split(",")
        .map(
          (entry) =>
            runtime.weaponFeatureIdByToken[
              runtime.normalizeFeatureToken(entry)
            ],
        )
        .filter(Boolean),
    );
  Object.assign(runtime, {
    weaponFeatureIdsFromQualities,
  });
  const weaponQualityNamesFromFeatureIds = (featureIds = []) =>
    featureIds
      .map((featureId) => {
        const feature = runtime.weaponFeatureById[String(featureId)];
        return feature ? runtime.t(feature.nameKey) : "";
      })
      .filter(Boolean);
  Object.assign(runtime, {
    weaponQualityNamesFromFeatureIds,
  });
  const syncWeaponDraftFeatureFields = (draft = {}) => {
    const explicitIds = runtime.parseWeaponFeatureIds(draft.FEATURES_ID);
    const qualityIds = runtime.weaponFeatureIdsFromQualities(draft.QUALITIES);
    const featureIds = explicitIds.length ? explicitIds : qualityIds;
    return {
      ...draft,
      FEATURES_ID: featureIds.join(";"),
      QUALITIES: runtime
        .weaponQualityNamesFromFeatureIds(featureIds)
        .join(", "),
    };
  };
  Object.assign(runtime, {
    syncWeaponDraftFeatureFields,
  });
  const weaponStatFieldKeys = [
    "ITEM_ID",
    "NAME",
    "TYPE",
    "HANDED",
    "CATEGORY",
    "DICE",
    "MODIFIER",
    "DAMAGE",
    "RANGE",
    "RELOAD",
    "QUALITIES",
    "LOAD",
    "FEATURES_ID",
    "OCCU_CHANCE",
  ];
  Object.assign(runtime, {
    weaponStatFieldKeys,
  });
  const emptyWeaponStatsDraft = () =>
    runtime.weaponStatFieldKeys.reduce((acc, key) => {
      acc[key] = "";
      return acc;
    }, {});
  Object.assign(runtime, {
    emptyWeaponStatsDraft,
  });
  const normalizeWeaponStatValue = (value) => String(value ?? "").trim();
  Object.assign(runtime, {
    normalizeWeaponStatValue,
  });
  return {
    weaponFeatureCatalog,
    weaponFeatureById,
    normalizeFeatureToken,
    weaponFeatureIdByToken,
    uniqueWeaponFeatureIds,
    parseWeaponFeatureIds,
    weaponFeatureIdsFromQualities,
    weaponQualityNamesFromFeatureIds,
    syncWeaponDraftFeatureFields,
    weaponStatFieldKeys,
    emptyWeaponStatsDraft,
    normalizeWeaponStatValue,
  };
};
