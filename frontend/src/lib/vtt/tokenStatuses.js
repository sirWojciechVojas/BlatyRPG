export const QUICK_TOKEN_STATUSES = Object.freeze([
  { code: "poisoned", symbol: "☠" },
  { code: "bleeding", symbol: "◆" },
  { code: "stunned", symbol: "✦" },
  { code: "blinded", symbol: "◉" },
  { code: "burning", symbol: "♨" },
  { code: "dead", symbol: "†" },
]);

export const tokenStatusCode = (status) =>
  String(
    typeof status === "string"
      ? status
      : status?.code || status?.id || status?.name || "",
  ).toLocaleLowerCase();

export const quickTokenStatus = (code) =>
  QUICK_TOKEN_STATUSES.find(
    (status) => status.code === tokenStatusCode(code),
  ) || null;

export const toggleTokenStatus = (statuses = [], code) => {
  const normalized = tokenStatusCode(code);
  const active = statuses.some(
    (status) => tokenStatusCode(status) === normalized,
  );
  return active
    ? statuses.filter((status) => tokenStatusCode(status) !== normalized)
    : [...statuses, normalized];
};
