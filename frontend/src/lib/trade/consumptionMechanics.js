import {
  createItemEffect,
  createItemMechanic,
} from "@/lib/trade/itemMechanics";

const numberText = (value) => {
  const normalized = Number(value || 0);
  return Number.isFinite(normalized) ? normalized : 0;
};

const difficultyFromTest = (value) => {
  const match = String(value || "").match(/([+-]\d+)%/u);
  return match ? Number(match[1]) : 0;
};

/**
 * A catalogue profile remains the source of truth. These are read-only
 * presentation mechanics for the generic mechanics editor, never mechanics
 * copied into a template record.
 */
export const consumptionProfileMechanics = (profile) => {
  if (!profile?.id) return [];
  const profileCode = String(profile.id)
    .toUpperCase()
    .replace(/[^A-Z0-9_]/gu, "_");

  const effects = [];
  const satiety = numberText(profile.satietyHours);
  const hydration = numberText(profile.hydrationHours);
  if (satiety) {
    effects.push(
      createItemEffect({
        type: "RESOURCE",
        value: `+${satiety} h`,
        description: `Sytość: +${satiety} h.`,
      }),
    );
  }
  if (hydration) {
    effects.push(
      createItemEffect({
        type: "RESOURCE",
        value: `+${hydration} h`,
        description: `Nawodnienie: +${hydration} h.`,
      }),
    );
  }
  if (profile.effect) {
    effects.push(
      createItemEffect({
        type: "CUSTOM",
        value: profile.effectId || "",
        duration: profile.effectWindow || "",
        description: profile.effect,
      }),
    );
  }
  if (profile.risk && profile.risk !== "Brak") {
    effects.push(
      createItemEffect({
        type: "CUSTOM",
        value: profile.negativeTest || "",
        description: [
          `Ryzyko: ${profile.risk}.`,
          profile.failureConsequence || "",
        ]
          .filter(Boolean)
          .join(" "),
      }),
    );
  }

  const preparation = profile.requiresPreparation
    ? " Wymaga przygotowania przed spożyciem."
    : "";
  return [
    {
      ...createItemMechanic(`CATALOG_CONSUME_${profileCode}`, {
        labelPl: `Spożycie: ${profile.name || profile.id}`,
        labelEn: `Consumption: ${profile.name || profile.id}`,
        trigger: "CONSUME",
        handler: "CONSUME",
        actionLabel: profile.actionLabel || "Spożyj",
        description:
          [
            profile.description || "",
            `Czas spożycia: ${profile.consumeTime || "—"}.`,
            profile.usableInCombat
              ? "Możliwe podczas walki."
              : "Niedostępne podczas walki.",
          ]
            .filter(Boolean)
            .join(" ") + preparation,
        check: {
          enabled: Boolean(
            profile.negativeTest && profile.negativeTest !== "—",
          ),
          formula: "1d100",
          targetKey: /odp|mocna głowa/iu.test(profile.negativeTest || "")
            ? "ODP"
            : "",
          difficulty: difficultyFromTest(profile.negativeTest),
        },
        effects,
        cost: { quantity: 1 },
        handlerKey: "consumption.catalog",
        parameters: {
          consumptionProfileId: profile.id,
          effectId: profile.effectId || "",
          generated: true,
        },
      }),
      source: "CONSUMPTION",
    },
  ];
};

const isGeneratedConsumptionMechanic = (mechanic) =>
  mechanic?.handlerKey === "consumption.catalog" &&
  mechanic?.parameters?.generated === true;

/**
 * Keeps one generated catalogue reference inside a template's own MECHANICS.
 * Custom mechanics remain untouched when the profile changes or is cleared.
 */
export const syncConsumptionProfileMechanics = (mechanics, profile) => [
  ...(Array.isArray(mechanics) ? mechanics : []).filter(
    (mechanic) => !isGeneratedConsumptionMechanic(mechanic),
  ),
  ...consumptionProfileMechanics(profile).map((mechanic) => {
    const templateMechanic = { ...mechanic };
    delete templateMechanic.source;
    return templateMechanic;
  }),
];
