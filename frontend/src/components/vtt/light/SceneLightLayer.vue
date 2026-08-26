<template>
  <div
    ref="root"
    class="scene-light-layer"
    :class="{ 'scene-light-layer--active': active }"
  >
    <SceneLightingVisual
      :uid="uid"
      :scene="scene"
      :lights="visualLights"
      :walls="walls"
      :can-manage="canManage"
    />
    <svg
      v-if="canManage && active"
      ref="editor"
      class="scene-light-layer__editor"
      :viewBox="viewBox"
      tabindex="0"
      @pointermove="move"
      @pointerdown="canvasPointerDown"
      @pointerup="finish"
      @pointercancel="cancel"
      @contextmenu.prevent.stop="openContextMenu"
      @keydown="keyboard"
    >
      <rect width="100%" height="100%" fill="transparent" />
      <g v-for="light in lights" :key="light.id" :class="lightClasses(light)">
        <path
          v-if="light.id === selectedId"
          class="scene-light__dim"
          :d="technicalPath(display(light), false)"
        />
        <path
          v-if="light.id === selectedId"
          class="scene-light__bright"
          :d="technicalPath(display(light), true)"
        />
        <line
          v-if="light.id === selectedId && isDirected(light)"
          class="scene-light__direction"
          :x1="display(light).x"
          :y1="display(light).y"
          :x2="directionEnd(display(light)).x"
          :y2="directionEnd(display(light)).y"
        />
        <circle
          v-if="light.id === selectedId"
          class="scene-light__source"
          :cx="display(light).x"
          :cy="display(light).y"
          r="11"
        />
        <circle
          class="scene-light__hit"
          :cx="display(light).x"
          :cy="display(light).y"
          r="16"
          @pointerdown.stop="startMove($event, light)"
          @click.stop="$emit('select', light.id)"
          @dblclick.stop="openProperties"
        />
      </g>
      <g v-if="creationPreview" class="scene-light scene-light--draft">
        <path
          class="scene-light__dim"
          :d="technicalPath(creationPreview, false)"
        />
        <path
          class="scene-light__bright"
          :d="technicalPath(creationPreview, true)"
        />
        <line
          v-if="isDirected(creationPreview)"
          class="scene-light__direction"
          :x1="creationPreview.x"
          :y1="creationPreview.y"
          :x2="directionEnd(creationPreview).x"
          :y2="directionEnd(creationPreview).y"
        />
        <circle
          class="scene-light__source"
          :cx="creationPreview.x"
          :cy="creationPreview.y"
          r="11"
        />
      </g>
    </svg>
    <LightHud
      v-if="canManage && active && selectedLight"
      :light="selectedLight"
      :grid-size="scene.gridSize"
      :busy="busy"
      :style="hudStyle"
      @update="$emit('update', { light: selectedLight, changes: $event })"
      @copy="copySelected"
      @edit="openProperties"
      @delete="$emit('delete', selectedLight)"
    />
    <LightToolToolbar
      v-if="canManage && active"
      :light="selectedLight"
      :source-type="selectedLight?.sourceType || creationType"
      :count="lights.length + 1"
      :list-open="listOpen"
      :busy="busy"
      :global-light-level="scene.globalLightLevel"
      @add="addDefault"
      @copy="copySelected"
      @update="updateSelected"
      @global-update="$emit('update', { globalLightLevel: $event })"
      @source-type="setSourceType"
      @edit="openProperties"
      @toggle-list="listOpen = !listOpen"
      @delete="$emit('delete', selectedLight)"
    />
    <LightManagementPanel
      v-if="canManage && active && listOpen"
      :lights="lights"
      :global-light-level="scene.globalLightLevel"
      :selected-id="selectedId"
      :busy="busy"
      @select="$emit('select', $event)"
      @add="addDefault"
      @update="$emit('update', $event)"
      @global-update="$emit('update', { globalLightLevel: $event })"
      @edit="editLight"
      @copy="copyLight"
      @delete="$emit('delete', $event)"
    />
    <LightPropertiesPanel
      v-if="canManage && active && selectedLight && propertiesOpen"
      ref="properties"
      :light="selectedLight"
      :busy="busy"
      :status="propertySaveStatus"
      :error="propertySaveError"
      @close="closeProperties"
      @save="saveProperties"
      @preview="propertiesPreview = $event"
      @unchanged="propertySaveStatus = 'saved'"
    />
    <LightContextMenu
      v-if="canManage && active && contextMenu"
      :style="contextMenuStyle"
      :light="selectedLight"
      :source-type="selectedLight?.sourceType || creationType"
      :busy="busy"
      @source-type="contextSourceType"
      @add="addAtContext"
      @toggle-list="toggleListFromContext"
      @update="updateFromContext"
      @copy="copyFromContext"
      @edit="editFromContext"
      @delete="deleteFromContext"
      @close="contextMenu = null"
    />
  </div>
