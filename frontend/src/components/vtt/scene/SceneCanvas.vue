<template>
  <section
    ref="viewport"
    class="scene-canvas"
    :class="{
      'scene-canvas--dragging': dragging,
      'scene-canvas--area-selection': tokenAreaSelectionActive,
      [`scene-canvas--tool-${activeTool}`]: true,
    }"
    tabindex="0"
    role="region"
    :aria-label="
      $t('vtt.scene.workspace.canvasLabel', { name: scene?.name || '' })
    "
    @wheel.prevent="onWheel"
    @keydown="onKeydown"
    @pointerdown="startPan"
    @pointermove="movePan"
    @pointerup="endPan"
    @pointercancel="endPan"
    @click="addTokenPolygonPoint"
    @dblclick="finishTokenPolygonSelection"
    @dragover.prevent="previewDrop"
    @dragleave="leaveDropPreview"
    @drop.prevent="dropContent"
  >
    <TokenAreaSelectionToolbar
      v-if="activeTool === 'tokens'"
      :model-value="tokenSelectionMode"
      @update:model-value="setTokenSelectionMode"
    />
    <SceneMeasurementOverlay
      :scene="scene"
      :active-tool="activeTool"
      :scale="camera.scale"
    />
    <p v-if="!scene" class="scene-canvas__empty">
      {{ $t("vtt.scene.workspace.noScene") }}
    </p>
    <div v-else class="scene-canvas__map" :style="mapStyle">
      <div class="scene-canvas__content" :style="contentStyle">
        <SceneBackgroundImage
          v-if="scene.backgroundUrl && !backgroundFailed"
          class="scene-canvas__background"
          :src="scene.backgroundUrl"
          :alt="scene.name"
          @error="backgroundFailed = true"
        />
        <svg
          v-if="pattern"
          class="scene-canvas__grid"
          :width="scene.width"
          :height="scene.height"
          :viewBox="`0 0 ${scene.width} ${scene.height}`"
          aria-hidden="true"
        >
          <defs>
            <pattern
              :id="patternId"
              patternUnits="userSpaceOnUse"
              :x="pattern.offsetX"
              :y="pattern.offsetY"
              :width="pattern.width"
              :height="pattern.height"
            >
              <path
                :d="pattern.path"
                fill="none"
                :stroke="pattern.color"
                :stroke-opacity="pattern.opacity"
                vector-effect="non-scaling-stroke"
              />
            </pattern>
          </defs>
          <rect width="100%" height="100%" :fill="`url(#${patternId})`" />
        </svg>
        <p v-if="backgroundFailed" class="scene-canvas__image-error">
          {{ $t("vtt.scene.workspace.backgroundError") }}
        </p>
        <SceneTileLayer
          :scene="scene"
          :tiles="tiles"
          :active-tool="activeTool"
          :selected-id="selectedTileId"
          :can-manage="canManageTiles"
          :busy="tileBusy"
          :scale="camera.scale"
          @select="$emit('tile-select', $event)"
          @create="$emit('tile-create', $event)"
          @update="$emit('tile-update', $event)"
          @delete="$emit('tile-delete', $event)"
        />
        <SceneLightLayer
          :scene="scene"
          :lights="lights"
          :walls="walls"
          :regions="regions"
          :active-tool="activeTool"
          :selected-id="selectedLightId"
          :can-manage="canManageLights"
          :busy="lightBusy"
          @select="$emit('light-select', $event)"
          @create="$emit('light-create', $event)"
          @update="$emit('light-update', $event)"
          @delete="$emit('light-delete', $event)"
        />
        <SceneRegionLayer
          :scene="scene"
          :regions="regions"
          :scale="camera.scale"
          :active-tool="activeTool"
          :selected-id="selectedRegionId"
          :can-manage="canManageRegions"
          :busy="regionBusy"
          @select="$emit('region-select', $event)"
          @create="$emit('region-create', $event)"
          @update="$emit('region-update', $event)"
          @delete="$emit('region-delete', $event)"
        />
        <SceneWallLayer
          ref="wallLayer"
          :scene="scene"
          :walls="walls"
          :scale="camera.scale"
          :active-tool="activeTool"
          :selected-id="selectedWallId"
          :can-manage="canManageWalls"
          :busy="wallBusy"
          :toolbar-target="wallToolbarTarget"
          @select="$emit('wall-select', $event)"
          @create="$emit('wall-create', $event)"
          @create-many="$emit('wall-create-many', $event)"
          @insert-opening="$emit('wall-insert-opening', $event)"
          @update="$emit('wall-update', $event)"
          @update-many="$emit('wall-update-many', $event)"
          @delete="$emit('wall-delete', $event)"
          @create-region="$emit('region-create', $event)"
        />
        <SceneDoorLayer
          :scene="scene"
          :walls="walls"
          :scale="camera.scale"
          :can-manage="canManageWalls"
          :busy="wallBusy"
          :selected-tokens="doorInteractionTokens"
          :characters="characters"
          :current-user-id="currentUserId"
          @interact="$emit('wall-interact', $event)"
          @edit="$emit('wall-edit', $event)"
        />
        <WallSpatialAudioController
          :scene="scene"
          :walls="walls"
          :tokens="fogTokens"
          :selected-token-id="selectedTokenId"
          :selected-token-ids="selectedTokenIds"
          :can-manage="canManageScene"
        />
        <TokenVisionOverlay
          :scene="scene"
          :tokens="visionShapeTokens"
          :walls="walls"
          :can-manage="canManageScene"
        />
        <TokenMovementRange
          :scene="scene"
          :tokens="displayTokens"
          :token-id="activeMovementTokenId"
          :enabled="activeMovementTokenId !== null"
        />
        <SceneFogLayer
          :scene="scene"
          :tokens="fogTokens"
          :walls="walls"
          :lights="lights"
          :regions="regions"
          :members="members"
          :characters="characters"
          :fog-state="fogState"
          :camera="camera"
          :viewport-size="viewportSize"
          :scene-padding="mapDimensions.padding"
          :active-tool="activeTool"
          :can-manage="canManageScene"
          :preview="effectiveFogPreview"
          :busy="fogBusy"
          @patch="$emit('fog-patch', $event)"
          @preview-change="$emit('fog-preview-change', $event)"
          @visibility-change="fogVisibility = $event"
        />
        <SceneTokenLayer
          :scene="scene"
          :tokens="displayTokens"
          :selected-id="selectedTokenId"
          :selected-ids="selectedTokenIds"
          :targeted-ids="targetedTokenIds"
          :active-turn-id="activeTurnId"
          :waiting-turn-ids="waitingTurnIds"
          :members="members"
          :characters="characters"
          :scale="camera.scale"
          :busy="tokenBusy"
          :foreground="tokenUiAboveFog"
          :movement-mode-token-id="activeMovementTokenId"
          :token-sync-links="tokenSyncLinks"
          @select="$emit('token-select', $event)"
          @move="$emit('token-move', $event)"
          @move-group="$emit('token-move-group', $event)"
          @movement-depleted="$emit('token-movement-depleted', $event)"
          @movement-limit="$emit('token-movement-limit', $event)"
          @update="$emit('token-update', $event)"
          @target="$emit('token-target', $event)"
          @delete="$emit('token-delete', $event)"
          @open-actor="$emit('open-actor', $event)"
          @assign-character="$emit('assign-character', $event)"
          @vision-preview="applyTokenVisionPreview"
          @vision-angle-preview="applyTokenVisionAnglePreview"
          @movement-mode="toggleMovementMode"
        />
        <TokenAreaSelectionOverlay
          :scene="scene"
          :tokens="displayTokens"
          :selection="tokenSelection"
          :scale="camera.scale"
        />
        <TokenDropPreview
          :scene="scene"
          :preview="actorDropPreview"
          :scale="camera.scale"
        />
      </div>
    </div>
  </section>
