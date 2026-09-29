export const normalizeProfessionText = (value) =>
  String(value || "")
    .toLocaleLowerCase("pl-PL")
    .replace(/ł/gu, "l")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/gu, "")
    .replace(/\s+/gu, " ")
    .trim();

export const presentProfessionText = (value) =>
  String(value || "")
    .replace(/\\r\\n|\\n|\\r/gu, "\n")
    .replace(/\r\n|\r/gu, "\n")
    .trim();

export const displayProfessionName = (value) => {
  const name = String(value || "").trim();
  return name
    ? `${name.charAt(0).toLocaleUpperCase("pl-PL")}${name.slice(1)}`
    : "";
};

export const professionSearchText = (profession) =>
  normalizeProfessionText(
    [profession?.name, profession?.description, profession?.details].join(" "),
  );

export const professionCollator = new Intl.Collator("pl-PL", {
  sensitivity: "base",
  numeric: true,
});

export const SOURCE_REFERENCE_IDS = Object.freeze([
  199, 200, 201, 202, 203, 204, 205, 206, 207, 209, 210, 211, 212, 213, 214,
  217, 218, 219, 220, 221, 222, 223, 224, 225, 226,
]);

export const EDITORIAL_NOTE_IDS = Object.freeze([113, 182, 189]);
