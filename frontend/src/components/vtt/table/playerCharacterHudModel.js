const BLOCKED_PATH_SEGMENTS = new Set([
  "__proto__",
  "prototype",
  "constructor",
]);

const HEALTH_FIELDS = Object.freeze([
  { current: "health.current", maximum: ["health.max", "health.maximum"] },
  {
    current: "hitPoints.current",
    maximum: ["hitPoints.max", "hitPoints.maximum"],
  },
  { current: "hp.current", maximum: ["hp.max", "hp.maximum"] },
  {
    current: "resources.health.current",
    maximum: ["resources.health.max", "resources.health.maximum"],
  },
  {
    current: "resources.hp.current",
    maximum: ["resources.hp.max", "resources.hp.maximum"],
  },
  {
    current: "attributes.health.current",
    maximum: ["attributes.health.max", "attributes.health.maximum"],
  },
  {
    current: "attributes.hp.current",
    maximum: ["attributes.hp.max", "attributes.hp.maximum"],
  },
  {
    current: "attributes.actual.hp",
    maximum: [
      "attributes.actual.hp_max",
      "attributes.maximum.hp",
      "attributes.max.hp",
      "attributes.base.hp",
    ],
  },
  {
    current: "wounds.current",
    maximum: ["wounds.max", "wounds.maximum"],
  },
  {
    current: "attributes.wounds.current",
    maximum: ["attributes.wounds.max", "attributes.wounds.maximum"],
  },
  {
    current: "attributes.actual.wounds",
    maximum: [
      "attributes.actual.max_wounds",
      "attributes.maximum.wounds",
      "attributes.max.wounds",
    ],
  },
  { current: "currentWounds", maximum: ["maxWounds"] },
  { current: "current_wounds", maximum: ["max_wounds"] },
]);

const EXPERIENCE_FIELDS = Object.freeze([
  {
    available: "experience.current",
    earned: ["experience.total"],
    required: ["experience.required", "experience.maximum", "experience.max"],
  },
  {
    available: "experience.available",
    earned: ["experience.total"],
    required: ["experience.required", "experience.maximum", "experience.max"],
  },
  {
    available: "attributes.exp.current",
    earned: ["attributes.exp.total"],
    required: [
      "attributes.exp.required",
      "attributes.exp.maximum",
      "attributes.exp.max",
    ],
  },
  {
    available: "experience_current",
    earned: ["experience_total"],
    required: ["experience_required", "experience_max"],
  },
  {
    available: "experienceCurrent",
    earned: ["experienceTotal"],
    required: ["experienceRequired", "experienceMax"],
  },
]);

const COMPLEX_SPENT_FIELDS = Object.freeze([
  "experience.complex.spent",
  "experience.spentAll",
  "experience.spent_all",
  "experience.spent",
  "attributes.exp.spent",
  "experience_spent",
]);

const MINIMUM_SPENT_FIELDS = Object.freeze([
  "experience.minimum.spent",
  "experience.minimal.spent",
  "experience.spentWithoutAlternatives",
  "experience.spent_without_alternatives",
  "experience.spentRequired",
  "attributes.exp.spentWithoutAlternatives",
  "experience_spent_without_alternatives",
]);

const COMPLEX_REQUIRED_FIELDS = Object.freeze([
  "experience.complex.required",
  "experience.complex.maximum",
]);

const MINIMUM_REQUIRED_FIELDS = Object.freeze([
  "experience.minimum.required",
  "experience.minimal.required",
  "experience.minimum.maximum",
]);

const idKey = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const numeric = Number(value);
  return Number.isFinite(numeric) ? `n:${numeric}` : `s:${String(value)}`;
};

const idsMatch = (left, right) => {
  const leftKey = idKey(left);
  return leftKey !== null && leftKey === idKey(right);
};

const compareCharacters = (left, right) => {
  const leftNumber = Number(left?.id);
  const rightNumber = Number(right?.id);
  if (Number.isFinite(leftNumber) && Number.isFinite(rightNumber)) {
    return leftNumber - rightNumber;
  }
  const idOrder = String(left?.id ?? "").localeCompare(String(right?.id ?? ""));
  return (
    idOrder || String(left?.name || "").localeCompare(String(right?.name || ""))
  );
};

