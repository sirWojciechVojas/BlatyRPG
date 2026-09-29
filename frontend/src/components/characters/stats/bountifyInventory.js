const PERSONAL_COLUMNS = 10;
const PERSONAL_ROWS = 3;
const GROUND_COLUMNS = 2;
const GROUND_ROWS = 4;

export const EQUIPMENT_SLOT_ROWS = {
  right: [
    ["head"],
    ["amulet"],
    ["armR1", "armR2"],
    ["belt"],
    ["ringR"],
    ["legR"],
    ["feet"],
  ],
  left: [
    ["cape"],
    ["armor"],
    ["armL1", "armL2"],
    ["hands"],
    ["ringL"],
    ["legL"],
    ["pupil"],
  ],
};

export const HANDY_SLOTS = [
  "quiver1",
  "quiver2",
  "quiver3",
  "handy1",
  "handy2",
  "handy3",
];

const equipmentSlots = Object.values(EQUIPMENT_SLOT_ROWS).flat(2);
const allNamedSlots = new Set([...equipmentSlots, ...HANDY_SLOTS]);
const isHandSlot = (slot) => /^arm[RL]\d+$/u.test(String(slot || ""));

const normalize = (value) =>
  String(value || "")
    .trim()
    .toLowerCase();

const visualTierValues = (value) => {
  if (Array.isArray(value)) return value.flatMap(visualTierValues);
  if (value && typeof value === "object") {
    return [value.code, value.id, value.name, value.label, value.value].flatMap(
      visualTierValues,
    );
  }
  return value === null || value === undefined ? [] : [String(value)];
};

const normalizedVisualTier = (value) =>
  normalize(value)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/gu, "")
    .replace(/ł/gu, "l");

const containsVisualTier = (values, expression) =>
  values.some((value) => expression.test(normalizedVisualTier(value)));

/**
 * The legacy Bountify borders were assigned from an item's rarity or from its
 * magic/rare attributes.  Keep that convention while accepting the current
 * API's uppercase, camelCase and instance metadata variants.
 */
export const itemVisualTier = (item = {}) => {
  const meta = item.INSTANCE_META || item.instanceMeta || {};
  const values = [
    item.RARITY,
    item.rarity,
    item.ITEM_RARITY,
    item.itemRarity,
    item.QUALITY,
    item.quality,
    item.ITEM_QUALITY,
    item.itemQuality,
    item.ATTRIBUTES,
    item.attributes,
    item.ITEM_CLASS,
    item.itemClass,
    meta.RARITY,
    meta.rarity,
    meta.ITEM_RARITY,
    meta.itemRarity,
    meta.QUALITY,
    meta.quality,
    meta.ITEM_QUALITY,
    meta.itemQuality,
    meta.ATTRIBUTES,
    meta.attributes,
    meta.ITEM_CLASS,
    meta.itemClass,
  ].flatMap(visualTierValues);

  if (
    containsVisualTier(values, /(^|[^a-z])(unique|unikat(?:owy)?)([^a-z]|$)/u)
  ) {
    return "unique";
  }
  if (containsVisualTier(values, /(^|[^a-z])(rare|rzadk[a-z]*)([^a-z]|$)/u)) {
    return "rare";
  }
  if (
    containsVisualTier(
      values,
      /(^|[^a-z])(magic|magical|magicz[a-z]*)([^a-z]|$)/u,
    )
  ) {
    return "magic";
  }
  return "";
};

const positionSlots = (prefix, columns, rows) =>
  Array.from({ length: rows * columns }, (_, index) => {
    const column = index % columns;
    const row = Math.floor(index / columns);
    return prefix ? `${prefix}:${column}|${row}` : `${column}|${row}`;
  });

