<template>
  <section class="item-mechanics-editor">
    <header class="item-mechanics-editor__header">
      <div>
        <h3>{{ $t("shop.workspace.mechanics.title") }}</h3>
        <p>{{ $t("shop.workspace.mechanics.description") }}</p>
      </div>
      <div class="item-mechanics-editor__header-actions">
        <label v-if="allowMode">
          <span>{{ $t("shop.workspace.mechanics.inheritanceMode") }}</span>
          <select
            :value="mode"
            @change="$emit('update:mode', $event.target.value)"
          >
            <option v-for="option in modeOptions" :key="option" :value="option">
              {{ $t(`shop.workspace.mechanics.modes.${option}`) }}
            </option>
          </select>
        </label>
        <button
          v-if="normalizedMode !== 'INHERIT'"
          type="button"
          class="primary"
          @click="addMechanic"
        >
          + {{ $t("shop.workspace.mechanics.add") }}
        </button>
      </div>
    </header>

    <div v-if="$slots.context" class="item-mechanics-editor__context">
      <slot name="context" />
    </div>

    <div class="item-mechanics-editor__body">
      <aside class="item-mechanics-editor__list">
        <button
          v-for="mechanic in visibleMechanics"
          :key="`${mechanic.source}:${mechanic.code}`"
          type="button"
          :class="{ active: selectedCode === mechanic.code }"
          @click="selectedCode = mechanic.code"
        >
          <span>
            <strong>{{ mechanic.labelPl || mechanic.code }}</strong>
            <small>{{ triggerLabel(mechanic.trigger) }}</small>
          </span>
          <em :class="`source-${String(mechanic.source).toLowerCase()}`">
            {{ sourceLabel(mechanic.source) }}
          </em>
        </button>
        <p v-if="!visibleMechanics.length">
          {{ $t("shop.workspace.mechanics.empty") }}
        </p>
      </aside>

      <div v-if="selectedOwnMechanic" class="item-mechanics-editor__form">
        <div class="item-mechanics-editor__identity">
          <label>
            <span>{{ $t("shop.workspace.mechanics.code") }}</span>
            <input
              :value="selectedOwnMechanic.code"
              @change="changeMechanicCode($event.target.value)"
            />
          </label>
          <label>
            <span>{{ $t("shop.workspace.mechanics.labelPl") }}</span>
            <input
              :value="selectedOwnMechanic.labelPl"
              @input="patchMechanic({ labelPl: $event.target.value })"
            />
          </label>
          <label>
            <span>{{ $t("shop.workspace.mechanics.labelEn") }}</span>
            <input
              :value="selectedOwnMechanic.labelEn"
              @input="patchMechanic({ labelEn: $event.target.value })"
            />
          </label>
          <label>
            <span>{{ $t("shop.workspace.mechanics.trigger") }}</span>
            <select
              :value="selectedOwnMechanic.trigger"
              @change="patchMechanic({ trigger: $event.target.value })"
            >
              <option
                v-for="option in optionCodes.triggers"
                :key="option"
                :value="option"
              >
                {{ triggerLabel(option) }}
              </option>
            </select>
          </label>
          <label>
            <span>{{ $t("shop.workspace.mechanics.handler") }}</span>
            <select
              :value="selectedOwnMechanic.handler"
              @change="patchMechanic({ handler: $event.target.value })"
            >
              <option
                v-for="option in optionCodes.handlers"
                :key="option"
                :value="option"
              >
                {{ $t(`shop.workspace.mechanics.handlers.${option}`) }}
              </option>
            </select>
          </label>
          <label>
            <span>{{ $t("shop.workspace.mechanics.actionLabel") }}</span>
            <input
              :value="selectedOwnMechanic.actionLabel"
              :placeholder="$t('shop.workspace.mechanics.actionPlaceholder')"
              @input="patchMechanic({ actionLabel: $event.target.value })"
            />
          </label>
        </div>

        <label class="item-mechanics-editor__description">
          <span>{{ $t("shop.workspace.mechanics.mechanicDescription") }}</span>
          <input
            :value="selectedOwnMechanic.description"
            @input="patchMechanic({ description: $event.target.value })"
          />
        </label>

        <div class="item-mechanics-editor__rules">
          <fieldset>
            <legend>
              <label>
                <input
                  type="checkbox"
                  :checked="selectedOwnMechanic.check.enabled"
                  @change="
                    patchNested('check', {
                      enabled: $event.target.checked,
                    })
                  "
                />
                {{ $t("shop.workspace.mechanics.test") }}
              </label>
            </legend>
            <div>
              <label>
                <span>{{ $t("shop.workspace.mechanics.formula") }}</span>
                <input
                  :value="selectedOwnMechanic.check.formula"
                  :disabled="!selectedOwnMechanic.check.enabled"
                  placeholder="1d100"
                  @input="
                    patchNested('check', { formula: $event.target.value })
                  "
                />
              </label>
              <label>
                <span>{{ $t("shop.workspace.mechanics.targetKey") }}</span>
                <input
                  :value="selectedOwnMechanic.check.targetKey"
                  :disabled="!selectedOwnMechanic.check.enabled"
                  placeholder="WS / BS / DEX"
                  @input="
                    patchNested('check', { targetKey: $event.target.value })
                  "
                />
              </label>
              <label>
                <span>{{ $t("shop.workspace.mechanics.difficulty") }}</span>
                <input
                  type="number"
                  min="-100"
                  max="100"
                  :value="selectedOwnMechanic.check.difficulty"
                  :disabled="!selectedOwnMechanic.check.enabled"
                  @input="
                    patchNested('check', {
                      difficulty: Number($event.target.value || 0),
                    })
                  "
                />
              </label>
            </div>
          </fieldset>

          <fieldset>
            <legend>{{ $t("shop.workspace.mechanics.cost") }}</legend>
            <div>
              <label>
                <span>{{ $t("shop.workspace.mechanics.quantityCost") }}</span>
                <input
                  type="number"
                  min="0"
                  :value="selectedOwnMechanic.cost.quantity"
                  @input="
                    patchNested('cost', {
                      quantity: Number($event.target.value || 0),
                    })
                  "
                />
              </label>
              <label>
                <span>{{ $t("shop.workspace.mechanics.chargeCost") }}</span>
                <input
                  type="number"
                  min="0"
                  :value="selectedOwnMechanic.cost.charges"
                  @input="
                    patchNested('cost', {
                      charges: Number($event.target.value || 0),
                    })
                  "
                />
              </label>
              <label>
                <span>{{ $t("shop.workspace.mechanics.resourceCost") }}</span>
                <input
                  :value="selectedOwnMechanic.cost.resourceValue"
                  placeholder="MANA:1"
                  @input="
                    patchNested('cost', {
                      resourceValue: $event.target.value,
                    })
                  "
                />
              </label>
            </div>
          </fieldset>
        </div>

        <div class="item-mechanics-editor__effects">
          <header>
            <strong>{{ $t("shop.workspace.mechanics.effects") }}</strong>
            <button type="button" @click="addEffect">
              + {{ $t("shop.workspace.mechanics.addEffect") }}
            </button>
          </header>
          <div
            v-for="(effect, index) in selectedOwnMechanic.effects"
            :key="index"
            class="item-mechanics-editor__effect"
          >
            <select
              :value="effect.when"
              :aria-label="$t('shop.workspace.mechanics.condition')"
              @change="patchEffect(index, { when: $event.target.value })"
            >
              <option
                v-for="option in optionCodes.conditions"
                :key="option"
                :value="option"
              >
                {{ $t(`shop.workspace.mechanics.conditions.${option}`) }}
              </option>
            </select>
            <select
              :value="effect.type"
              :aria-label="$t('shop.workspace.mechanics.effectType')"
              @change="patchEffect(index, { type: $event.target.value })"
            >
              <option
                v-for="option in optionCodes.effectTypes"
                :key="option"
                :value="option"
              >
                {{ $t(`shop.workspace.mechanics.effectTypes.${option}`) }}
              </option>
            </select>
            <select
              :value="effect.target"
              :aria-label="$t('shop.workspace.mechanics.effectTarget')"
              @change="patchEffect(index, { target: $event.target.value })"
            >
              <option
                v-for="option in optionCodes.targets"
                :key="option"
                :value="option"
              >
                {{ $t(`shop.workspace.mechanics.targets.${option}`) }}
              </option>
            </select>
            <input
              :value="effect.value"
              :aria-label="$t('shop.workspace.mechanics.effectValue')"
              :placeholder="$t('shop.workspace.mechanics.valuePlaceholder')"
              @input="patchEffect(index, { value: $event.target.value })"
            />
            <input
              :value="effect.description"
              :aria-label="$t('shop.workspace.mechanics.effectDescription')"
              :placeholder="$t('shop.workspace.mechanics.effectDescription')"
              @input="patchEffect(index, { description: $event.target.value })"
            />
            <button
              type="button"
              class="danger"
              :title="$t('actions.remove')"
              @click="removeEffect(index)"
            >
              ×
            </button>
          </div>
          <p v-if="!selectedOwnMechanic.effects.length">
            {{ $t("shop.workspace.mechanics.noEffects") }}
          </p>
        </div>

        <details class="item-mechanics-editor__extension">
          <summary>{{ $t("shop.workspace.mechanics.extension") }}</summary>
          <div>
            <label>
              <span>{{ $t("shop.workspace.mechanics.handlerKey") }}</span>
              <input
                :value="selectedOwnMechanic.handlerKey"
                placeholder="module.action"
                @input="patchMechanic({ handlerKey: $event.target.value })"
              />
            </label>
            <label>
              <span>{{ $t("shop.workspace.mechanics.parameters") }}</span>
              <textarea
                :value="parametersText"
                rows="2"
                @change="updateParameters($event.target.value)"
              />
            </label>
          </div>
          <p v-if="parametersError" role="alert">{{ parametersError }}</p>
        </details>

        <footer>
          <button type="button" class="danger" @click="removeMechanic">
            {{ $t("shop.workspace.mechanics.remove") }}
          </button>
        </footer>
      </div>

      <div v-else-if="selectedInheritedMechanic" class="item-mechanics-preview">
        <span>{{ sourceLabel(selectedInheritedMechanic.source) }}</span>
        <h4>
          {{
            selectedInheritedMechanic.labelPl || selectedInheritedMechanic.code
          }}
        </h4>
        <p>{{ selectedInheritedMechanic.description || "—" }}</p>
        <dl>
          <dt>{{ $t("shop.workspace.mechanics.trigger") }}</dt>
          <dd>{{ triggerLabel(selectedInheritedMechanic.trigger) }}</dd>
          <dt>{{ $t("shop.workspace.mechanics.handler") }}</dt>
          <dd>
            {{
              $t(
                `shop.workspace.mechanics.handlers.${selectedInheritedMechanic.handler}`,
              )
            }}
          </dd>
          <dt>{{ $t("shop.workspace.mechanics.effects") }}</dt>
          <dd>{{ selectedInheritedMechanic.effects.length }}</dd>
        </dl>
        <button
          v-if="mode !== 'INHERIT'"
          type="button"
          class="primary"
          @click="overrideInherited"
        >
          {{ $t("shop.workspace.mechanics.overrideHere") }}
        </button>
      </div>

      <div v-else class="item-mechanics-editor__placeholder">
        <strong>{{ $t("shop.workspace.mechanics.selectOrAdd") }}</strong>
        <p>{{ $t("shop.workspace.mechanics.futureHint") }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import i18n from "@/i18n";
import {
  createItemEffect,
  createItemMechanic,
  mechanicOptionCodes,
  mechanicsMode,
  normalizeItemMechanics,
} from "@/lib/trade/itemMechanics";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  inherited: { type: Array, default: () => [] },
  mode: { type: String, default: "EXTEND" },
  allowMode: { type: Boolean, default: false },
  ownSource: { type: String, default: "TEMPLATE" },
});
const emit = defineEmits(["update:modelValue", "update:mode", "change"]);
const t = (key) => i18n.global.t(key);
const selectedCode = ref("");
const parametersError = ref("");
const optionCodes = mechanicOptionCodes;
const modeOptions = mechanicOptionCodes.modes;
const ownMechanics = computed(() => normalizeItemMechanics(props.modelValue));
const normalizedMode = computed(() => mechanicsMode(props.mode));
const inheritedMechanics = computed(() =>
  normalizeItemMechanics(props.inherited).map((entry, index) => ({
    ...entry,
    source: props.inherited[index]?.source || "CLASS",
  })),
);
const visibleMechanics = computed(() => {
  const values = new Map();
  if (normalizedMode.value !== "REPLACE") {
    inheritedMechanics.value.forEach((entry) => values.set(entry.code, entry));
  }
  if (normalizedMode.value !== "INHERIT") {
    ownMechanics.value.forEach((entry) => {
      if (!entry.enabled) values.delete(entry.code);
      else values.set(entry.code, { ...entry, source: props.ownSource });
    });
  }
  return [...values.values()];
});
const selectedOwnMechanic = computed(() =>
  normalizedMode.value === "INHERIT"
    ? null
    : ownMechanics.value.find((entry) => entry.code === selectedCode.value),
);
const selectedInheritedMechanic = computed(() =>
  inheritedMechanics.value.find((entry) => entry.code === selectedCode.value),
);
const parametersText = computed(() =>
  JSON.stringify(selectedOwnMechanic.value?.parameters || {}, null, 2),
);

