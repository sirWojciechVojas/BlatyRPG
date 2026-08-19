export const createContainersMethodsPart3Segment3 = (runtime) => {
  return {
    similarStackCount(items = []) {
      const groups = new Map();
      items.forEach((item) => {
        const key = String(item?.stackCandidateKey || "");
        if (!key) {
          return;
        }
        groups.set(key, (groups.get(key) || 0) + 1);
      });
      return new Map(
        Array.from(groups.entries()).filter(([, count]) => Number(count) > 1),
      );
    },
    resolveAssortmentMergeItemData(item) {
      if (!item || item.type !== "instance") {
        return null;
      }
      const instance = this.getInstanceById(item.instanceId);
      if (!instance) {
        return null;
      }
      const template = this.getTemplateById(instance.templateId);
      const meta = this.containerInstanceMeta[instance.id] || {};
      const personalCost = Number(meta.PERSONAL_COST ?? meta.PRIZE ?? 0);
      const quantity = Number(meta.QUANTITY);
      const normalizedQuantity = Number.isFinite(quantity)
        ? Math.max(1, Math.round(quantity))
        : 1;
      return {
        instanceId: Number(instance.id),
        templateId: Number(instance.templateId),
        INV_ID: Number(instance.templateId),
        NAME: String(
          runtime.normalizeMergeValue(
            meta.NAME || instance.nameOverride || template?.name,
            `Przedmiot ${instance.id}`,
          ),
        ),
        ITEM_PLACE: String(
          runtime.normalizeMergeValue(
            runtime.resolveItemPlace(meta, "STOISKO"),
            "STOISKO",
          ),
        ),
        SLOT: String(
          runtime.normalizeMergeValue(
            runtime.resolveItemPlace(meta, "STOISKO"),
            "STOISKO",
          ),
        ),
        PERSONAL_PSEU: String(
          runtime.normalizeMergeValue(
            meta.PERSONAL_PSEU ||
              meta.NAME ||
              instance.nameOverride ||
              template?.name,
            `Przedmiot ${instance.id}`,
          ),
        ),
        PERSONAL_DESC: String(
          runtime.normalizeMergeValue(
            meta.PERSONAL_DESC ||
              instance.note ||
              template?.baseData?.DESCRIPTION,
            "",
          ),
        ),
        PERSONAL_COST: Number.isFinite(personalCost) ? personalCost : 0,
        DESCRIPTION: String(
          runtime.normalizeMergeValue(
            meta.DESCRIPTION || template?.baseData?.DESCRIPTION,
            "",
          ),
        ),
        IMG_CLASS: String(
          runtime.normalizeMergeValue(
            meta.IMG_CLASS || template?.baseData?.IMG_CLASS || "v0001",
            "v0001",
          ),
        ),
        PRIZE: Number(
          runtime.normalizeMergeValue(
            meta.PRIZE,
            Number(template?.baseData?.PRIZE ?? 0),
          ),
        ),
        CHARGE: Number(
          runtime.normalizeMergeValue(
            meta.CHARGE,
            Number(template?.baseData?.CHARGE ?? 0),
          ),
        ),
        QUANTITY: normalizedQuantity,
      };
    },
    assortmentMergeChoiceFor(fieldKey) {
      return this.assortmentMergeChoices?.[fieldKey] === "right"
        ? "right"
        : "left";
    },
  };
};
