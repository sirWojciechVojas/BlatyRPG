const enumValue = (value, allowed, fallback) => {
  const normalized = String(value || "").toUpperCase();
  return allowed.includes(normalized) ? normalized : fallback;
};

const codeValue = (value, fallback = "") => {
  const normalized = String(value || "")
    .trim()
    .toUpperCase();
  return /^[A-Z][A-Z0-9_]{0,63}$/u.test(normalized) ? normalized : fallback;
};

const TRIGGERS = ["USE", "ATTACK", "CONSUME", "EQUIP", "PASSIVE", "CUSTOM"];
const HANDLERS = [
  "GENERIC",
  "DICE_TEST",
  "ATTACK",
  "CONSUME",
  "APPLY_EFFECTS",
  "CUSTOM",
];
const CONDITIONS = ["ALWAYS", "SUCCESS", "FAILURE"];
const EFFECT_TYPES = [
  "DAMAGE",
  "HEAL",
  "STATUS",
  "MODIFIER",
  "RESOURCE",
  "CUSTOM",
];
const TARGETS = ["SELF", "TARGET", "AREA"];
const MODES = ["INHERIT", "EXTEND", "REPLACE"];

export const mechanicOptionCodes = Object.freeze({
  triggers: TRIGGERS,
  handlers: HANDLERS,
  conditions: CONDITIONS,
  effectTypes: EFFECT_TYPES,
  targets: TARGETS,
  modes: MODES,
});

export const createItemEffect = (overrides = {}) => ({
  when: "ALWAYS",
  type: "CUSTOM",
  target: "SELF",
  value: "",
  duration: "",
  resourceCode: "",
  statusCode: "",
  description: "",
  ...overrides,
});

export const createItemMechanic = (code = "NEW_MECHANIC", overrides = {}) => ({
  labelPl: "",
  labelEn: "",
  enabled: true,
  trigger: "USE",
  handler: "GENERIC",
  actionLabel: "",
  description: "",
  handlerKey: "",
  parameters: {},
  ...overrides,
  code: codeValue(overrides.code ?? code, "NEW_MECHANIC"),
  check: {
    enabled: false,
    formula: "1d100",
    targetKey: "",
    difficulty: 0,
    comparison: "LTE",
    ...(overrides.check || {}),
  },
  effects: (overrides.effects || []).map((effect) => createItemEffect(effect)),
  cost: {
    quantity: 0,
    charges: 0,
    resourceCode: "",
    resourceValue: "",
    ...(overrides.cost || {}),
  },
});

export const normalizeItemMechanics = (values = []) => {
  const byCode = new Map();
  (Array.isArray(values) ? values : []).slice(0, 50).forEach((value, index) => {
    if (!value || typeof value !== "object") return;
    const mechanic = createItemMechanic(
      codeValue(value.code, `MECHANIC_${index + 1}`),
      value,
    );
    mechanic.trigger = enumValue(mechanic.trigger, TRIGGERS, "USE");
    mechanic.handler = enumValue(mechanic.handler, HANDLERS, "GENERIC");
    mechanic.effects = mechanic.effects.slice(0, 20).map((effect) => ({
      ...createItemEffect(effect),
      when: enumValue(effect.when, CONDITIONS, "ALWAYS"),
      type: enumValue(effect.type, EFFECT_TYPES, "CUSTOM"),
      target: enumValue(effect.target, TARGETS, "SELF"),
    }));
    byCode.set(mechanic.code, mechanic);
  });
  return [...byCode.values()];
};

export const mechanicsMode = (value) => enumValue(value, MODES, "EXTEND");

export const dictionaryMechanics = (dictionaries, group, code) =>
  normalizeItemMechanics(
    dictionaries?.[group]?.find((entry) => entry.code === code)?.mechanics ||
      [],
  );

export const resolveItemMechanics = ({
  dictionaries = {},
  itemClass = "",
  itemGenre = "",
  templateMechanics = [],
  mode = "EXTEND",
} = {}) => {
  const resolved = new Map();
  const merge = (values, source) => {
    normalizeItemMechanics(values).forEach((mechanic) => {
      if (!mechanic.enabled) {
        resolved.delete(mechanic.code);
        return;
      }
      resolved.set(mechanic.code, { ...mechanic, source });
    });
  };
  const normalizedMode = mechanicsMode(mode);
  if (normalizedMode !== "REPLACE") {
    merge(dictionaryMechanics(dictionaries, "classes", itemClass), "CLASS");
    merge(dictionaryMechanics(dictionaries, "genres", itemGenre), "GENRE");
  }
  if (normalizedMode !== "INHERIT") {
    merge(templateMechanics, "TEMPLATE");
  }
  return [...resolved.values()];
};
