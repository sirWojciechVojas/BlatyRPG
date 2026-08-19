export const createCoreMethodsPart7 = (runtime) => {
  return {
    normalizeTemplateForm(form) {
      const normalizedForm = {
        ...form,
        IMG_CLASS: this.normalizeLegacyIconClass(form.IMG_CLASS),
      };
      const validation = runtime.validateTemplateRecord(normalizedForm);
      const errors = this.localizeTemplateErrors(validation.errors || {});
      if (!validation.valid) {
        return {
          valid: false,
          errors,
          message: this.firstValidationMessage(errors),
        };
      }
      const record = validation.record || {};
      return {
        valid: true,
        errors: {},
        item: {
          ...form,
          ID: record.id,
          NAME: record.name,
          DESCRIPTION: record.description,
          DETAILS: record.details,
          ITEM_CLASS: record.itemClass,
          ITEM_ID: record.itemId,
          ITEM_GENRE: record.itemGenre,
          IMG_CLASS: record.imgClass,
          PRIZE: record.prize,
          CHARGE: record.charge,
        },
      };
    },
    normalizeInventoryForm(form) {
      const normalizedForm = {
        ...form,
        IMG_CLASS: this.normalizeLegacyIconClass(form.IMG_CLASS),
      };
      const validation = runtime.validateInventoryRecord(normalizedForm);
      const errors = this.localizeInventoryErrors(validation.errors || {});
      if (!validation.valid) {
        return {
          valid: false,
          errors,
          message: this.firstValidationMessage(errors),
        };
      }
      const record = validation.record || {};
      return {
        valid: true,
        errors: {},
        item: {
          ...form,
          ID: record.id,
          INV_ID: record.templateId,
          ITEM_PLACE: record.itemPlace ?? record.slot,
          SLOT: record.itemPlace ?? record.slot,
          PERSONAL_PSEU: record.personalPseu,
          PERSONAL_DESC: record.personalDesc,
          PERSONAL_COST: record.personalCost,
          QUANTITY: record.quantity,
          OWNER_OPT: record.ownerOpt,
          NAME: record.name,
          DESCRIPTION: record.description,
          IMG_CLASS: record.imgClass,
          PRIZE: record.prize,
          CHARGE: record.charge,
        },
      };
    },
    normalizeLegacyIconClass(rawClass) {
      return runtime.normalizeLegacyIconClassUtil(rawClass, {
        pattern: runtime.legacyIconClassPattern,
        max: runtime.legacyIconMax,
        namedPositions: {},
        fallback: "v0001",
      });
    },
    itemImageSrcForItem(item) {
      return runtime.resolveItemImageSource(item);
    },
    resolveItemBasePrice(item) {
      if (!item || typeof item !== "object") {
        return null;
      }
      const raw =
        item.BASE_PRICE ??
        item.basePrice ??
        item.PRIZE ??
        item.prize ??
        item.PERSONAL_COST ??
        item.personalCost;
      const parsed = Number(raw);
      return Number.isFinite(parsed) ? Math.max(0, parsed) : null;
    },
    resolveItemDetailAvailability(item) {
      if (!item || typeof item !== "object") {
        return runtime.t("shop.itemDetailDialog.noDataShort");
      }
      if (
        item.AVAILABLE === false ||
        item.available === false ||
        item.UNAVAILABLE === true ||
        item.unavailable === true
      ) {
        return runtime.t("shop.itemDetailDialog.availability.unavailable");
      }
      const quantity = Number(item.QUANTITY ?? item.quantity);
      if (Number.isFinite(quantity)) {
        if (quantity <= 0) {
          return runtime.t("shop.itemDetailDialog.availability.unavailable");
        }
        return runtime.t(
          "shop.itemDetailDialog.availability.availableQuantity",
          {
            quantity: Math.round(quantity),
          },
        );
      }
      return runtime.t("shop.itemDetailDialog.availability.available");
    },
    legacyIconClassForItem(item) {
      if (!item) {
        return "v0001";
      }
      return this.normalizeLegacyIconClass(runtime.resolveItemIconToken(item));
    },
    iconStyle(imgClass) {
      const normalized = this.normalizeLegacyIconClass(imgClass);
      const sourceIconClass =
        runtime.readIconClassRemap()[normalized] || normalized;
      const legacyPosition = runtime.legacyPositionForClass(sourceIconClass);
      if (legacyPosition) {
        return {
          backgroundImage: `url("${runtime.inventorySprite}")`,
          backgroundPosition: runtime.formatLegacyPosition(legacyPosition),
          backgroundSize: `calc(${runtime.legacyIconColumns} * var(--trade-icon-size)) auto`,
          backgroundRepeat: "no-repeat",
        };
      }
      const icon = runtime.iconMap[sourceIconClass];
      if (icon) {
        return {
          backgroundImage: `url("${icon}")`,
          backgroundSize: "contain",
          backgroundPosition: "center",
          backgroundRepeat: "no-repeat",
        };
      }
      return {};
    },
  };
};
