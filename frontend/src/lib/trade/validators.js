import {
  normalizeInventoryRecord,
  normalizeTemplateRecord,
  normalizeTrashRecord,
} from "@/lib/trade/normalizers";

const firstErrorMessage = (errors = {}) => {
  const values = Object.values(errors).filter(Boolean);
  return values.length ? values[0] : "";
};

const makeResult = (record, errors = {}) => ({
  valid: !Object.keys(errors).length,
  errors,
  message: firstErrorMessage(errors),
  record,
});

export const validateTemplateRecord = (
  input = {},
  { requireId = true } = {},
) => {
  const record = normalizeTemplateRecord(input);
  const errors = {};
  const rawPrize = Number(input.PRIZE ?? input.prize);
  const rawCharge = Number(input.CHARGE ?? input.charge);
  if (requireId && (!Number.isFinite(record.id) || record.id <= 0)) {
    errors.ID = "ID must be a number greater than 0.";
  }
  if (!record.name) {
    errors.NAME = "Name is required.";
  }
  if (!record.description) {
    errors.DESCRIPTION = "Description is required.";
  }
  if (!record.itemClass) {
    errors.ITEM_CLASS = "ITEM_CLASS is required.";
  }
  if (record.itemClass === "WEAPON" && !String(record.itemId || "").trim()) {
    errors.ITEM_ID = "ITEM_ID is required for WEAPON.";
  }
  if (!Number.isFinite(rawPrize) || rawPrize < 0) {
    errors.PRIZE = "PRIZE must be a number >= 0.";
  }
  if (!Number.isFinite(rawCharge) || rawCharge < 0) {
    errors.CHARGE = "CHARGE must be a number >= 0.";
  }
  return makeResult(record, errors);
};

export const validateInventoryRecord = (
  input = {},
  { requireId = true } = {},
) => {
  const record = normalizeInventoryRecord(input);
  const errors = {};
  const rawTemplateId = Number(input.INV_ID ?? input.invId ?? input.templateId);
  const rawPersonalCost = Number(
    input.PERSONAL_COST ?? input.personalCost ?? input.PRIZE ?? input.prize,
  );
  const rawQuantity = Number(input.QUANTITY ?? input.quantity);
  const hasRawPrize =
    Object.prototype.hasOwnProperty.call(input, "PRIZE") ||
    Object.prototype.hasOwnProperty.call(input, "prize");
  const hasRawCharge =
    Object.prototype.hasOwnProperty.call(input, "CHARGE") ||
    Object.prototype.hasOwnProperty.call(input, "charge");
  const rawPrize = Number(input.PRIZE ?? input.prize);
  const rawCharge = Number(input.CHARGE ?? input.charge);
  if (requireId && (!Number.isFinite(record.id) || record.id <= 0)) {
    errors.ID = "Valid ID is required.";
  }
  if (!Number.isFinite(rawTemplateId) || rawTemplateId <= 0) {
    errors.INV_ID = "INV_ID must be a valid number.";
  }
  if (!Number.isFinite(rawPersonalCost) || rawPersonalCost < 0) {
    errors.PERSONAL_COST = "PERSONAL_COST must be a number >= 0.";
  }
  if (!Number.isFinite(rawQuantity) || rawQuantity < 0) {
    errors.QUANTITY = "QUANTITY must be a number >= 0.";
  }
  if (hasRawPrize && (!Number.isFinite(rawPrize) || rawPrize < 0)) {
    errors.PRIZE = "PRIZE must be a number >= 0.";
  }
  if (hasRawCharge && (!Number.isFinite(rawCharge) || rawCharge < 0)) {
    errors.CHARGE = "CHARGE must be a number >= 0.";
  }
  return makeResult(record, errors);
};

export const validateTrashRecord = (input = {}, options = {}) => {
  const record = normalizeTrashRecord(input);
  const base = validateInventoryRecord(record, options);
  const errors = { ...base.errors };
  if (!record.trashKind) {
    errors.TRASH_KIND = "TRASH_KIND is required.";
  }
  return makeResult(record, errors);
};