watch(
  visibleMechanics,
  (values) => {
    if (
      selectedCode.value &&
      values.some((entry) => entry.code === selectedCode.value)
    ) {
      return;
    }
    selectedCode.value = values[0]?.code || ownMechanics.value[0]?.code || "";
  },
  { immediate: true },
);

const updateValues = (values) => {
  emit("update:modelValue", normalizeItemMechanics(values));
  emit("change");
};
const nextCode = () => {
  const used = new Set(ownMechanics.value.map((entry) => entry.code));
  let index = 1;
  while (used.has(`MECHANIC_${index}`)) index += 1;
  return `MECHANIC_${index}`;
};
const addMechanic = () => {
  const code = nextCode();
  updateValues([
    ...ownMechanics.value,
    createItemMechanic(code, {
      labelPl: t("shop.workspace.mechanics.newMechanic"),
      labelEn: "New mechanic",
    }),
  ]);
  selectedCode.value = code;
};
const patchMechanic = (patch) => {
  updateValues(
    ownMechanics.value.map((entry) =>
      entry.code === selectedCode.value ? { ...entry, ...patch } : entry,
    ),
  );
};
const patchNested = (key, patch) => {
  const current = selectedOwnMechanic.value?.[key] || {};
  patchMechanic({ [key]: { ...current, ...patch } });
};
const changeMechanicCode = (value) => {
  const normalized = String(value || "")
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9_]/gu, "_");
  if (
    !/^[A-Z][A-Z0-9_]{0,63}$/u.test(normalized) ||
    ownMechanics.value.some(
      (entry) => entry.code === normalized && entry.code !== selectedCode.value,
    )
  ) {
    return;
  }
  const previous = selectedCode.value;
  updateValues(
    ownMechanics.value.map((entry) =>
      entry.code === previous ? { ...entry, code: normalized } : entry,
    ),
  );
  selectedCode.value = normalized;
};
const removeMechanic = () => {
  updateValues(
    ownMechanics.value.filter((entry) => entry.code !== selectedCode.value),
  );
  selectedCode.value = "";
};
const addEffect = () => {
  patchMechanic({
    effects: [
      ...(selectedOwnMechanic.value?.effects || []),
      createItemEffect({
        when: selectedOwnMechanic.value?.check.enabled ? "SUCCESS" : "ALWAYS",
      }),
    ],
  });
};
const patchEffect = (index, patch) => {
  patchMechanic({
    effects: selectedOwnMechanic.value.effects.map((effect, effectIndex) =>
      effectIndex === index ? { ...effect, ...patch } : effect,
    ),
  });
};
const removeEffect = (index) => {
  patchMechanic({
    effects: selectedOwnMechanic.value.effects.filter(
      (_, effectIndex) => effectIndex !== index,
    ),
  });
};
const overrideInherited = () => {
  if (!selectedInheritedMechanic.value) return;
  const mechanic = createItemMechanic(selectedInheritedMechanic.value.code, {
    ...selectedInheritedMechanic.value,
  });
  delete mechanic.source;
  updateValues([
    ...ownMechanics.value.filter((entry) => entry.code !== mechanic.code),
    mechanic,
  ]);
  selectedCode.value = mechanic.code;
};
const updateParameters = (value) => {
  try {
    const parsed = JSON.parse(value || "{}");
    if (!parsed || Array.isArray(parsed) || typeof parsed !== "object") {
      throw new Error("parameters");
    }
    parametersError.value = "";
    patchMechanic({ parameters: parsed });
  } catch {
    parametersError.value = t("shop.workspace.mechanics.invalidParameters");
  }
};
const triggerLabel = (code) => t(`shop.workspace.mechanics.triggers.${code}`);
const sourceLabel = (source) =>
  t(`shop.workspace.mechanics.sources.${source || "TEMPLATE"}`);
</script>

<style src="./ItemMechanicsEditor.css"></style>