export const inventoryFrameVariant = (index, columns, rows) => {
  const column = index % columns;
  const row = Math.floor(index / columns);

  if (columns === 1 && rows === 1) return "single";
  if (rows === 1) {
    if (column === 0) return "horizontal-left";
    if (column === columns - 1) return "horizontal-right";
    return "horizontal-center";
  }
  if (columns === 1) {
    if (row === 0) return "vertical-top";
    if (row === rows - 1) return "vertical-bottom";
    return "vertical-center";
  }

  const vertical = row === 0 ? "top" : row === rows - 1 ? "bottom" : "middle";
  const horizontal =
    column === 0 ? "left" : column === columns - 1 ? "right" : "center";

  return `${vertical}-${horizontal}`;
};

export const PERSONAL_SLOTS = positionSlots(
  "",
  PERSONAL_COLUMNS,
  PERSONAL_ROWS,
);
export const GROUND_SLOTS = positionSlots(
  "ground",
  GROUND_COLUMNS,
  GROUND_ROWS,
);

export const slotLabelKey = (slot) => {
  if (slot.startsWith("ground:"))
    return "vtt.table.characterStats.inventory.ground";
  if (/^\d+\|\d+$/u.test(slot)) {
    return "vtt.table.characterStats.inventory.backpack";
  }
  return `vtt.table.characterStats.inventory.slots.${slot}`;
};

export const slotKind = (slot) => {
  if (slot.startsWith("ground:")) return "ground";
  if (/^\d+\|\d+$/u.test(slot)) return "personal";
  if (HANDY_SLOTS.includes(slot)) return "handy";
  return "equipment";
};

const normalizedGenre = (item = {}) => {
  const itemClass = normalize(item.ITEM_CLASS || item.itemClass);
  const raw = normalize(
    item.ITEM_GENRE ||
      item.itemGenre ||
      item.INSTANCE_META?.ITEM_GENRE ||
      item.INSTANCE_META?.itemGenre,
  );
  const identity = normalizedVisualTier(
    [item.NAME, item.PERSONAL_PSEU].filter(Boolean).join(" "),
  );

  const isQuiverAmmunition =
    ["ammunition", "ammo", "quiver"].includes(raw) ||
    ["ammunition", "ammo"].includes(itemClass) ||
    /(^|\s)(belty|bolts?|strzaly|arrows?)(\s|$)/u.test(identity);
  if (isQuiverAmmunition) return "quiver";

  // Current shop templates describe weapons as WEAPON/MELEE or
  // WEAPON/RANGED, while imported Bountify items use the older `arms`
  // genre.  ITEM_CLASS is authoritative here so a malformed genre can never
  // make a weapon compatible with an armour slot.
  if (itemClass === "weapon" && raw !== "handy" && raw !== "quiver") {
    return "arm";
  }

  if (itemClass === "armor") {
    if (["head", "helmet"].includes(raw)) return "head";
    if (["shield", "buckler"].includes(raw)) return "arm";
    if (["hands", "feet", "cape"].includes(raw)) return raw;
    return "armor";
  }

  if (
    [
      "arm",
      "arms",
      "weapon",
      "weapons",
      "melee",
      "ranged",
      "shield",
      "buckler",
    ].includes(raw)
  ) {
    return "arm";
  }
  if (["armor", "armour", "body", "torso", "chest"].includes(raw)) {
    return "armor";
  }
  if (["legs", "leg"].includes(raw)) return "leg";
  if (
    [
      "healing",
      "buffs",
      "resistance",
      "toxins",
      "meals",
      "bakery",
      "preserves",
      "drinks",
      "spices_herbs",
      "potion",
      "utility",
    ].includes(raw)
  ) {
    return "handy";
  }
  return raw;
};

const slotGenre = (slot) => {
  if (isHandSlot(slot)) return "arm";
  if (slot.startsWith("ring")) return "ring";
  if (slot.startsWith("leg")) return "leg";
  if (slot.startsWith("quiver")) return "quiver";
  if (slot.startsWith("handy")) return "handy";
  return slot.replace(/[RL]\d?$/u, "");
};

