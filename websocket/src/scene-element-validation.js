import { ProtocolError } from "./protocol-error.js";

export const plainObject = (value) =>
  value !== null && typeof value === "object" && !Array.isArray(value);

export const exactKeys = (value, allowed) => {
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field", key);
  }
};

export const number = (
  value,
  code,
  minimum = -1000000,
  maximum = 1000000,
) => {
  if (
    typeof value !== "number" ||
    !Number.isFinite(value) ||
    value < minimum ||
    value > maximum
  ) {
    throw new ProtocolError(code);
  }
  return value;
};