const safePath = (value) => {
  const normalized = String(value || "").trim();
  const segments = normalized.split(".");
  return normalized &&
    /^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){0,7}$/u.test(normalized) &&
    !segments.some((segment) =>
      BLOCKED_PATH_SEGMENTS.has(segment.toLocaleLowerCase()),
    )
    ? normalized
    : "";
};

const finiteNumber = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
};

export const readCharacterNumber = (data, requestedPath) => {
  const path = safePath(requestedPath);
  if (!path) return { exists: false, path: "", value: null };
  let cursor = data;
  for (const segment of path.split(".")) {
    if (
      !cursor ||
      typeof cursor !== "object" ||
      Array.isArray(cursor) ||
      !Object.prototype.hasOwnProperty.call(cursor, segment)
    ) {
      return { exists: false, path, value: null };
    }
    cursor = cursor[segment];
  }
  const value = finiteNumber(cursor);
  return { exists: value !== null, path, value };
};

const firstNumber = (data, paths = []) => {
  for (const path of paths) {
    const result = readCharacterNumber(data, path);
    if (result.exists) return result;
  }
  return { exists: false, path: "", value: null };
};

export const clampPercent = (current, maximum) => {
  const value = finiteNumber(current);
  const limit = finiteNumber(maximum);
  if (value === null || limit === null || limit <= 0) return 0;
  return Math.max(0, Math.min(100, (value / limit) * 100));
};

export const selectPlayerCharacter = ({
  characters = [],
  userId = null,
  focusedCharacterId = null,
  selectedCharacterId = null,
  campaignId = null,
} = {}) => {
  const ownerKey = idKey(userId);
  if (!ownerKey) return null;
  const owned = characters
    .filter(
      (character) =>
        idKey(character?.ownerUserId) === ownerKey &&
        (campaignId === null ||
          campaignId === undefined ||
          idsMatch(character?.campaignId, campaignId)),
    )
    .slice()
    .sort(compareCharacters);
  if (!owned.length) return null;
  return (
    owned.find((character) => idsMatch(character.id, focusedCharacterId)) ||
    owned.find((character) => idsMatch(character.id, selectedCharacterId)) ||
    null
  );
};

export const selectHudCharacter = ({
  characters = [],
  userId = null,
  focusedCharacterId = null,
  selectedCharacterId = null,
  canManage = false,
  campaignId = null,
} = {}) => {
  if (canManage) {
    const available = characters
      .filter(
        (character) =>
          campaignId === null ||
          campaignId === undefined ||
          idsMatch(character?.campaignId, campaignId),
      )
      .slice()
      .sort(compareCharacters);
    return (
      available.find((character) =>
        idsMatch(character?.id, focusedCharacterId),
      ) ||
      available.find((character) =>
        idsMatch(character?.id, selectedCharacterId),
      ) ||
      null
    );
  }
  return selectPlayerCharacter({
    characters,
    userId,
    focusedCharacterId,
    selectedCharacterId,
    campaignId,
  });
};

export const isOwnedCharacter = (character, userId, characterId = null) =>
  Boolean(character) &&
  idsMatch(character.ownerUserId, userId) &&
  (characterId === null || idsMatch(character.id, characterId));

export const isHudCharacterAllowed = ({
  character,
  summary,
  userId,
  characterId,
  canManage = false,
} = {}) => {
  if (!character || !summary || !idsMatch(character.id, characterId)) {
    return false;
  }
  if (!idsMatch(summary.id, characterId)) return false;
  return canManage || isOwnedCharacter(character, userId, characterId);
};

const hpHint = (value) =>
  /(^|[._])(hp|health|hitpoints?|wounds?|zyw|vitality|current_wounds?)($|[._])/iu.test(
    String(value || "").replace(/\s+/gu, ""),
  );