export const isTwoHanded = (item = {}) => {
  if (!item) return false;
  const values = [
    item.HANDED,
    item.handed,
    item?.WEAPON?.HANDED,
    item?.WEAPON?.handed,
    item?.ITEM_ID?.HANDED,
    item?.INSTANCE_META?.HANDED,
    item?.INSTANCE_META?.WEAPON?.HANDED,
  ];
  const normalizedValues = values.map(normalize).filter(Boolean);
  if (
    normalizedValues.some((value) =>
      [
        "1",
        "1h",
        "one",
        "one-handed",
        "jednoreczna",
        "jednoręczna",
        "bron jednoreczna",
        "broń jednoręczna",
      ].includes(value),
    )
  ) {
    return false;
  }
  if (
    normalizedValues.some((value) =>
      [
        "2",
        "2h",
        "two",
        "two-handed",
        "dwureczna",
        "dwuręczna",
        "bron dwureczna",
        "broń dwuręczna",
      ].includes(value),
    )
  ) {
    return true;
  }

  const identity = normalizedVisualTier(
    [item.NAME, item.PERSONAL_PSEU, item?.WEAPON?.NAME, item?.WEAPON?.TYPE]
      .filter(Boolean)
      .join(" "),
  );
  const isCrossbow = /(^|\s)(kusza|crossbow)(\s|$)/u.test(identity);
  const isPistolCrossbow = /(kusza pistoletowa|pistol crossbow)/u.test(
    identity,
  );
  return isCrossbow && !isPistolCrossbow;
};

export const itemId = (item = {}) => Number(item.ID || item.id || 0);

const sortItems = (items) =>
  [...items].sort((left, right) => itemId(left) - itemId(right));

export const itemSlot = (item = {}) =>
  String(item.SLOT || item.ITEM_PLACE || "").trim();

export const createInventoryLayout = (items = []) => {
  const slots = [
    ...equipmentSlots,
    ...HANDY_SLOTS,
    ...PERSONAL_SLOTS,
    ...GROUND_SLOTS,
  ];
  const bySlot = new Map(slots.map((slot) => [slot, null]));
  const overflow = [];

  sortItems(items).forEach((item) => {
    const slot = itemSlot(item);
    const knownSlot = bySlot.has(slot);
    if (knownSlot && !bySlot.get(slot)) {
      bySlot.set(slot, item);
    } else {
      overflow.push(item);
    }
  });

  const openSlots = [...PERSONAL_SLOTS, ...GROUND_SLOTS].filter(
    (slot) => !bySlot.get(slot),
  );
  overflow.forEach((item, index) => {
    if (openSlots[index]) bySlot.set(openSlots[index], item);
  });

  return {
    right: EQUIPMENT_SLOT_ROWS.right.flat().map((slot) => ({
      slot,
      item: bySlot.get(slot),
      frame: "single",
    })),
    left: EQUIPMENT_SLOT_ROWS.left.flat().map((slot) => ({
      slot,
      item: bySlot.get(slot),
      frame: "single",
    })),
    handy: HANDY_SLOTS.map((slot) => ({
      slot,
      item: bySlot.get(slot),
      frame: "single",
    })),
    personal: PERSONAL_SLOTS.map((slot, index) => ({
      slot,
      item: bySlot.get(slot),
      frame: inventoryFrameVariant(index, PERSONAL_COLUMNS, PERSONAL_ROWS),
    })),
    ground: GROUND_SLOTS.map((slot, index) => ({
      slot,
      item: bySlot.get(slot),
      frame: inventoryFrameVariant(index, GROUND_COLUMNS, GROUND_ROWS),
    })),
    overflow: overflow.slice(openSlots.length),
  };
};

const occupiedItems = (cells = []) =>
  cells.map((cell) => cell.item).filter(Boolean);

export const inventoryEncumbranceGroups = (layout = {}) => ({
  equipment: occupiedItems([
    ...(layout.right || []),
    ...(layout.left || []),
    ...(layout.handy || []),
  ]),
  backpack: occupiedItems(layout.personal),
});

