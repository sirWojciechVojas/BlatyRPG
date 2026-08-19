export const createCoreRuntimePart1Segment1 = (runtime) => {
  const inventoryIconContext = require.context(
    "@/assets/app-ui/img/inventory",
    false,
    /^\.\/v\d{4}\.png$/,
  );
  Object.assign(runtime, {
    inventoryIconContext,
  });
  const iconMap = runtime.inventoryIconContext.keys().reduce((map, key) => {
    const iconClass = key.replace("./", "").replace(".png", "").toLowerCase();
    map[iconClass] = runtime.inventoryIconContext(key);
    return map;
  }, {});
  Object.assign(runtime, {
    iconMap,
  });
  const legacyIconClassPattern = /^v\d{4}$/i;
  Object.assign(runtime, {
    legacyIconClassPattern,
  });
  const legacyIconColumns = 20;
  Object.assign(runtime, {
    legacyIconColumns,
  });
  const legacyIconSize = 42;
  Object.assign(runtime, {
    legacyIconSize,
  });
  const tradeIconMinSize = 24;
  Object.assign(runtime, {
    tradeIconMinSize,
  });
  const tradeIconMaxSize = 64;
  Object.assign(runtime, {
    tradeIconMaxSize,
  });
  const legacyIconMax = 1375;
  Object.assign(runtime, {
    legacyIconMax,
  });
  const iconCollectionStorageKey = "trade-icon-collection-v1";
  Object.assign(runtime, {
    iconCollectionStorageKey,
  });
  const legacySpriteIconOptions = Array.from(
    {
      length: runtime.legacyIconMax,
    },
    (_, i) => `v${String(i + 1).padStart(4, "0")}`,
  );
  Object.assign(runtime, {
    legacySpriteIconOptions,
  });
  const legacyIconStyleId = "legacy-inventory-icon-rules";
  Object.assign(runtime, {
    legacyIconStyleId,
  });
  const legacySelectableIconOptions = [...runtime.legacySpriteIconOptions];
  Object.assign(runtime, {
    legacySelectableIconOptions,
  });
  const normalizeSpriteIconClass = (rawClass) =>
    runtime.normalizeLegacyIconClassUtil(rawClass, {
      pattern: runtime.legacyIconClassPattern,
      max: runtime.legacyIconMax,
      namedPositions: {},
      fallback: "",
    });
  Object.assign(runtime, {
    normalizeSpriteIconClass,
  });
  const readIconClassRemap = () => {
    if (typeof window === "undefined") {
      return {};
    }
    try {
      const raw = window.localStorage.getItem(runtime.iconCollectionStorageKey);
      if (!raw) {
        return {};
      }
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") {
        return {};
      }
      const remap = {};
      Object.entries(parsed.remap || {}).forEach(
        ([displayClass, sourceClass]) => {
          const normalizedDisplay =
            runtime.normalizeSpriteIconClass(displayClass);
          const normalizedSource =
            runtime.normalizeSpriteIconClass(sourceClass);
          if (normalizedDisplay && normalizedSource) {
            remap[normalizedDisplay] = normalizedSource;
          }
        },
      );
      return remap;
    } catch (error) {
      return {};
    }
  };
  Object.assign(runtime, {
    readIconClassRemap,
  });
  const clampTradeIconSize = (value) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
      return runtime.legacyIconSize;
    }
    return Math.min(
      runtime.tradeIconMaxSize,
      Math.max(runtime.tradeIconMinSize, Math.round(parsed)),
    );
  };
  Object.assign(runtime, {
    clampTradeIconSize,
  });
  const formatLegacyPosition = (position) => {
    if (!position) {
      return null;
    }
    return `calc(${position.x} * var(--trade-icon-size)) calc(${position.y} * var(--trade-icon-size))`;
  };
  Object.assign(runtime, {
    formatLegacyPosition,
  });
  const legacyPositionForClass = (iconClass) => {
    if (!iconClass) {
      return null;
    }
    const normalizedIconClass = String(iconClass).toLowerCase();
    if (runtime.legacyIconClassPattern.test(normalizedIconClass)) {
      const numeric = Number(normalizedIconClass.slice(1));
      if (
        Number.isFinite(numeric) &&
        numeric >= 1 &&
        numeric <= runtime.legacyIconMax
      ) {
        const index = numeric - 1;
        return {
          x: -(index % runtime.legacyIconColumns),
          y: -Math.floor(index / runtime.legacyIconColumns),
        };
      }
    }
    return null;
  };
  Object.assign(runtime, {
    legacyPositionForClass,
  });
  const buildLegacyIconCss = () => {
    const lines = [];
    for (let id = 1; id <= runtime.legacyIconMax; id += 1) {
      const iconClass = `v${String(id).padStart(4, "0")}`;
      const position = runtime.formatLegacyPosition(
        runtime.legacyPositionForClass(iconClass),
      );
      if (!position) {
        continue;
      }
      lines.push(
        `#trading .legacy-inventory-icon.${iconClass}{background-position:${position};}`,
      );
    }
    return lines.join("");
  };
  Object.assign(runtime, {
    buildLegacyIconCss,
  });
  const legacyIconCss = runtime.buildLegacyIconCss();
  Object.assign(runtime, {
    legacyIconCss,
  });
  const TRASH_OWNER_GENERAL = runtime.OWNER_CODES.TRASH;
  Object.assign(runtime, {
    TRASH_OWNER_GENERAL,
  });
  const PLAYER_TRASH_SLOT_CAPACITY = 16;
  Object.assign(runtime, {
    PLAYER_TRASH_SLOT_CAPACITY,
  });
  const GENERAL_TRASH_SLOT_CAPACITY = null;
  Object.assign(runtime, {
    GENERAL_TRASH_SLOT_CAPACITY,
  });
  const t = (key, values = {}) => runtime.i18n.global.t(key, values);
  Object.assign(runtime, {
    t,
  });
  const ensureLegacyIconStyles = () => {
    if (typeof document === "undefined") {
      return;
    }
    if (document.getElementById(runtime.legacyIconStyleId)) {
      return;
    }
    const style = document.createElement("style");
    style.id = runtime.legacyIconStyleId;
    style.type = "text/css";
    style.textContent = runtime.legacyIconCss;
    document.head.appendChild(style);
  };
  Object.assign(runtime, {
    ensureLegacyIconStyles,
  });
  return {
    inventoryIconContext,
    iconMap,
    legacyIconClassPattern,
    legacyIconColumns,
    legacyIconSize,
    tradeIconMinSize,
    tradeIconMaxSize,
    legacyIconMax,
    iconCollectionStorageKey,
    legacySpriteIconOptions,
    legacyIconStyleId,
    legacySelectableIconOptions,
    normalizeSpriteIconClass,
    readIconClassRemap,
    clampTradeIconSize,
    formatLegacyPosition,
    legacyPositionForClass,
    buildLegacyIconCss,
    legacyIconCss,
    TRASH_OWNER_GENERAL,
    PLAYER_TRASH_SLOT_CAPACITY,
    GENERAL_TRASH_SLOT_CAPACITY,
    t,
    ensureLegacyIconStyles,
  };
};
