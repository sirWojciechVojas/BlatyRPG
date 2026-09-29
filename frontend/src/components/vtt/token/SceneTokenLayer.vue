<template>
  <div
    class="scene-token-layer"
    :class="{ 'scene-token-layer--foreground': foreground }"
  >
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
          !drag?.blocked && isDraggingToken(token.id),
        'scene-token-wrap--moving': movingTokenIds[token.id],
        'scene-token-wrap--selected': tokenStates[token.id].selected,
        'scene-token-wrap--hud': token.id === hudTokenId,
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
        @dblclick.stop="openTokenActorFromDoubleClick(token)"
        @wheel="rotateTokenFacingWithWheel($event, token)"
        @contextmenu.prevent.stop="toggleTokenHud(token.id)"
      >
        <AuthenticatedImage
          v-if="token.imageUrl"
          :src="token.imageUrl"
          alt=""
          draggable="false"
        />
        <span v-else>{{ initials(token.name) }}</span>
      </button>
      <button
        v-if="movementToggleVisible(token)"
        type="button"
        class="scene-token-movement-toggle"
        :class="{
          active: String(movementModeTokenId) === String(token.id),
          depleted: movementRemaining(token) <= 0,
        }"
        :disabled="busy || token.locked"
        :aria-pressed="String(movementModeTokenId) === String(token.id)"
        :title="
          $t('vtt.token.movement.modeHint', {
            points: formatMovement(movementRemaining(token)),
          })
        "
        @pointerdown.stop
        @click.stop="$emit('movement-mode', token.id)"
      >
        <span aria-hidden="true">↗</span>
        <b>
          {{ formatMovement(movementRemaining(token)) }}
          {{ $t("vtt.token.movement.pointsShort") }}
        </b>
      </button>
      <TokenInfoStack v-if="tokenInfoVisible(token)" :token="token" />
      <TokenRotationHandles
        v-if="isTokenExpanded(token.id)"
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
        v-if="token.id === hudTokenId && !hasMultiSelection"
        :token="token"
        :busy="busy"
        :targeted="tokenStates[token.id].targeted"
        :scale="scale"
        :synchronized="Boolean(incomingSyncLink(token.id))"
        @pointerdown.stop
        @move-start="startDrag($event, token)"
        @status="toggleTokenStatus(token, $event)"
        @resources="updateTokenResources(token, $event)"
        @target="$emit('target', token.id)"
        @visibility="toggleTokenVisibility(token)"
        @lock="toggleTokenLock(token)"
        @settings="openTokenSettings($event, token)"
        @open-actor="$emit('open-actor', $event)"
        @assign-character="$emit('assign-character', token)"
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
        :sync-link="settingsSyncLink"
        @save="saveTokenSettings(settingsToken, $event)"
        @assign-character="$emit('assign-character', settingsToken)"
        @close="settingsTokenId = null"
      />
    </Teleport>
  </div>
</template>

<script>
import TokenHud from "./TokenHud.vue";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
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
import { tokenPresentationMethods } from "./tokenPresentationMethods";
import { tokenRotationMethods } from "./tokenRotationMethods";
import {
  tokenFacingStyle,
  tokenFacingWheelChanges,
} from "@/lib/vtt/tokenFacing";
import {
  activeTokenUiStates,
  tokenUiClasses,
  tokenUiFlags,
} from "@/lib/vtt/tokenUiState";

const FACING_WHEEL_COMMIT_DELAY = 180;

export default {
  name: "SceneTokenLayer",
  components: {
    AuthenticatedImage,
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
    foreground: { type: Boolean, default: false },
    movementModeTokenId: { type: [Number, String], default: null },
    tokenSyncLinks: { type: Array, default: () => [] },
  },
  emits: [
    "select",
    "move",
    "move-group",
    "movement-depleted",
    "movement-limit",
    "update",
    "target",
    "delete",
    "open-actor",
    "assign-character",
    "vision-preview",
    "vision-angle-preview",
    "movement-mode",
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
    expandedTokenId: null,
    presentationClickTokenId: null,
    presentationClickTimer: null,
    facingWheelTimers: new Map(),
  }),
  computed: {
    hasMultiSelection() {
      return this.effectiveSelectedIds.length > 1;
    },
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
    settingsSyncLink() {
      return this.settingsToken
        ? this.incomingSyncLink(this.settingsToken.id)
        : null;
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
        this.drag.group.length,
      );
    },
  },
  watch: tokenLayerWatchers,
  beforeUnmount() {
    this.cancelDrag();
    this.pendingTimers.forEach((timer) => window.clearTimeout(timer));
    this.pendingTimers.clear();
    this.clearPresentationClick();
    this.facingWheelTimers.forEach((timer) => window.clearTimeout(timer));
    this.facingWheelTimers.clear();
  },
  methods: {
    ...tokenDragMethods,
    ...tokenLayerMotionMethods,
    ...tokenHudMethods,
    ...tokenPresentationMethods,
    ...tokenRotationMethods,
    incomingSyncLink(tokenId) {
      return (
        this.tokenSyncLinks.find(
          (link) => Number(link.targetTokenId) === Number(tokenId),
        ) || null
      );
    },
    facingStyle: tokenFacingStyle,
    stateClasses: tokenUiClasses,
    rotateTokenFacingWithWheel(event, token) {
      if (
        this.busy ||
        token.locked ||
        token.capabilities?.canControl !== true ||
        this.hasMultiSelection ||
        !this.tokenStates[token.id]?.selected
      ) {
        return;
      }
      const changes = tokenFacingWheelChanges(
        this.displayTokenAngles(token),
        event.deltaY,
      );
      if (!changes) return;

      event.preventDefault();
      event.stopPropagation();
      this.anglePreview = {
        ...this.anglePreview,
        [token.id]: {
          ...(this.anglePreview[token.id] || {}),
          ...changes,
        },
      };
      this.$emit("vision-angle-preview", {
        tokenId: token.id,
        changes: this.anglePreview[token.id],
      });

      const previousTimer = this.facingWheelTimers.get(token.id);
      if (previousTimer) window.clearTimeout(previousTimer);
      const timer = window.setTimeout(() => {
        this.facingWheelTimers.delete(token.id);
        const preview = this.anglePreview[token.id];
        if (!preview) return;
        const committed = { facing: preview.facing };
        if (token.rotationFollowsFacing === true) {
          committed.rotation = preview.rotation;
        }
        this.clearTokenAnglePreview(token.id);
        this.$emit("update", { token, changes: committed });
      }, FACING_WHEEL_COMMIT_DELAY);
      this.facingWheelTimers.set(token.id, timer);
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
        "--token-ui-scale": String(1 / Math.max(0.05, Number(this.scale) || 1)),
      };
    },
    movementToggleVisible(token) {
      return (
        !this.hasMultiSelection &&
        this.isTokenExpanded(token.id) &&
        token.capabilities?.canControl === true
      );
    },
    movementRemaining(token) {
      return Math.max(
        0,
        Number.isFinite(Number(token.movementPoints))
          ? Number(token.movementPoints)
          : Number(token.movementRange || 0) - Number(token.movementSpent || 0),
      );
    },
    formatMovement(value) {
      return Number.isInteger(Number(value))
        ? String(Number(value))
        : Number(value).toFixed(1);
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