const opposingHandSlots = (slot) => {
  const match = /^arm([RL])(\d+)$/u.exec(slot);
  if (!match) return [];
  const [, side, index] = match;
  return [`arm${side === "R" ? "L" : "R"}${index}`];
};

const twoHandedBlockingItem = (slot, itemsBySlot = {}) =>
  isHandSlot(slot)
    ? opposingHandSlots(slot)
        .map((opposingSlot) => itemsBySlot[opposingSlot])
        .find(isTwoHanded) || null
    : null;

export const isSlotBlockedByTwoHanded = (slot, itemsBySlot = {}) =>
  Boolean(twoHandedBlockingItem(slot, itemsBySlot));

export const canPlaceItem = (item, targetSlot, itemsBySlot = {}) => {
  if (!item || !targetSlot) return { ok: false, code: "missing_item" };
  const kind = slotKind(targetSlot);
  if (["personal", "ground"].includes(kind)) return { ok: true };

  const targetGenre = slotGenre(targetSlot);
  if (normalizedGenre(item) !== targetGenre) {
    return { ok: false, code: "wrong_slot" };
  }

  if (isHandSlot(targetSlot)) {
    if (isSlotBlockedByTwoHanded(targetSlot, itemsBySlot)) {
      return { ok: false, code: "two_handed_block" };
    }
    const opposingItem = opposingHandSlots(targetSlot)
      .map((slot) => itemsBySlot[slot])
      .find(Boolean);
    if (isTwoHanded(item) && opposingItem) {
      return { ok: false, code: "two_handed_block" };
    }
  }
  return { ok: true };
};

export const createMovePlan = (layout, sourceSlot, targetSlot) => {
  const allCells = [
    ...layout.right,
    ...layout.left,
    ...layout.handy,
    ...layout.personal,
    ...layout.ground,
  ];
  const bySlot = Object.fromEntries(
    allCells.map((cell) => [cell.slot, cell.item]),
  );
  const source = bySlot[sourceSlot];
  const target = bySlot[targetSlot];
  const nextItemsBySlot = {
    ...bySlot,
    [sourceSlot]: target || null,
    [targetSlot]: source,
  };
  const sourceCheck = canPlaceItem(source, targetSlot, nextItemsBySlot);
  if (!sourceCheck.ok) return sourceCheck;
  if (target) {
    const targetCheck = canPlaceItem(target, sourceSlot, nextItemsBySlot);
    if (!targetCheck.ok) return targetCheck;
  }
  return {
    ok: true,
    moves: [
      { item: source, slot: targetSlot },
      ...(target ? [{ item: target, slot: sourceSlot }] : []),
    ],
  };
};

const layoutCells = (layout = {}) => [
  ...(layout.right || []),
  ...(layout.left || []),
  ...(layout.handy || []),
  ...(layout.personal || []),
  ...(layout.ground || []),
];

const targetSlotsForItem = (item) => {
  const genre = normalizedGenre(item);
  if (genre === "arm") return ["armR1", "armR2", "armL1", "armL2"];
  if (genre === "quiver") return ["quiver1", "quiver2", "quiver3"];
  if (genre === "handy") return ["handy1", "handy2", "handy3"];
  if (genre === "leg") return ["legR", "legL"];
  if (genre === "ring") return ["ringR", "ringL"];
  return equipmentSlots.filter((slot) => slotGenre(slot) === genre);
};

const firstValidMovePlan = (layout, sourceSlot, targetSlots) => {
  const bySlot = Object.fromEntries(
    layoutCells(layout).map((cell) => [cell.slot, cell.item]),
  );
  const candidates = targetSlots
    .filter((slot) => slot !== sourceSlot)
    .sort(
      (left, right) =>
        Number(Boolean(bySlot[left])) - Number(Boolean(bySlot[right])),
    );

  for (const targetSlot of candidates) {
    const plan = createMovePlan(layout, sourceSlot, targetSlot);
    if (plan.ok) return { ...plan, targetSlot };
  }
  return null;
};