</template>

<script>
import { getCurrentInstance, nextTick } from "vue";
import SceneTokenLayer from "@/components/vtt/token/SceneTokenLayer.vue";
import TokenAreaSelectionOverlay from "@/components/vtt/token/TokenAreaSelectionOverlay.vue";
import TokenAreaSelectionToolbar from "@/components/vtt/token/TokenAreaSelectionToolbar.vue";
import SceneWallLayer from "@/components/vtt/wall/SceneWallLayer.vue";
import SceneDoorLayer from "@/components/vtt/wall/SceneDoorLayer.vue";
import WallSpatialAudioController from "@/components/vtt/wall/WallSpatialAudioController.vue";
import SceneLightLayer from "@/components/vtt/light/SceneLightLayer.vue";
import SceneTileLayer from "@/components/vtt/tile/SceneTileLayer.vue";
import SceneFogLayer from "@/components/vtt/fog/SceneFogLayer.vue";
import SceneRegionLayer from "@/components/vtt/region/SceneRegionLayer.vue";
import SceneMeasurementOverlay from "./SceneMeasurementOverlay.vue";
import SceneBackgroundImage from "./SceneBackgroundImage.vue";
import TokenDropPreview from "@/components/vtt/token/TokenDropPreview.vue";
import TokenMovementRange from "@/components/vtt/token/TokenMovementRange.vue";
import TokenVisionOverlay from "@/components/vtt/token/TokenVisionOverlay.vue";
import { sceneCanvasCameraMethods } from "./sceneCanvasCameraMethods";
import { sceneCanvasComputed } from "./sceneCanvasComputed";
import { sceneCanvasDropMethods } from "./sceneCanvasDropMethods";
import { tokenAreaSelectionMethods } from "@/components/vtt/token/tokenAreaSelectionMethods";

