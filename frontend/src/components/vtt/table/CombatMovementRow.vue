<template>
  <article
    class="combat-movement-row"
    :class="{ 'combat-movement-row--expanded': expanded }"
  >
    <div class="combat-movement-row__summary">
      <button
        v-if="showRoster"
        type="button"
        class="combat-movement-row__roster"
        :class="{ active: inRoster }"
        :title="$t('vtt.table.combat.toggleRoster')"
        :aria-pressed="inRoster"
        :disabled="busy"
        @click="$emit('toggle')"
      >
        {{ inRoster ? "✓" : "+" }}
      </button>
      <span
        v-else
        class="combat-movement-row__roster-spacer"
        aria-hidden="true"
      />
      <CombatTokenIdentity :token="token" />
      <CombatMovementBar
        :points="draft.points"
        :range="draft.range"
        :prefix="$t('vtt.table.combat.movementShort')"
        :label="movementLabel"
        :color="movementColor"
      />
      <button
        type="button"
        class="combat-movement-row__expand"
        :title="$t('vtt.table.combat.editMovement')"
        :aria-label="
          $t('vtt.table.combat.editMovementFor', { name: token.name })
        "
        :aria-expanded="expanded"
        :disabled="busy"
        @click="$emit('expand')"
      >
        {{ expanded ? "⌃" : "•••" }}
      </button>
    </div>

    <div v-if="expanded" class="combat-movement-row__editor">
      <label>
        <span>{{ $t("vtt.table.combat.points") }}</span>
        <input
          :value="draft.points"
          type="number"
          min="0"
          :max="draft.range"
          step="0.5"
          :disabled="busy"
          @input="emitEdit('points', $event.target.value)"
        />
      </label>
      <label>
        <span>{{ $t("vtt.table.combat.range") }}</span>
        <input
          :value="draft.range"
          type="number"
          min="0"
          max="10000"
          step="0.5"
          :disabled="busy"
          @input="emitEdit('range', $event.target.value)"
        />
      </label>
      <label class="combat-movement-row__reset-mode">
        <span>{{ $t("vtt.table.combat.resetMode") }}</span>
        <select
          :value="draft.resetMode"
          :disabled="busy"
          @change="emitEdit('resetMode', $event.target.value)"
        >
          <option value="turn">{{ $t("vtt.token.movement.turn") }}</option>
          <option value="round">{{ $t("vtt.token.movement.round") }}</option>
          <option value="manual">{{ $t("vtt.token.movement.manual") }}</option>
        </select>
      </label>
      <div class="combat-movement-row__actions">
        <button type="button" :disabled="busy" @click="$emit('reset')">
          ↺ {{ $t("vtt.table.combat.resetOne") }}
        </button>
        <button
          type="button"
          class="combat-movement-row__save"
          :disabled="busy"
          @click="$emit('save')"
        >
          {{ $t("vtt.table.combat.assign") }}
        </button>
      </div>
    </div>
  </article>
</template>

<script>
import CombatMovementBar from "./CombatMovementBar.vue";
import CombatTokenIdentity from "./CombatTokenIdentity.vue";
import { tokenMovementColor } from "@/lib/vtt/combatPresentation";

export default {
  name: "CombatMovementRow",
  components: { CombatMovementBar, CombatTokenIdentity },
  props: {
    token: { type: Object, required: true },
    draft: { type: Object, required: true },
    expanded: { type: Boolean, default: false },
    inRoster: { type: Boolean, default: false },
    showRoster: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["toggle", "expand", "edit", "reset", "save"],
  computed: {
    movementColor() {
      return tokenMovementColor(this.token);
    },
    movementLabel() {
      return this.$t("vtt.table.combat.movementValue", {
        points: this.draft.points,
        range: this.draft.range,
      });
    },
  },
  methods: {
    emitEdit(key, value) {
      this.$emit("edit", { key, value });
    },
  },
};
</script>
