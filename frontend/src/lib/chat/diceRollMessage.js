const DICE_ROLL_PREFIX = "[[blatyrpg-roll:v1]]";

const cleanText = (value, maximum = 120) =>
  Array.from(String(value ?? ""))
    .map((character) => {
      const codePoint = character.codePointAt(0);
      return codePoint < 32 || codePoint === 127 || codePoint > 0xffff
        ? " "
        : character;
    })
    .join("")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, maximum);

const normalizeDie = (die) => {
  const type = cleanText(die?.type, 16);
  const value = cleanText(die?.display ?? die?.value ?? die?.label, 80);
  return type && value ? { type, value } : null;
};

const percentileTotal = (formula, dice) => {
  const compactFormula = formula.replace(/\s+/g, "").toLowerCase();
  if (
    !["1d100+1d10", "1d10+1d100"].includes(compactFormula) ||
    dice.length !== 2
  ) {
    return null;
  }
  const tens = dice.find((die) => die.type.toLowerCase() === "d100");
  const units = dice.find((die) => die.type.toLowerCase() === "d10");
  const tensValue = Number(tens?.value);
  const unitsValue = Number(units?.value);
  if (!Number.isFinite(tensValue) || !Number.isFinite(unitsValue)) return null;
  const total = tensValue + unitsValue;
  return String(total === 0 ? 100 : total);
};

export const createDiceRollMessage = ({ formula, dice, total } = {}) => {
  const normalized = {
    formula: cleanText(formula),
    dice: (Array.isArray(dice) ? dice : [])
      .map(normalizeDie)
      .filter(Boolean)
      .slice(0, 20),
    total: cleanText(total, 80),
  };
  if (!normalized.formula || !normalized.total) return "";
  return `${DICE_ROLL_PREFIX}${JSON.stringify(normalized)}`;
};

export const parseDiceRollMessage = (body) => {
  const source = String(body || "");
  if (!source.startsWith(DICE_ROLL_PREFIX)) return null;
  try {
    const parsed = JSON.parse(source.slice(DICE_ROLL_PREFIX.length));
    const formula = cleanText(parsed?.formula);
    const total = cleanText(parsed?.total, 80);
    const dice = (Array.isArray(parsed?.dice) ? parsed.dice : [])
      .map(normalizeDie)
      .filter(Boolean)
      .slice(0, 20);
    return formula && total
      ? { formula, dice, total: percentileTotal(formula, dice) || total }
      : null;
  } catch (_error) {
    return null;
  }
};

const maximumForDie = (type) => {
  if (type === "d100") return 90;
  if (type === "d10") return 9;
  if (type === "D10") return 10;
  const match = String(type).match(/^[dD](\d+)$/);
  return match ? Number(match[1]) : null;
};

export const groupDiceRollResults = (dice = []) => {
  const groups = [];
  const groupsByType = new Map();

  dice.forEach((die) => {
    const type = cleanText(die?.type, 16);
    const value = cleanText(die?.value, 80);
    if (!type || !value) return;

    let group = groupsByType.get(type);
    if (!group) {
      group = { type, notation: "", dice: [], total: "" };
      groupsByType.set(type, group);
      groups.push(group);
    }

    const numericValue = Number(value);
    group.dice.push({
      type,
      value,
      isMaximum:
        Number.isFinite(numericValue) && numericValue === maximumForDie(type),
    });
  });

  return groups.map((group) => {
    const values = group.dice.map((die) => Number(die.value));
    return {
      ...group,
      notation: `${group.dice.length}${group.type}`,
      total: values.every(Number.isFinite)
        ? String(values.reduce((sum, value) => sum + value, 0))
        : "",
    };
  });
};

export const presentDiceRollMessage = (roll) =>
  roll
    ? {
        ...roll,
        groups: groupDiceRollResults(roll.dice),
      }
    : null;

export { DICE_ROLL_PREFIX };
