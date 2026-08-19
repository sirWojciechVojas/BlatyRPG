export const WFRP_CURRENCY_DEFINITIONS = Object.freeze([
  {
    code: "wfrp_empire",
    labelPl: "Imperium",
    labelEn: "The Empire",
    units: [
      {
        code: "gold_crown",
        labelPl: "Złota korona",
        labelEn: "Gold crown",
        symbolPl: "zk",
        symbolEn: "gc",
        factor: 240,
        icon: "crown",
      },
      {
        code: "silver_shilling",
        labelPl: "Srebrny szyling",
        labelEn: "Silver shilling",
        symbolPl: "s",
        symbolEn: "ss",
        factor: 12,
        icon: "shilling",
      },
      {
        code: "brass_penny",
        labelPl: "Miedziany pens",
        labelEn: "Brass penny",
        symbolPl: "p",
        symbolEn: "bp",
        factor: 1,
        icon: "brass",
      },
    ],
  },
  {
    code: "wfrp_bretonnia",
    labelPl: "Bretonnia",
    labelEn: "Bretonnia",
    units: [
      {
        code: "ecu",
        labelPl: "Ecu",
        labelEn: "Ecu",
        symbolPl: "ecu",
        symbolEn: "ecu",
        factor: 240,
        icon: "crown",
      },
      {
        code: "denier",
        labelPl: "Denier",
        labelEn: "Denier",
        symbolPl: "d",
        symbolEn: "d",
        factor: 1,
        icon: "brass",
      },
    ],
  },
]);

export const COC_CURRENCY_DEFINITIONS = Object.freeze([
  {
    code: "coc_usd_1920",
    labelPl: "Dolar amerykański (lata 20.)",
    labelEn: "US dollar (1920s)",
    units: [
      {
        code: "dollar",
        labelPl: "Dolar",
        labelEn: "Dollar",
        symbolPl: "$",
        symbolEn: "$",
        factor: 100,
        icon: "unit",
      },
      {
        code: "cent",
        labelPl: "Cent",
        labelEn: "Cent",
        symbolPl: "¢",
        symbolEn: "¢",
        factor: 1,
        icon: "unit",
      },
    ],
  },
  {
    code: "coc_gbp_1920",
    labelPl: "Funt brytyjski (przed dziesiętny)",
    labelEn: "British pound (pre-decimal)",
    units: [
      {
        code: "pound",
        labelPl: "Funt",
        labelEn: "Pound",
        symbolPl: "£",
        symbolEn: "£",
        factor: 240,
        icon: "unit",
      },
      {
        code: "shilling",
        labelPl: "Szyling",
        labelEn: "Shilling",
        symbolPl: "s",
        symbolEn: "s",
        factor: 12,
        icon: "unit",
      },
      {
        code: "penny",
        labelPl: "Pens",
        labelEn: "Penny",
        symbolPl: "d",
        symbolEn: "d",
        factor: 1,
        icon: "unit",
      },
    ],
  },
  {
    code: "coc_frf_1920",
    labelPl: "Frank francuski (lata 20.)",
    labelEn: "French franc (1920s)",
    units: [
      {
        code: "franc",
        labelPl: "Frank",
        labelEn: "Franc",
        symbolPl: "₣",
        symbolEn: "₣",
        factor: 100,
        icon: "unit",
      },
      {
        code: "centime",
        labelPl: "Centym",
        labelEn: "Centime",
        symbolPl: "c",
        symbolEn: "c",
        factor: 1,
        icon: "unit",
      },
    ],
  },
]);

export const GENERIC_CURRENCY_DEFINITION = Object.freeze({
  code: "generic",
  labelPl: "Waluta systemowa",
  labelEn: "System currency",
  units: [
    {
      code: "unit",
      labelPl: "Jednostka",
      labelEn: "Unit",
      symbolPl: "j",
      symbolEn: "u",
      factor: 1,
      icon: "unit",
    },
  ],
});

export const currencyDefinitionsForSystem = (systemCode = "wfrp2ed") => {
  const code = String(systemCode).toLowerCase();
  if (["wfrp2ed", "wfrp", "warhammer"].includes(code)) {
    return WFRP_CURRENCY_DEFINITIONS;
  }
  if (["coc7e", "coc", "call_of_cthulhu", "cthulhu"].includes(code)) {
    return COC_CURRENCY_DEFINITIONS;
  }
  return [GENERIC_CURRENCY_DEFINITION];
};

export const resolveCurrencyDefinition = (
  definitions = [],
  currencyCode = "",
) => {
  const source =
    Array.isArray(definitions) && definitions.length
      ? definitions
      : WFRP_CURRENCY_DEFINITIONS;
  return (
    source.find((entry) => entry.code === currencyCode) ||
    source[0] ||
    GENERIC_CURRENCY_DEFINITION
  );
};

export const resolveDisplayCurrencyCode = (
  currencyCode = "",
  fallbackCurrencyCode = "generic",
) => {
  const code = String(currencyCode || "")
    .trim()
    .toLowerCase();
  const fallback = String(fallbackCurrencyCode || "")
    .trim()
    .toLowerCase();

  if (!code || (code === "generic" && fallback && fallback !== "generic")) {
    return fallback || "generic";
  }
  return code;
};

export const decomposeCurrencyAmount = (value, definition) => {
  let remaining = Math.max(0, Math.floor(Number(value) || 0));
  return (definition?.units || []).reduce((amounts, unit) => {
    const factor = Math.max(1, Math.floor(Number(unit.factor) || 1));
    amounts[unit.code] = Math.floor(remaining / factor);
    remaining %= factor;
    return amounts;
  }, {});
};

export const composeCurrencyAmount = (amounts = {}, definition) =>
  Math.max(
    0,
    Math.floor(
      (definition?.units || []).reduce(
        (sum, unit) =>
          sum +
          Math.max(0, Math.floor(Number(amounts[unit.code]) || 0)) *
            Math.max(1, Math.floor(Number(unit.factor) || 1)),
        0,
      ),
    ),
  );

export const localizedCurrencyLabel = (definition, locale = "pl") =>
  String(locale).startsWith("pl")
    ? definition?.labelPl || definition?.labelEn || definition?.code || ""
    : definition?.labelEn || definition?.labelPl || definition?.code || "";

export const formatCurrencyAmount = (value, definition, locale = "pl") => {
  const amounts = decomposeCurrencyAmount(value, definition);
  return (definition?.units || [])
    .map((unit) => {
      const symbol = String(locale).startsWith("pl")
        ? unit.symbolPl || unit.symbolEn
        : unit.symbolEn || unit.symbolPl;
      return `${amounts[unit.code] || 0} ${symbol || unit.code}`;
    })
    .join(" ");
};