</template>

<script>
import { getCurrentInstance } from "vue";
import LightHud from "./LightHud.vue";
import LightContextMenu from "./LightContextMenu.vue";
import LightManagementPanel from "./LightManagementPanel.vue";
import LightPropertiesPanel from "./LightPropertiesPanel.vue";
import LightToolToolbar from "./LightToolToolbar.vue";
import SceneLightingVisual from "./SceneLightingVisual.vue";
import { lightLayerEditorMethods } from "./lightLayerEditorMethods";
import { lightPropertyEditorMethods } from "./lightPropertyEditorMethods";
import { lightTechnicalPath } from "@/lib/vtt/lightGeometry";
import { effectiveLight } from "@/lib/vtt/lightPhotometry";

export default {
  name: "SceneLightLayer",
  components: {
    LightHud,
    LightContextMenu,
    LightManagementPanel,
    LightPropertiesPanel,
    LightToolToolbar,
    SceneLightingVisual,
  },
  props: {
    scene: { type: Object, required: true },
    lights: { type: Array, default: () => [] },
    walls: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    selectedId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "create", "update", "delete"],
  data() {
    return {
      uid: getCurrentInstance().uid,
      drag: null,
      preview: null,
      propertiesOpen: false,
      propertiesPreview: null,
      propertySaveStatus: "idle",
      propertySaveError: "",
      listOpen: true,
      creationType: "omni",
      contextMenu: null,
    };
  },
  computed: {
    active() {
      return this.activeTool === "lights";
    },
    selectedLight() {
      return this.lights.find((light) => light.id === this.selectedId) || null;
    },
    creationPreview() {
      return this.drag?.type === "create"
        ? {
            ...this.preview,
            sourceType: this.creationType,
            angle: this.defaultAngle(this.creationType),
            lumens: 800,
            opacity: 1,
            softness: 0.5,
            gradualIllumination: true,
            constrainedByWalls: true,
            enabled: true,
          }
        : null;
    },
    visualLights() {
      if (this.drag?.type === "move") {
        return this.lights.map((light) =>
          light.id === this.drag.light.id
            ? { ...light, ...this.preview }
            : light,
        );
      }
      if (this.creationPreview) {
        return [
          ...this.lights,
          {
            ...this.creationPreview,
            id: "draft",
            name: this.$t("vtt.light.defaultName"),
            color: "#FFD27A",
            darknessMin: 0,
            darknessMax: 1,
            animation: "none",
            animationSpeed: 1,
            animationIntensity: 0.5,
            elevation: 0,
          },
        ];
      }
      if (this.propertiesPreview && this.selectedLight) {
        return this.lights.map((light) =>
          light.id === this.selectedLight.id
            ? { ...light, ...this.propertiesPreview }
            : light,
        );
      }
      return this.lights;
    },
    viewBox() {
      return `0 0 ${this.scene.width} ${this.scene.height}`;
    },
    hudStyle() {
      return {
        left: `${this.selectedLight.x}px`,
        top: `${this.selectedLight.y}px`,
      };
    },
    contextMenuStyle() {
      return {
        left: `${this.contextMenu.left}px`,
        top: `${this.contextMenu.top}px`,
      };
    },
  },
  methods: {
    ...lightLayerEditorMethods,
    ...lightPropertyEditorMethods,
    technicalPath(light, bright) {
      return lightTechnicalPath(light, this.walls, this.scene, bright);
    },
    isDirected(light) {
      return ["directional", "cone"].includes(light?.sourceType);
    },
    directionEnd(light) {
      const geometry = effectiveLight(light);
      const radians = ((Number(light.direction) || 0) * Math.PI) / 180;
      return {
        x: Number(light.x) + Math.cos(radians) * geometry.dimRadius,
        y: Number(light.y) + Math.sin(radians) * geometry.dimRadius,
      };
    },
  },
};
</script>