const linkedHealthResource = (character, tokens) => {
  const candidates = [];
  (Array.isArray(tokens) ? tokens : []).forEach((token) => {
    if (!idsMatch(token?.characterId, character?.id)) return;
    const bars = Array.isArray(token?.resources?.bars)
      ? token.resources.bars
      : [];
    bars.forEach((bar, barIndex) => {
      const currentPath = safePath(bar?.attributePath);
      if (!currentPath) return;
      const score =
        (hpHint(currentPath) ? 2 : 0) + (hpHint(bar?.label) ? 1 : 0);
      if (!score) return;
      candidates.push({ bar, barIndex, score, token });
    });
  });
  candidates.sort(
    (left, right) =>
      right.score - left.score ||
      Number(left.token.sceneId || 0) - Number(right.token.sceneId || 0) ||
      Number(left.token.id || 0) - Number(right.token.id || 0) ||
      left.barIndex - right.barIndex,
  );
  return candidates[0] || null;
};

export const healthResourceModel = (character = {}, tokens = []) => {
  const linked = linkedHealthResource(character, tokens);
  if (linked) {
    const current = finiteNumber(linked.bar.value);
    const maximum = finiteNumber(linked.bar.max);
    const complete = current !== null && maximum !== null && maximum > 0;
    return {
      current,
      maximum,
      percent: clampPercent(current, maximum),
      complete,
      editable:
        complete &&
        linked.token.locked !== true &&
        (linked.token.capabilities?.canEdit === true ||
          linked.token.capabilities?.canControl === true ||
          linked.token.capabilities?.canManage === true),
      source: {
        kind: "token",
        token: linked.token,
        barIndex: linked.barIndex,
        currentPath: safePath(linked.bar.attributePath),
        maximumPath: safePath(linked.bar.maxAttributePath),
      },
    };
  }

  const data = character?.data || {};
  for (const field of HEALTH_FIELDS) {
    const current = readCharacterNumber(data, field.current);
    if (!current.exists) continue;
    const maximum = firstNumber(data, field.maximum);
    const complete = maximum.exists && maximum.value > 0;
    return {
      current: current.value,
      maximum: maximum.value,
      percent: clampPercent(current.value, maximum.value),
      complete,
      editable: complete,
      source: {
        kind: "character",
        currentPath: current.path,
        maximumPath: maximum.path,
      },
    };
  }

  return {
    current: null,
    maximum: null,
    percent: 0,
    complete: false,
    editable: false,
    source: null,
  };
};

const experienceBar = (spent, available, required) => {
  const spentValue = finiteNumber(spent);
  const availableValue = finiteNumber(available);
  const requiredValue = finiteNumber(required);
  const complete =
    spentValue !== null &&
    availableValue !== null &&
    requiredValue !== null &&
    requiredValue > 0;
  const spentPercent = complete
    ? clampPercent(Math.max(0, spentValue), requiredValue)
    : 0;
  const availablePercent = complete
    ? Math.min(
        100 - spentPercent,
        clampPercent(Math.max(0, availableValue), requiredValue),
      )
    : 0;
  return {
    spent: spentValue,
    available: availableValue,
    current:
      spentValue === null || availableValue === null
        ? null
        : spentValue + availableValue,
    required: requiredValue,
    spentPercent,
    availablePercent,
    complete,
  };
};

export const experienceResourceModel = (character = {}) => {
  const data = character?.data || {};
  const definition = EXPERIENCE_FIELDS.find(
    (field) => readCharacterNumber(data, field.available).exists,
  );
  if (!definition) {
    const empty = experienceBar(null, null, null);
    return {
      available: null,
      maximum: null,
      complete: false,
      editable: false,
      source: null,
      complex: empty,
      minimum: { ...empty },
      distinguishesMinimum: false,
    };
  }

  const available = readCharacterNumber(data, definition.available);
  const earned = firstNumber(data, definition.earned);
  const baseRequired = firstNumber(data, definition.required);
  const required = baseRequired.exists ? baseRequired : earned;
  const explicitComplexSpent = firstNumber(data, COMPLEX_SPENT_FIELDS);
  const explicitMinimumSpent = firstNumber(data, MINIMUM_SPENT_FIELDS);
  const fallbackSpent =
    earned.exists && available.exists
      ? Math.max(0, earned.value - available.value)
      : null;
  const complexSpent = explicitComplexSpent.exists
    ? explicitComplexSpent.value
    : fallbackSpent;
  const minimumSpent = explicitMinimumSpent.exists
    ? explicitMinimumSpent.value
    : complexSpent;
  const complexRequired = firstNumber(data, COMPLEX_REQUIRED_FIELDS);
  const minimumRequired = firstNumber(data, MINIMUM_REQUIRED_FIELDS);
  const complex = experienceBar(
    complexSpent,
    available.value,
    complexRequired.exists ? complexRequired.value : required.value,
  );
  const minimum = experienceBar(
    minimumSpent,
    available.value,
    minimumRequired.exists ? minimumRequired.value : required.value,
  );

  return {
    available: available.value,
    maximum: required.value,
    complete: complex.complete && minimum.complete,
    editable: complex.complete && minimum.complete,
    source: {
      kind: "character",
      currentPath: available.path,
      maximumPath: required.path,
    },
    complex,
    minimum,
    distinguishesMinimum: explicitMinimumSpent.exists || minimumRequired.exists,
  };
};

