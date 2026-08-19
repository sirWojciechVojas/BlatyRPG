export const createRuntimePart1Segment1 = (runtime) => {
  const toText = (value) => String(value || "").trim();
  Object.assign(runtime, {
    toText,
  });
  const MOJIBAKE_CHAR_RE =
    /[\u00C4\u0102\u0139\u00B6\u00BB\u0081\u201A\u201E\u2021\u2026\u203A\u2122\u013D\u015F\u0161]/;
  Object.assign(runtime, {
    MOJIBAKE_CHAR_RE,
  });
  const MOJIBAKE_REPLACEMENTS = [
    ["\u00C4\u2026", "ą"],
    ["\u00C4\u2021", "ć"],
    ["\u00C4\u2122", "ę"],
    ["\u0139\u201A", "ł"],
    ["\u0139\u201E", "ń"],
    ["\u0139\u203A", "ś"],
    ["\u0139\u015F", "ź"],
    ["\u0139\u013D", "ż"],
    ["\u0139\u0081", "Ł"],
    ["\u0139\u0161", "Ś"],
    ["\u0139\u00BB", "Ż"],
    ["\u00C5\u201A", "ł"],
    ["\u00C5\u201E", "ń"],
    ["\u0102\u0142", "ó"],
    ["\u00C5\u203A", "ś"],
    ["\u00C5\u015F", "ź"],
    ["\u00C5\u013D", "ż"],
    ["\u00C5\u0081", "Ł"],
    ["\u00C5\u0161", "Ś"],
    ["\u00C5\u00BB", "Ż"],
    ["\u0102\u00B6", "ö"],
  ];
  Object.assign(runtime, {
    MOJIBAKE_REPLACEMENTS,
  });
  const repairMojibake = (value) => {
    let text = String(value || "");
    if (!runtime.MOJIBAKE_CHAR_RE.test(text)) {
      return text;
    }
    runtime.MOJIBAKE_REPLACEMENTS.forEach(([broken, fixed]) => {
      text = text.replaceAll(broken, fixed);
    });
    return text;
  };
  Object.assign(runtime, {
    repairMojibake,
  });
  const toLower = (value) => runtime.toText(value).toLocaleLowerCase("pl-PL");
  Object.assign(runtime, {
    toLower,
  });
  const toComparable = (value) =>
    runtime
      .toLower(value)
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/ł/g, "l");
  Object.assign(runtime, {
    toComparable,
  });
  const normalizeSpaces = (value) =>
    runtime.toText(runtime.repairMojibake(value)).replace(/\s+/g, " ").trim();
  Object.assign(runtime, {
    normalizeSpaces,
  });
  const toWords = (value) => {
    const matches = runtime.toText(value).match(/[\p{L}\p{N}]+/gu);
    return Array.isArray(matches) ? matches : [];
  };
  Object.assign(runtime, {
    toWords,
  });
  const titleCase = (value) =>
    runtime
      .toWords(value)
      .map(
        (word) =>
          `${word.charAt(0).toLocaleUpperCase("pl-PL")}${word.slice(1).toLocaleLowerCase("pl-PL")}`,
      )
      .join(" ");
  Object.assign(runtime, {
    titleCase,
  });
  const uniq = (items = []) =>
    Array.from(
      new Set(
        items.map((entry) => runtime.normalizeSpaces(entry)).filter(Boolean),
      ),
    );
  Object.assign(runtime, {
    uniq,
  });
  const randomFnSafe = (fn = Math.random) => {
    const value = Number(fn());
    if (!Number.isFinite(value)) {
      return 0.5;
    }
    if (value <= 0) {
      return 0;
    }
    if (value >= 1) {
      return 0.999999;
    }
    return value;
  };
  Object.assign(runtime, {
    randomFnSafe,
  });
  const pickRandom = (items = [], randomFn = Math.random, fallback = "") => {
    if (!Array.isArray(items) || !items.length) {
      return fallback;
    }
    const index = Math.floor(runtime.randomFnSafe(randomFn) * items.length);
    return items[index];
  };
  Object.assign(runtime, {
    pickRandom,
  });
  const hashString = (input) => {
    let hash = 2166136261;
    const value = String(input || "");
    for (let i = 0; i < value.length; i += 1) {
      hash ^= value.charCodeAt(i);
      hash +=
        (hash << 1) + (hash << 4) + (hash << 7) + (hash << 8) + (hash << 24);
    }
    return hash >>> 0;
  };
  Object.assign(runtime, {
    hashString,
  });
  const pickByHash = (items = [], hash = 0, salt = 0, fallback = "") => {
    if (!Array.isArray(items) || !items.length) {
      return fallback;
    }
    const index = Math.abs(Number(hash) + Number(salt)) % items.length;
    return items[index];
  };
  Object.assign(runtime, {
    pickByHash,
  });
  return {
    toText,
    MOJIBAKE_CHAR_RE,
    MOJIBAKE_REPLACEMENTS,
    repairMojibake,
    toLower,
    toComparable,
    normalizeSpaces,
    toWords,
    titleCase,
    uniq,
    randomFnSafe,
    pickRandom,
    hashString,
    pickByHash,
  };
};
