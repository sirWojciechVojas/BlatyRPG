<template>
  <div
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
      @pointerup="finish"
      @pointercancel="cancel"
      @keydown="keyboard"
    >
      <rect
        width="100%"
        height="100%"
        fill="transparent"
        @pointerdown="startCreate"
      />
      <g v-for="light in lights" :key="light.id" :class="lightClasses(light)">
        <circle
          v-if="light.id === selectedId"
          class="scene-light__dim"
          :cx="display(light).x"
          :cy="display(light).y"
          :r="light.dimRadius"
        />
        <circle
          v-if="light.id === selectedId"
          class="scene-light__bright"
          :cx="display(light).x"
          :cy="display(light).y"
          :r="light.brightRadius"
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
          @dblclick.stop="propertiesOpen = true"
        />
      </g>
      <g v-if="creationPreview" class="scene-light scene-light--draft">
        <circle
          class="scene-light__dim"
          :cx="creationPreview.x"
          :cy="creationPreview.y"
          :r="creationPreview.dimRadius"
        />
        <circle
          class="scene-light__bright"
          :cx="creationPreview.x"
          :cy="creationPreview.y"
          :r="creationPreview.brightRadius"
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
      @edit="propertiesOpen = true"
      @delete="$emit('delete', selectedLight)"
    />
    <LightToolToolbar
      v-if="canManage && active"
      :light="selectedLight"
      :count="lights.length"
      :list-open="listOpen"
      :busy="busy"
      @add="addDefault"
      @copy="copySelected"
      @update="updateSelected"
      @edit="propertiesOpen = true"
      @toggle-list="listOpen = !listOpen"
      @delete="$emit('delete', selectedLight)"
    />
    <LightManagementPanel
      v-if="canManage && active && listOpen"
      :lights="lights"
      :selected-id="selectedId"
      :busy="busy"
      @select="$emit('select', $event)"
      @add="addDefault"
      @update="$emit('update', $event)"
      @edit="editLight"
      @delete="$emit('delete', $event)"
    />
    <LightPropertiesPanel
      v-if="canManage && active && selectedLight && propertiesOpen"
      :light="selectedLight"
      :busy="busy"
      @close="propertiesOpen = false"
      @save="$emit('update', { light: selectedLight, changes: $event })"
    />
  </div>
</template>

<script>
import { getCurrentInstance } from "vue";
import LightHud from "./LightHud.vue";
import LightManagementPanel from "./LightManagementPanel.vue";
import LightPropertiesPanel from "./LightPropertiesPanel.vue";
import LightToolToolbar from "./LightToolToolbar.vue";
import SceneLightingVisual from "./SceneLightingVisual.vue";
import { lightLayerEditorMethods } from "./lightLayerEditorMethods";

export default {
  name: "SceneLightLayer",
  components: {
    LightHud,
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
      listOpen: true,
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
      return this.drag?.type === "create" ? this.preview : null;
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
            color: "#FFD27A",
            intensity: 1,
            opacity: 1,
            softness: 0.5,
            gradualIllumination: true,
            darknessMin: 0,
            darknessMax: 1,
            sourceType: "light",
            constrainedByWalls: true,
            animation: "none",
            animationSpeed: 1,
            animationIntensity: 0.5,
            elevation: 0,
            enabled: true,
          },
        ];
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
  },
  methods: {
    ...lightLayerEditorMethods,
  },
};
</script>
