<template>
  <div class="scene-token-layer">
    <TokenDragIndicator
      :scene="scene"
      :indicator="dragIndicator"
      :scale="scale"
    />
    <div
      v-for="token in tokens"
      :key="token.id"
      class="scene-token-wrap"
      :class="{
        'scene-token-wrap--dragging': drag?.token.id === token.id,
        'scene-token-wrap--moving': movingTokenIds[token.id],
        'scene-token-wrap--selected': tokenStates[token.id].selected,
      }"
      :style="tokenStyle(token)"
      @transitionstart="startMotion($event, token.id)"
      @transitionend="finishMotion($event, token.id)"
      @transitioncancel="finishMotion($event, token.id)"
    >
      <i
        class="scene-token-facing"
        :style="facingStyle(token)"
        aria-hidden="true"
      />
      <button
        type="button"
        class="scene-token"
        :class="[
          `scene-token--${token.disposition}`,
          stateClasses(tokenStates[token.id]),
        ]"
        :style="{ transform: `rotate(${token.rotation}deg)` }"
        :aria-label="stateLabel(token, tokenStates[token.id])"
        :title="stateLabel(token, tokenStates[token.id])"
        :aria-pressed="tokenStates[token.id].selected"
        :aria-disabled="
          tokenStates[token.id].disabled ||
          tokenStates[token.id].uncontrolled ||
          tokenStates[token.id].locked
        "
        @pointerdown.stop="startDrag($event, token)"
        @pointerenter="hoveredTokenId = token.id"
        @pointerleave="hoveredTokenId = null"
        @click.stop="selectToken($event, token.id)"
      >
        <img
          v-if="token.imageUrl"
          :src="token.imageUrl"
          alt=""
          draggable="false"
        />
        <span v-else>{{ initials(token.name) }}</span>
        <small>{{ token.name }}</small>
      </button>
      <TokenStateOverlay :flags="tokenStates[token.id]" />
      <TokenHud
        v-if="token.id === selectedId"
        :token="token"
        :busy="busy"
        @pointerdown.stop
        @rotate="
          $emit('update', {
            token,
            changes: rotatedFacing(token, $event),
          })
        "
        @open-actor="$emit('open-actor', $event)"
        @delete="$emit('delete', token)"
      />
    </div>
  </div>
</template>

<script>
import TokenHud from "./TokenHud.vue";
import TokenDragIndicator from "./TokenDragIndicator.vue";
import TokenStateOverlay from "./TokenStateOverlay.vue";
import { tokenDragMethods } from "./tokenDragMethods";
import { buildTokenDragIndicator } from "./tokenDragIndicator";
import {
  pendingTokenPositionResolved,
  tokenTravelDuration,
} from "./tokenMotion";
import { rotateTokenFacing, tokenFacingStyle } from "@/lib/vtt/tokenFacing";
import {
  activeTokenUiStates,
  tokenUiClasses,
  tokenUiFlags,
} from "@/lib/vtt/tokenUiState";