export default {
  name: "SceneCanvas",
  components: {
    SceneBackgroundImage,
    SceneLightLayer,
    SceneFogLayer,
    SceneMeasurementOverlay,
    SceneRegionLayer,
    SceneTokenLayer,
    TokenAreaSelectionOverlay,
    TokenAreaSelectionToolbar,
    TokenDropPreview,
    TokenMovementRange,
    TokenVisionOverlay,
    SceneTileLayer,
    SceneWallLayer,
    SceneDoorLayer,
    WallSpatialAudioController,
  },
  props: {
    scene: { type: Object, default: null },
    activeTool: { type: String, default: "select" },
    tokens: { type: Array, default: () => [] },
    selectedTokenId: { type: [Number, String], default: null },
    selectedTokenIds: { type: Array, default: () => [] },
    targetedTokenIds: { type: Array, default: () => [] },
    activeTurnId: { type: [Number, String], default: null },
    waitingTurnIds: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    currentUserId: { type: [Number, String], default: null },
    tokenBusy: { type: Boolean, default: false },
    canCreateToken: { type: Boolean, default: false },
    walls: { type: Array, default: () => [] },
    selectedWallId: { type: [Number, String], default: null },
    canManageWalls: { type: Boolean, default: false },
    wallBusy: { type: Boolean, default: false },
    wallToolbarTarget: { type: String, default: "" },
    lights: { type: Array, default: () => [] },
    selectedLightId: { type: [Number, String], default: null },
    canManageLights: { type: Boolean, default: false },
    lightBusy: { type: Boolean, default: false },
    regions: { type: Array, default: () => [] },
    selectedRegionId: { type: [Number, String], default: null },
    canManageRegions: { type: Boolean, default: false },
    regionBusy: { type: Boolean, default: false },
    tiles: { type: Array, default: () => [] },
    selectedTileId: { type: [Number, String], default: null },
    canManageTiles: { type: Boolean, default: false },
    tileBusy: { type: Boolean, default: false },
    canManageScene: { type: Boolean, default: false },
    fogState: { type: Object, default: null },
    fogPreview: {
      type: Object,
      default: () => ({ mode: "gm", id: null }),
    },
    fogBusy: { type: Boolean, default: false },
    tokenSyncLinks: { type: Array, default: () => [] },
  },
  emits: [
    "camera-change",
    "token-select",
    "token-move",
    "token-move-group",
    "token-movement-depleted",
    "token-movement-limit",
    "token-update",
    "token-target",
    "token-delete",
    "token-create",
    "token-template-create",
    "open-actor",
    "assign-character",
    "wall-select",
    "wall-create",
    "wall-create-many",
    "wall-insert-opening",
    "wall-update",
    "wall-update-many",
    "wall-delete",
    "wall-interact",
    "wall-edit",
    "light-select",
    "light-create",
    "light-update",
    "light-delete",
    "region-select",
    "region-create",
    "region-update",
    "region-delete",
    "tile-select",
    "tile-create",
    "tile-update",
    "tile-delete",
    "fog-patch",
    "fog-preview-change",
  ],
  data() {
    return {
      camera: { x: 0, y: 0, scale: 1 },
      dragging: false,
      pointer: null,
      backgroundFailed: false,
      resizeObserver: null,
      hasFitted: false,
      viewportSize: { width: 0, height: 0 },
      patternId: `scene-grid-${getCurrentInstance().uid}`,
      actorDropPreview: null,
      tokenSelectionMode: "point",
      tokenSelection: null,
      fogVisibility: {
        constrained: false,
        visibleTokenIds: [],
        visionTokenIds: [],
      },
      tokenVisionPreviews: {},
      tokenVisionAnglePreviews: {},
      movementModeTokenId: null,
    };
  },
  computed: sceneCanvasComputed,
  watch: {
    "scene.id"() {
      this.cancelTokenAreaSelection();
      this.movementModeTokenId = null;
      this.backgroundFailed = false;
      this.hasFitted = false;
      nextTick(this.fit);
    },
    "scene.backgroundUrl"() {
      this.backgroundFailed = false;
    },
    activeTool(value) {
      if (value !== "tokens") this.cancelTokenAreaSelection();
      if (!["select", "tokens"].includes(value))
        this.movementModeTokenId = null;
    },
    selectedTokenId: "clearInvalidMovementMode",
    selectedTokenIds: { deep: true, handler: "clearInvalidMovementMode" },
  },
  mounted() {
    window.addEventListener("dragend", this.clearDropPreview);
    if (typeof ResizeObserver !== "undefined") {
      this.resizeObserver = new ResizeObserver(([entry]) => {
        this.resizeViewport(entry.contentRect.width, entry.contentRect.height);
      });
      this.resizeObserver.observe(this.$refs.viewport);
    }
    nextTick(this.fit);
  },
  beforeUnmount() {
    this.cancelTokenAreaSelection();
    window.removeEventListener("dragend", this.clearDropPreview);
    this.resizeObserver?.disconnect();
  },
  methods: {
    openWallProperties(wallId) {
      this.$refs.wallLayer?.editWall?.(wallId);
    },
    ...sceneCanvasCameraMethods,
    ...sceneCanvasDropMethods,
    ...tokenAreaSelectionMethods,
    applyTokenVisionPreview(preview) {
      this.tokenVisionPreviews = preview?.positions || {};
    },
    applyTokenVisionAnglePreview(preview) {
      if (!preview) return;
      const next = { ...this.tokenVisionAnglePreviews };
      if (preview.changes) next[preview.tokenId] = preview.changes;
      else delete next[preview.tokenId];
      this.tokenVisionAnglePreviews = next;
    },
    toggleMovementMode(tokenId) {
      this.movementModeTokenId =
        String(this.movementModeTokenId) === String(tokenId) ? null : tokenId;
    },
    clearInvalidMovementMode() {
      if (this.activeMovementTokenId === null) this.movementModeTokenId = null;
    },
  },
};
</script>
