export const createEditorViewModelPart2 = (runtime) => {
  const updatePricingConfig = (nextConfig) => {
    runtime.updateField(
      "pricingConfig",
      runtime.normalizeShopPricingConfig(nextConfig),
    );
  };
  Object.assign(runtime, {
    updatePricingConfig,
  });
  const updatePricingPolicyField = ({ path, value }) => {
    const segments = String(path || "")
      .split(".")
      .filter(Boolean);
    if (!segments.length) {
      return;
    }
    const current = runtime.currentPricingConfig();
    const next = {
      ...current,
    };
    let cursor = next;
    let source = current;
    segments.forEach((segment, index) => {
      const isLast = index === segments.length - 1;
      if (isLast) {
        if (value === null) {
          delete cursor[segment];
        } else {
          cursor[segment] = value;
        }
        return;
      }
      const branch =
        source?.[segment] && typeof source[segment] === "object"
          ? source[segment]
          : {};
      cursor[segment] = Array.isArray(branch)
        ? [...branch]
        : {
            ...branch,
          };
      cursor = cursor[segment];
      source = branch;
    });
    if (!String(path).startsWith("currencyPolicy.")) {
      next.policyId = "custom";
    }
    runtime.updatePricingConfig(next);
  };
  Object.assign(runtime, {
    updatePricingPolicyField,
  });
  const applyPricingPreset = (presetId) => {
    runtime.updatePricingConfig(
      runtime.applyShopPricingPreset(presetId, runtime.currentPricingConfig()),
    );
  };
  Object.assign(runtime, {
    applyPricingPreset,
  });
  const addPricingRule = () => {
    const current = runtime.currentPricingConfig();
    const sequence = current.rules.length + 1;
    const rule = runtime.createShopPricingRule({
      id: `pricing-rule-${Date.now()}-${sequence}`,
      name: runtime.t("shop.shopEditor.pricing.exceptions.defaultName", {
        number: sequence,
      }),
      priority: sequence * 10,
    });
    runtime.updatePricingConfig({
      ...current,
      policyId: "custom",
      rules: [...current.rules, rule],
    });
  };
  Object.assign(runtime, {
    addPricingRule,
  });
  const updatePricingRule = ({ id, patch = {} }) => {
    const current = runtime.currentPricingConfig();
    const rules = current.rules.map((rule) => {
      if (rule.id !== id) {
        return rule;
      }
      return {
        ...rule,
        ...patch,
        match: patch.match
          ? {
              ...rule.match,
              ...patch.match,
            }
          : rule.match,
        effect: patch.effect
          ? {
              ...rule.effect,
              ...patch.effect,
            }
          : rule.effect,
      };
    });
    runtime.updatePricingConfig({
      ...current,
      policyId: "custom",
      rules,
    });
  };
  Object.assign(runtime, {
    updatePricingRule,
  });
  const removePricingRule = (ruleId) => {
    const current = runtime.currentPricingConfig();
    runtime.updatePricingConfig({
      ...current,
      policyId: "custom",
      rules: current.rules.filter((rule) => rule.id !== ruleId),
    });
  };
  const updatePolicyPresets = (policies) => {
    runtime.updateField("customPresets", {
      ...(runtime.ctx.shopEditorForm?.customPresets || {}),
      profiles: runtime.ctx.shopEditorForm?.customPresets?.profiles || [],
      policies,
    });
  };
  const savePolicyPreset = (name) => {
    const normalizedName = String(name || "").trim();
    if (!normalizedName) return;
    const current = runtime.currentPricingConfig();
    const existing = runtime.ctx.shopEditorForm?.customPresets?.policies || [];
    updatePolicyPresets([
      ...existing.filter(
        (entry) =>
          entry.name.toLocaleLowerCase("pl") !==
          normalizedName.toLocaleLowerCase("pl"),
      ),
      {
        id: `policy-${Date.now()}`,
        name: normalizedName,
        values: JSON.parse(JSON.stringify(current)),
      },
    ]);
  };
  const applyPolicyPreset = (preset) => {
    if (preset?.values) runtime.updatePricingConfig(preset.values);
  };
  const removePolicyPreset = (id) => {
    updatePolicyPresets(
      (runtime.ctx.shopEditorForm?.customPresets?.policies || []).filter(
        (entry) => entry.id !== id,
      ),
    );
  };
  Object.assign(runtime, {
    removePricingRule,
    savePolicyPreset,
    applyPolicyPreset,
    removePolicyPreset,
  });
  const updatePreviewTemplate = (value) => {
    const nextId = Number(value);
    runtime.previewTemplateId.value = Number.isFinite(nextId) ? nextId : null;
  };
  const updatePreviewInput = ({ field, value }) => {
    const target = {
      quantity: runtime.previewQuantity,
      condition: runtime.previewCondition,
      reputation: runtime.previewReputation,
      mode: runtime.previewMode,
      temporaryModifier: runtime.previewTemporaryModifier,
      quickMode: runtime.previewQuickMode,
    }[field];
    if (target) target.value = value;
  };
  Object.assign(runtime, {
    updatePreviewTemplate,
    updatePreviewInput,
  });
  const saveProfile = async () => {
    runtime.attemptedSuggestionGeneration.value = false;
    await runtime.ctx.saveShopEditorProfile();
  };
  Object.assign(runtime, {
    saveProfile,
  });
  const createShop = async () => {
    runtime.attemptedSuggestionGeneration.value = false;
    await runtime.ctx.handleCreateShopForEditor();
  };
  Object.assign(runtime, {
    createShop,
  });
  const changeShop = (value) => {
    runtime.attemptedSuggestionGeneration.value = false;
    runtime.ctx.handleShopEditorShopChange(value);
  };
  Object.assign(runtime, {
    changeShop,
  });
  const generateSuggestions = async () => {
    runtime.attemptedSuggestionGeneration.value = true;
    if (runtime.missingSuggestionFields.value.length) {
      if (typeof runtime.ctx.showWalletAlert === "function") {
        runtime.ctx.showWalletAlert(
          runtime.t("shop.shopEditor.validation.fillRequired", {
            fields: runtime.missingSuggestionFieldLabels.value.join(", "),
          }),
        );
      }
      return;
    }
    await runtime.ctx.generateShopSuggestions();
  };
  Object.assign(runtime, {
    generateSuggestions,
  });
  const openActivationDialog = () => {
    if (typeof runtime.ctx.openShopActivationDialogForEditor === "function") {
      runtime.ctx.openShopActivationDialogForEditor();
    }
  };
  Object.assign(runtime, {
    openActivationDialog,
  });
  return {
    updatePricingConfig,
    updatePricingPolicyField,
    applyPricingPreset,
    addPricingRule,
    updatePricingRule,
    removePricingRule,
    savePolicyPreset,
    applyPolicyPreset,
    removePolicyPreset,
    updatePreviewTemplate,
    updatePreviewInput,
    saveProfile,
    createShop,
    changeShop,
    generateSuggestions,
    openActivationDialog,
  };
};