export default {
  name: "SceneTokenLayer",
  components: { TokenDragIndicator, TokenHud, TokenStateOverlay },
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    selectedIds: { type: Array, default: () => [] },
    activeTurnId: { type: [Number, String], default: null },
    waitingTurnIds: { type: Array, default: () => [] },
    targetedIds: { type: Array, default: () => [] },
    scale: { type: Number, default: 1 },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "move", "update", "delete", "open-actor"],
  data: () => ({
    drag: null,
    preview: {},
    pendingPositions: {},
    pendingTimers: new Map(),
    movingTokenIds: {},
    motionDurations: {},
    hoveredTokenId: null,
  }),
  computed: {
    effectiveSelectedIds() {
      return this.selectedIds.length
        ? this.selectedIds
        : this.selectedId === null
          ? []
          : [this.selectedId];
    },
    tokenStates() {
      return Object.fromEntries(
        this.tokens.map((token) => [
          token.id,
          tokenUiFlags(token, {
            selectedIds: this.effectiveSelectedIds,
            hoveredId: this.hoveredTokenId,
            draggingId: this.drag?.token.id,
            activeTurnId: this.activeTurnId,
            waitingTurnIds: this.waitingTurnIds,
            targetedIds: this.targetedIds,
            disabled: this.busy,
          }),
        ]),
      );
    },
    dragIndicator() {
      if (!this.drag) return null;
      return buildTokenDragIndicator(
        this.scene,
        this.drag.token,
        this.preview[this.drag.token.id] || this.drag.token,
      );
    },
  },
  watch: {
    tokens: {
      deep: true,
      handler(tokens, previousTokens = []) {
        const durations = { ...this.motionDurations };
        tokens.forEach((token) => {
          const previous = previousTokens.find((item) => item.id === token.id);
          if (previous && (previous.x !== token.x || previous.y !== token.y)) {
            durations[token.id] = tokenTravelDuration(previous, token);
          }
        });
        Object.keys(durations).forEach((id) => {
          if (!tokens.some((token) => token.id === Number(id)))
            delete durations[id];
        });
        this.motionDurations = durations;
        this.syncPendingPositions(tokens);
      },
    },
  },
  beforeUnmount() {
    this.cancelDrag();
    this.pendingTimers.forEach((timer) => window.clearTimeout(timer));
    this.pendingTimers.clear();
  },
  methods: {
    ...tokenDragMethods,
    facingStyle: tokenFacingStyle,
    rotatedFacing: rotateTokenFacing,
    stateClasses: tokenUiClasses,
    selectToken(event, tokenId) {
      this.$emit("select", {
        tokenId,
        additive: event.ctrlKey || event.metaKey || event.shiftKey,
      });
    },
    stateLabel(token, flags) {
      const labels = activeTokenUiStates(flags)
        .filter((state) => state !== "default")
        .map((state) => this.$t(`vtt.token.states.${state}`));
      return labels.length
        ? `${token.name} — ${labels.join(", ")}`
        : token.name;
    },
    tokenStyle(token) {
      const position =
        this.preview[token.id] || this.pendingPositions[token.id] || token;
      return {
        width: `${token.width}px`,
        height: `${token.height}px`,
        transform: `translate(${position.x}px, ${position.y}px)`,
        zIndex: String(100 + Math.round(token.elevation || 0)),
        "--token-travel-duration": `${this.motionDurations[token.id] || 460}ms`,
      };
    },
    holdTokenPosition(token, position) {
      const tokenId = Number(token.id);
      window.clearTimeout(this.pendingTimers.get(tokenId));
      this.pendingPositions = {
        ...this.pendingPositions,
        [tokenId]: {
          x: Number(position.x),
          y: Number(position.y),
          revision: Number(token.revision),
        },
      };
      this.pendingTimers.set(
        tokenId,
        window.setTimeout(() => this.releasePendingPosition(tokenId), 3000),
      );
    },
    syncPendingPositions(tokens) {
      Object.entries(this.pendingPositions).forEach(([id, pending]) => {
        const token = tokens.find((item) => item.id === Number(id));
        if (pendingTokenPositionResolved(pending, token)) {
          this.releasePendingPosition(id);
        }
      });
    },
    releasePendingPosition(tokenId) {
      window.clearTimeout(this.pendingTimers.get(Number(tokenId)));
      this.pendingTimers.delete(Number(tokenId));
      const pendingPositions = { ...this.pendingPositions };
      delete pendingPositions[tokenId];
      this.pendingPositions = pendingPositions;
    },
    startMotion(event, tokenId) {
      if (
        event.target !== event.currentTarget ||
        event.propertyName !== "transform"
      ) {
        return;
      }
      this.movingTokenIds = { ...this.movingTokenIds, [tokenId]: true };
    },
    finishMotion(event, tokenId) {
      if (
        event.target !== event.currentTarget ||
        event.propertyName !== "transform"
      ) {
        return;
      }
      const movingTokenIds = { ...this.movingTokenIds };
      delete movingTokenIds[tokenId];
      this.movingTokenIds = movingTokenIds;
    },
    initials(name) {
      return String(name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
  },
};
</script>
