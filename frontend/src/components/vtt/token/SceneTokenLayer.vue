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
        'scene-token-wrap--selected': token.id === selectedId,
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
          {
            'scene-token--selected': token.id === selectedId,
            'scene-token--hidden': token.hidden,
            'scene-token--locked': token.locked,
          },
        ]"
        :style="{ transform: `rotate(${token.rotation}deg)` }"
        :aria-label="token.name"
        :aria-pressed="token.id === selectedId"
        :aria-disabled="!token.capabilities.canControl || token.locked"
        @pointerdown.stop="startDrag($event, token)"
        @click.stop="$emit('select', token.id)"
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
import { tokenDragMethods } from "./tokenDragMethods";
import { buildTokenDragIndicator } from "./tokenDragIndicator";
import {
  pendingTokenPositionResolved,
  tokenTravelDuration,
} from "./tokenMotion";
import { rotateTokenFacing, tokenFacingStyle } from "@/lib/vtt/tokenFacing";

export default {
  name: "SceneTokenLayer",
  components: { TokenDragIndicator, TokenHud },
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
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
  }),
  computed: {
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
