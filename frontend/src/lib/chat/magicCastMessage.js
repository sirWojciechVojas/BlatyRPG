const MAGIC_CAST_PREFIX = "[[blatyrpg-magic:v1]]";

const cleanText = (value, maximum) =>
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

const integer = (value, minimum, maximum) => {
  const normalized = Number(value);
  return Number.isSafeInteger(normalized) &&
    normalized >= minimum &&
    normalized <= maximum
    ? normalized
    : null;
};

const dice = (value, maximumCount) =>
  (Array.isArray(value) ? value : [])
    .map((die) => integer(die, 1, 10))
    .filter((die) => die !== null)
    .slice(0, maximumCount);

const manifestation = (value) => {
  const face = integer(value?.face, 1, 10);
  const matchingDice = integer(value?.matchingDice, 2, 14);
  const severity = ["minor", "major", "catastrophic", "undefined"].includes(
    value?.severity,
  )
    ? value.severity
    : "undefined";
  return face && matchingDice ? { face, matchingDice, severity } : null;
};

const normalize = ({ characterName, cast } = {}) => {
  const result = cast?.result;
  const spell = result?.spell || cast?.spell;
  const powerDice = dice(result?.powerDice, 10);
  const chaosDice = dice(result?.chaosDice, 4);
  const castingNumber = integer(result?.castingNumber, 1, 999);
  const powerTotal = integer(result?.powerTotal, -999, 999);
  const modifier = integer(result?.modifier, -999, 999);
  const name = cleanText(spell?.name, 160);
  if (
    !result ||
    !name ||
    powerDice.length < 1 ||
    castingNumber === null ||
    powerTotal === null ||
    modifier === null ||
    typeof result.spellSucceeded !== "boolean"
  ) {
    return null;
  }

  const ingredientName = cleanText(result.ingredient?.name, 160);
  const channelAttempted = result.channel?.attempted === true;
  return {
    castId: integer(cast?.id, 1, Number.MAX_SAFE_INTEGER),
    character: cleanText(characterName, 120),
    spell: name,
    tradition: cleanText(cast?.spell?.tradition, 100),
    magicType: cleanText(cast?.spell?.magicType, 80),
    castingNumber,
    powerDice,
    chaosDice,
    modifier,
    powerTotal,
    succeeded: result.spellSucceeded,
    automaticFailure: result.automaticFailure === true,
    willpowerTestRequired: result.willpowerTestRequired === true,
    manifestations: (Array.isArray(result.manifestations)
      ? result.manifestations
      : []
    )
      .map(manifestation)
      .filter(Boolean)
      .slice(0, 10),
    channel: channelAttempted
      ? {
          attempted: true,
          succeeded: result.channel?.succeeded === true,
          roll: integer(result.channel?.roll, 1, 100),
          bonus: integer(result.channel?.bonus, -999, 999) ?? 0,
        }
      : null,
    ingredient: ingredientName
      ? {
          name: ingredientName,
          bonus: integer(result.ingredient?.bonus, -999, 999) ?? 0,
          consumed: result.ingredient?.consumed === true,
        }
      : null,
    targets: (Array.isArray(cast?.targets) ? cast.targets : [])
      .map((target) => cleanText(target?.label ?? target, 100))
      .filter(Boolean)
      .slice(0, 6),
    defense: cleanText(result.targetDefense?.message, 220),
    effect: cleanText(result.effect?.message, 420),
  };
};

export const createMagicCastMessage = (value) => {
  const normalized = normalize(value);
  return normalized ? `${MAGIC_CAST_PREFIX}${JSON.stringify(normalized)}` : "";
};

export const parseMagicCastMessage = (body) => {
  const source = String(body || "");
  if (!source.startsWith(MAGIC_CAST_PREFIX)) return null;
  try {
    const parsed = JSON.parse(source.slice(MAGIC_CAST_PREFIX.length));
    return normalize({
      characterName: parsed?.character,
      cast: {
        id: parsed?.castId,
        spell: {
          name: parsed?.spell,
          tradition: parsed?.tradition,
          magicType: parsed?.magicType,
        },
        targets: parsed?.targets,
        result: {
          spell: { name: parsed?.spell },
          castingNumber: parsed?.castingNumber,
          powerDice: parsed?.powerDice,
          chaosDice: parsed?.chaosDice,
          modifier: parsed?.modifier,
          powerTotal: parsed?.powerTotal,
          spellSucceeded: parsed?.succeeded,
          automaticFailure: parsed?.automaticFailure,
          willpowerTestRequired: parsed?.willpowerTestRequired,
          manifestations: parsed?.manifestations,
          channel: parsed?.channel,
          ingredient: parsed?.ingredient,
          targetDefense: { message: parsed?.defense },
          effect: { message: parsed?.effect },
        },
      },
    });
  } catch (_error) {
    return null;
  }
};

export { MAGIC_CAST_PREFIX };