export const createPlayerCharacterHudModel = (character, tokens = []) => ({
  health: healthResourceModel(character, tokens),
  experience: experienceResourceModel(character),
});

export const cloneDataWithNumber = (data, requestedPath, value) => {
  const path = safePath(requestedPath);
  const nextValue = finiteNumber(value);
  if (!path || nextValue === null) return null;
  let clone;
  try {
    clone = JSON.parse(JSON.stringify(data || {}));
  } catch (_error) {
    return null;
  }
  if (!clone || typeof clone !== "object" || Array.isArray(clone)) return null;
  const segments = path.split(".");
  const last = segments.pop();
  let cursor = clone;
  for (const segment of segments) {
    if (
      !cursor[segment] ||
      typeof cursor[segment] !== "object" ||
      Array.isArray(cursor[segment])
    ) {
      return null;
    }
    cursor = cursor[segment];
  }
  if (!Object.prototype.hasOwnProperty.call(cursor, last)) return null;
  cursor[last] = nextValue;
  return clone;
};

export const tokenResourcesWithValue = (token, barIndex, value) => {
  const nextValue = finiteNumber(value);
  if (nextValue === null || !Array.isArray(token?.resources?.bars)) return null;
  let resources;
  try {
    resources = JSON.parse(JSON.stringify(token.resources));
  } catch (_error) {
    return null;
  }
  if (!resources.bars?.[barIndex]) return null;
  resources.bars[barIndex].value = nextValue;
  return resources;
};

export const nextResourceValue = (current, delta, maximum = null) => {
  const value = finiteNumber(current);
  const change = finiteNumber(delta);
  if (value === null || change === null) return null;
  const limit = finiteNumber(maximum);
  return Math.max(
    0,
    limit !== null && limit >= 0
      ? Math.min(limit, value + change)
      : value + change,
  );
};

export const shopOwnerCodeForCharacter = (access = {}, characterId = null) => {
  const id = Number(characterId);
  if (!Number.isInteger(id) || id < 1) return null;
  const direct = Array.isArray(access?.characters) ? access.characters : [];
  const grouped = (
    Array.isArray(access?.players) ? access.players : []
  ).flatMap((player) =>
    Array.isArray(player?.characters) ? player.characters : [],
  );
  const selected = [...direct, ...grouped].find(
    (character) => Number(character?.characterId) === id,
  );
  const ownerCode = String(selected?.ownerCode || "")
    .trim()
    .toUpperCase();
  return ownerCode || null;
};

export const playerHudActions = (canOpenShop, canManage, shopBusy = false) => [
  { id: "home", icon: "scene", available: false },
  { id: "character", icon: "users", available: true },
  { id: "combat", icon: "sword", available: true },
  { id: "map", icon: "fit", available: true },
  { id: "history", icon: "book", available: false },
  { id: "scrolls", icon: "file", available: false },
  { id: "journal", icon: "file", available: false },
  { id: "equipment", icon: "package", available: false },
  { id: "traits", icon: "users", available: false },
  { id: "notes", icon: "file", available: false },
  { id: "bestiary", icon: "database", available: false },
  { id: "purse", icon: "shop", available: false },
  { id: "abilities", icon: "light", available: false },
  {
    id: "shop",
    icon: "shop",
    available: canOpenShop === true && shopBusy !== true,
    busy: shopBusy === true,
  },
  { id: "dice", icon: "dice", available: true },
  { id: "spells", icon: "light", available: false },
  { id: "advance", icon: "sword", available: false },
  { id: "settings", icon: "settings", available: canManage === true },
];