/**
 * Resolves the one-click action exposed by the inventory details dialog and by
 * a deliberate double-click. The result always uses the regular move planner,
 * so automatic actions obey the same slot and two-handed-weapon rules as drag
 * and drop.
 */
export const createItemPrimaryAction = (layout, sourceSlot) => {
  const sourceCell = layoutCells(layout).find(
    (cell) => cell.slot === sourceSlot,
  );
  if (!sourceCell?.item) {
    return { ok: false, code: "missing_item", action: "unavailable" };
  }

  const sourceKind = slotKind(sourceSlot);
  if (["equipment", "handy"].includes(sourceKind)) {
    const plan = firstValidMovePlan(layout, sourceSlot, PERSONAL_SLOTS);
    return plan
      ? { ...plan, action: "store" }
      : { ok: false, code: "backpack_full", action: "store" };
  }

  const equipmentTargets = targetSlotsForItem(sourceCell.item);
  if (equipmentTargets.length) {
    const plan = firstValidMovePlan(layout, sourceSlot, equipmentTargets);
    if (plan) {
      const genre = normalizedGenre(sourceCell.item);
      const action =
        genre === "quiver" ? "quiver" : genre === "handy" ? "ready" : "equip";
      return { ...plan, action };
    }
    const itemsBySlot = Object.fromEntries(
      layoutCells(layout).map((cell) => [cell.slot, cell.item]),
    );
    const blockedItem = equipmentTargets
      .map((slot) => twoHandedBlockingItem(slot, itemsBySlot))
      .find(Boolean);
    return {
      ok: false,
      code: blockedItem
        ? "inactive_hand_slot"
        : isTwoHanded(sourceCell.item)
          ? "two_handed_block"
          : "no_available_slot",
      blockedItem: blockedItem || null,
      action:
        normalizedGenre(sourceCell.item) === "quiver"
          ? "quiver"
          : normalizedGenre(sourceCell.item) === "handy"
            ? "ready"
            : "equip",
    };
  }

  if (sourceKind === "ground") {
    const plan = firstValidMovePlan(layout, sourceSlot, PERSONAL_SLOTS);
    return plan
      ? { ...plan, action: "pickup" }
      : { ok: false, code: "backpack_full", action: "pickup" };
  }

  return { ok: false, code: "no_primary_action", action: "unavailable" };
};

export const createInventoryPlacementPreview = (layout, sourceSlot = "") => {
  const allCells = layoutCells(layout);
  const itemsBySlot = Object.fromEntries(
    allCells.map((cell) => [cell.slot, cell.item]),
  );
  const sourceItem = itemsBySlot[sourceSlot] || null;
  const active = Boolean(sourceItem);
  const placementItemsBySlot = active
    ? { ...itemsBySlot, [sourceSlot]: null }
    : itemsBySlot;

  return {
    active,
    sourceItem,
    slots: Object.fromEntries(
      allCells.map(({ slot }) => {
        const source = active && slot === sourceSlot;
        const equipmentSlot = ["equipment", "handy"].includes(slotKind(slot));
        const blockedItem = twoHandedBlockingItem(slot, placementItemsBySlot);
        const blockedByTwoHanded = Boolean(blockedItem);
        const validMove =
          active && !source && createMovePlan(layout, sourceSlot, slot).ok;
        const target =
          active &&
          equipmentSlot &&
          !source &&
          !blockedByTwoHanded &&
          validMove;
        const disabled = blockedByTwoHanded;
        return [
          slot,
          {
            source,
            target,
            disabled,
            dimmed:
              active &&
              equipmentSlot &&
              !source &&
              !target &&
              !blockedByTwoHanded,
            blockedByTwoHanded,
            blockedItem,
          },
        ];
      }),
    ),
  };
};

export const isNamedInventorySlot = (slot) => allNamedSlots.has(slot);
