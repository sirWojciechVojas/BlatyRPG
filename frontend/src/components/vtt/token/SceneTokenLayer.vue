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
        'scene-token-wrap--dragging':
          !drag?.blocked && drag?.token.id === token.id,
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
        :style="facingStyle(displayTokenAngles(token))"
        aria-hidden="true"
      />
      <button
        type="button"
        class="scene-token"
        :class="[
          `scene-token--${token.disposition}`,
          stateClasses(tokenStates[token.id]),
        ]"
        :style="{
          transform: `rotate(${displayTokenAngles(token).rotation}deg)`,
        }"
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
        @dblclick.stop="openTokenActor(token)"
        @contextmenu.prevent.stop="toggleTokenHud(token.id)"
      >
        <img
          v-if="token.imageUrl"
          :src="token.imageUrl"
          alt=""
          draggable="false"
        />
        <span v-else>{{ initials(token.name) }}</span>
      </button>
      <TokenInfoStack v-if="tokenInfoVisible(token)" :token="token" />
      <TokenRotationHandles
        v-if="tokenStates[token.id].selected"
        :token="displayTokenAngles(token)"
        :disabled="busy"
        :scale="scale"
        @preview="previewTokenAngle(token, $event)"
        @commit="commitTokenAngle(token, $event)"
        @cancel="clearTokenAnglePreview(token.id)"
      />
      <TokenStateOverlay :flags="tokenStates[token.id]" />
      <TokenStatusBadges
        v-if="tokenInfoVisible(token)"
        :statuses="token.statuses"
      />
      <TokenResourceOverlay
        v-if="tokenInfoVisible(token)"
        :resources="token.resources"
        :bar-position="token.resourceBarPosition"
        :editable="resourceEditable(token)"
        :can-manage-movement="token.capabilities.canManage"
        @update="updateTokenResources(token, $event)"
      />
      <TokenHud
        v-if="token.id === hudTokenId"
        :token="token"
        :busy="busy"
        :targeted="tokenStates[token.id].targeted"
        :scale="scale"
        @pointerdown.stop
        @move-start="startDrag($event, token)"
        @status="toggleTokenStatus(token, $event)"
        @resources="updateTokenResources(token, $event)"
        @target="$emit('target', token.id)"
        @visibility="toggleTokenVisibility(token)"
        @lock="toggleTokenLock(token)"
        @settings="openTokenSettings($event, token)"
        @open-actor="$emit('open-actor', $event)"
        @delete="$emit('delete', token)"
        @close="hudTokenId = null"
      />
    </div>
    <Teleport to="body">
      <TokenSettingsPanel
        v-if="settingsToken"
        :token="settingsToken"
        :grid-size="Number(scene.gridSize) || 100"
        :grid-type="scene.gridType || 'square'"
        :members="members"
        :actor="settingsActor"
        :anchor="settingsAnchor"
        :busy="busy"
        @save="saveTokenSettings(settingsToken, $event)"
        @close="settingsTokenId = null"
      />
    </Teleport>
  </div>
</template>

<script>
import TokenHud from "./TokenHud.vue";
import TokenInfoStack from "./TokenInfoStack.vue";
import TokenDragIndicator from "./TokenDragIndicator.vue";
import TokenStateOverlay from "./TokenStateOverlay.vue";
import TokenStatusBadges from "./TokenStatusBadges.vue";
import TokenResourceOverlay from "./TokenResourceOverlay.vue";
import TokenRotationHandles from "./TokenRotationHandles.vue";
import TokenSettingsPanel from "./TokenSettingsPanel.vue";
import { tokenDragMethods } from "./tokenDragMethods";
import { buildTokenDragIndicator } from "./tokenDragIndicator";
import { tokenLayerMotionMethods } from "./tokenLayerMotionMethods";
import { tokenLayerWatchers } from "./tokenLayerWatchers";
import { tokenDisplayPosition } from "./tokenMotion";
import { tokenHudMethods } from "./tokenHudMethods";
import { tokenRotationMethods } from "./tokenRotationMethods";
import { tokenFacingStyle } from "@/lib/vtt/tokenFacing";
import {
  activeTokenUiStates,
  tokenUiClasses,
  tokenUiFlags,
} from "@/lib/vtt/tokenUiState";

export default {
  name: "SceneTokenLayer",
  components: {
    TokenDragIndicator,
    TokenHud,
    TokenInfoStack,
    TokenSettingsPanel,
    TokenResourceOverlay,
    TokenRotationHandles,
    TokenStateOverlay,
    TokenStatusBadges,
  },
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    selectedIds: { type: Array, default: () => [] },
    activeTurnId: { type: [Number, String], default: null },
    waitingTurnIds: { type: Array, default: () => [] },
    targetedIds: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    scale: { type: Number, default: 1 },
    busy: { type: Boolean, default: false },
  },
  emits: [
    "select",
    "move",
    "movement-depleted",
    "movement-limit",
    "update",
    "target",
    "delete",
    "open-actor",
  ],
  data: () => ({
    drag: null,
    preview: {},
    pendingPositions: {},
    pendingTimers: new Map(),
    movingTokenIds: {},
    motionDurations: {},
    hoveredTokenId: null,
    hudTokenId: null,
    settingsTokenId: null,
    settingsAnchor: {},
    anglePreview: {},
  }),
  computed: {
    settingsToken() {
      return (
        this.tokens.find((token) => token.id === this.settingsTokenId) || null
      );
    },
    settingsActor() {
      if (!this.settingsToken?.characterId) return null;
      return (
        this.characters.find(
          (actor) => actor.id === this.settingsToken.characterId,
        ) || null
      );
    },
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
            draggingId: this.drag?.blocked ? null : this.drag?.token.id,
            activeTurnId: this.activeTurnId,
            waitingTurnIds: this.waitingTurnIds,
            targetedIds: this.targetedIds,
            disabled: this.busy,
          }),
        ]),
      );
    },
    dragIndicator() {
      if (!this.drag || this.drag.blocked) return null;
      return buildTokenDragIndicator(
        this.scene,
        this.drag.token,
        this.preview[this.drag.token.id] || this.drag.token,
        this.drag.waypoints,
      );
    },
  },
  watch: tokenLayerWatchers,
  beforeUnmount() {
    this.cancelDrag();
    this.pendingTimers.forEach((timer) => window.clearTimeout(timer));
    this.pendingTimers.clear();
  },
  methods: {
    ...tokenDragMethods,
    ...tokenLayerMotionMethods,
    ...tokenHudMethods,
    ...tokenRotationMethods,
    facingStyle: tokenFacingStyle,
    stateClasses: tokenUiClasses,
    selectToken(event, tokenId) {
      if (this.hudTokenId !== tokenId) this.hudTokenId = null;
      this.$emit("select", {
        tokenId,
        additive: event.ctrlKey || event.metaKey || event.shiftKey,
      });
    },
    toggleTokenHud(tokenId) {
      const opening = this.hudTokenId !== tokenId;
      this.hudTokenId = opening ? tokenId : null;
      if (opening) this.$emit("select", { tokenId, additive: false });
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
      const position = tokenDisplayPosition(
        token,
        this.pendingPositions[token.id],
      );
      return {
        width: `${token.width}px`,
        height: `${token.height}px`,
        transform: `translate(${position.x}px, ${position.y}px)`,
        zIndex: String(100 + Math.round(token.elevation || 0)),
        "--token-travel-duration": `${this.motionDurations[token.id] || 460}ms`,
      };
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
