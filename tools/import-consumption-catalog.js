#!/usr/bin/env node
/*
 * Converts the supplied WFRP consumption workbook into PHP data used at
 * runtime. The game never opens an XLSX file; this script is an explicit
 * build/import step and uses only Node's standard library.
 */
const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");

const [source = "docs/katalog_potraw_i_napojow_wfrp2.xlsx", destination = "backend/app/Data/consumption_catalog.php"] = process.argv.slice(2);
const requiredCatalogColumns = [
  "ID", "Nazwa", "Nazwa niezidentyfikowana", "Rodzaj", "Kategoria",
  "Region / kultura", "Dostępność", "Cena bazowa w karczmie (p)",
  "Trwałość", "Sytość (h)", "Nawodnienie (h)", "Efekt ID",
  "Efekt mechaniczny", "Okno działania", "Ryzyko", "Test negatywny",
  "Skutek porażki", "Podczas walki", "Czas spożycia", "Opis",
];
const requiredEffectColumns = ["Efekt ID", "Nazwa", "Kategoria", "Zasada", "Kumulowanie", "Uwagi"];
const riskValues = new Set(["Brak", "Niskie", "Umiarkowane", "Wysokie", "Śmiertelne"]);

const xmlDecode = (value) => String(value || "")
  .replace(/&amp;/g, "&").replace(/&lt;/g, "<").replace(/&gt;/g, ">")
  .replace(/&quot;/g, '"').replace(/&apos;/g, "'")
  .replace(/&#(\d+);/g, (_, value) => String.fromCodePoint(Number(value)));
const columnNumber = (letters) => String(letters).split("").reduce((number, letter) => number * 26 + letter.charCodeAt(0) - 64, 0);
const cellValue = (xml) => {
  const text = [...xml.matchAll(/<(?:\w+:)?t[^>]*>([\s\S]*?)<\/(?:\w+:)?t>/g)]
    .map((match) => xmlDecode(match[1])).join("");
  if (text) return text.trim();
  const value = /<(?:\w+:)?v>([\s\S]*?)<\/(?:\w+:)?v>/.exec(xml);
  return xmlDecode(value ? value[1] : "").trim();
};
const workbookRows = (file, sheetPath) => {
  const xml = execFileSync("unzip", ["-p", file, sheetPath], { encoding: "utf8" });
  const rows = [];
  for (const row of xml.matchAll(/<(?:\w+:)?row\b[^>]*\br="(\d+)"[^>]*>([\s\S]*?)<\/(?:\w+:)?row>/g)) {
    const values = [];
    for (const cell of row[2].matchAll(/<(?:\w+:)?c\b([^>]*)>([\s\S]*?)<\/(?:\w+:)?c>/g)) {
      const ref = /\br="([A-Z]+)\d+"/.exec(cell[1]);
      if (ref) values[columnNumber(ref[1]) - 1] = cellValue(cell[2]);
    }
    rows[Number(row[1]) - 1] = values;
  }
  return rows;
};
const nonEmpty = (rows) => rows.filter((row) => Array.isArray(row) && row.some((value) => String(value || "").trim()));
const findHeader = (rows, required) => {
  const index = rows.findIndex((row) => required.every((name) => row.includes(name)));
  if (index < 0) throw new Error(`Nie znaleziono wymaganych nagłówków: ${required.join(", ")}`);
  return { index, columns: Object.fromEntries(rows[index].map((name, index) => [name, index])) };
};
const numberValue = (value, field, id) => {
  const number = Number(String(value).replace(",", "."));
  if (!Number.isFinite(number) || number < 0) throw new Error(`${id}: ${field} musi być nieujemną liczbą.`);
  return number;
};
const value = (row, columns, field) => String(row[columns[field]] || "").trim();
const durationMinutes = (raw) => {
  const normalized = String(raw || "").toLowerCase();
  const match = normalized.match(/(\d+)\s*(minut|minuty|godzin|godziny|godzina)/u);
  if (!match) return 0;
  return Number(match[1]) * (match[2].startsWith("godzin") ? 60 : 1);
};
const shelfLifeDays = (raw) => {
  const normalized = String(raw || "").toLowerCase();
  const match = normalized.match(/(\d+)\s*(dzień|dni|dnia|miesi[aą]c|lat)/u);
  if (!match) return null;
  const factor = match[2].startsWith("mies") ? 30 : match[2] === "lat" ? 365 : 1;
  return Number(match[1]) * factor;
};
const combatAllowed = (raw) => String(raw || "").toLowerCase().startsWith("tak");
const effectIsHidden = (effectId) => /^E(?:2[6-9]|3[0-1])$/u.test(effectId);
const effectIsAlcohol = (effectId) => ["E18", "E19", "E20", "E34"].includes(effectId);

const rows = {
  catalog: nonEmpty(workbookRows(source, "xl/worksheets/sheet1.xml")),
  effects: nonEmpty(workbookRows(source, "xl/worksheets/sheet2.xml")),
};
const catalogHeader = findHeader(rows.catalog, requiredCatalogColumns);
const effectsHeader = findHeader(rows.effects, requiredEffectColumns);
const effects = {};
for (const row of rows.effects.slice(effectsHeader.index + 1)) {
  const id = value(row, effectsHeader.columns, "Efekt ID");
  if (!id) continue;
  if (effects[id]) throw new Error(`Zduplikowane ID efektu: ${id}`);
  for (const field of requiredEffectColumns) if (!value(row, effectsHeader.columns, field)) throw new Error(`${id}: brak pola ${field}.`);
  effects[id] = {
    id,
    name: value(row, effectsHeader.columns, "Nazwa"),
    category: value(row, effectsHeader.columns, "Kategoria"),
    rule: value(row, effectsHeader.columns, "Zasada"),
    stacking: value(row, effectsHeader.columns, "Kumulowanie"),
    notes: value(row, effectsHeader.columns, "Uwagi"),
  };
}
const profiles = {};
for (const row of rows.catalog.slice(catalogHeader.index + 1)) {
  const id = value(row, catalogHeader.columns, "ID");
  if (!id) continue;
  if (profiles[id]) throw new Error(`Zduplikowane ID produktu: ${id}`);
  for (const field of requiredCatalogColumns) {
    if (!value(row, catalogHeader.columns, field)) throw new Error(`${id}: brak pola ${field}.`);
  }
  const effectId = value(row, catalogHeader.columns, "Efekt ID");
  if (!effects[effectId]) throw new Error(`${id}: nieznany Efekt ID ${effectId}.`);
  const risk = value(row, catalogHeader.columns, "Ryzyko");
  if (!riskValues.has(risk)) throw new Error(`${id}: nieznana kategoria ryzyka ${risk}.`);
  const rawTime = value(row, catalogHeader.columns, "Czas spożycia");
  const rawShelfLife = value(row, catalogHeader.columns, "Trwałość");
  profiles[id] = {
    id,
    name: value(row, catalogHeader.columns, "Nazwa"),
    unidentifiedName: value(row, catalogHeader.columns, "Nazwa niezidentyfikowana"),
    kind: value(row, catalogHeader.columns, "Rodzaj"),
    category: value(row, catalogHeader.columns, "Kategoria"),
    region: value(row, catalogHeader.columns, "Region / kultura"),
    availability: value(row, catalogHeader.columns, "Dostępność"),
    basePricePennies: numberValue(value(row, catalogHeader.columns, "Cena bazowa w karczmie (p)"), "Cena bazowa", id),
    shelfLife: rawShelfLife,
    shelfLifeDays: shelfLifeDays(rawShelfLife),
    satietyHours: numberValue(value(row, catalogHeader.columns, "Sytość (h)"), "Sytość", id),
    hydrationHours: numberValue(value(row, catalogHeader.columns, "Nawodnienie (h)"), "Nawodnienie", id),
    effectId,
    mechanicalEffect: value(row, catalogHeader.columns, "Efekt mechaniczny"),
    effectWindow: value(row, catalogHeader.columns, "Okno działania"),
    effectWindowMinutes: durationMinutes(value(row, catalogHeader.columns, "Okno działania")),
    risk,
    negativeTest: value(row, catalogHeader.columns, "Test negatywny"),
    failureConsequence: value(row, catalogHeader.columns, "Skutek porażki"),
    usableInCombat: combatAllowed(value(row, catalogHeader.columns, "Podczas walki")),
    combatText: value(row, catalogHeader.columns, "Podczas walki"),
    consumeTime: rawTime,
    consumeMinutes: durationMinutes(rawTime),
    requiresPreparation: effectId === "E35",
    hiddenRisk: effectIsHidden(effectId),
    alcohol: effectIsAlcohol(effectId),
    description: value(row, catalogHeader.columns, "Opis"),
  };
}
if (Object.keys(profiles).length !== 600) throw new Error(`Oczekiwano 600 produktów, otrzymano ${Object.keys(profiles).length}.`);
if (Object.keys(effects).length < 1) throw new Error("Arkusz Efekty nie zawiera definicji.");
const php = `<?php\n\n/** Generated by tools/import-consumption-catalog.js from ${path.basename(source)}. Do not edit manually. */\nreturn ${JSON.stringify({ profiles, effects }, null, 2)
  .replace(/\btrue\b/g, "true").replace(/\bfalse\b/g, "false").replace(/\bnull\b/g, "null")
  .replace(/"([^"\\]+)":/g, (_, key) => `'${key.replace(/'/g, "\\'")}' =>`)
  .replace(/: /g, " => ")
  .replace(/[{}]/g, (token) => token === "{" ? "[" : "]")
  .replace(/\]/g, "]")
  .replace(/,\n/g, ",\n")
  .replace(/\n/g, "\n")
  .replace(/\[\n/g, "[\n")
  .replace(/\n\]/g, "\n]")};\n`;
fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.writeFileSync(destination, php, "utf8");
console.log(`Zaimportowano ${Object.keys(profiles).length} profili i ${Object.keys(effects).length} efektów do ${destination}.`);
