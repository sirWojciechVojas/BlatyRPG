<template>
  <svg class="scene-light-layer__visual" :viewBox="viewBox" aria-hidden="true">
    <defs>
      <template v-for="light in activeLights" :key="`defs-${light.id}`">
        <radialGradient
          :id="maskId(light)"
          gradientUnits="userSpaceOnUse"
          :cx="light.x"
          :cy="light.y"
          :r="geometry(light).dimRadius"
        >
          <stop offset="0%" stop-color="#000" :stop-opacity="strength(light)" />
          <stop
            :offset="brightOffset(light)"
            stop-color="#000"
            :stop-opacity="strength(light)"
          />
          <stop
            :offset="fadeOffset(light)"
            stop-color="#777"
            :stop-opacity="strength(light)"
          />
          <stop offset="100%" stop-color="#fff" stop-opacity="0" />
        </radialGradient>
        <radialGradient
          :id="glowId(light)"
          gradientUnits="userSpaceOnUse"
          :cx="light.x"
          :cy="light.y"
          :r="geometry(light).dimRadius"
        >
          <stop
            offset="0%"
            :stop-color="light.color"
            :stop-opacity="strength(light) * 0.58"
          />
          <stop
            :offset="brightOffset(light)"
            :stop-color="light.color"
            :stop-opacity="strength(light) * 0.36"
          />
          <stop offset="100%" :stop-color="light.color" stop-opacity="0" />
        </radialGradient>
        <radialGradient
          :id="darkId(light)"
          gradientUnits="userSpaceOnUse"
          :cx="light.x"
          :cy="light.y"
          :r="geometry(light).dimRadius"
        >
          <stop
            offset="0%"
            stop-color="#000108"
            :stop-opacity="strength(light)"
          />
          <stop
            :offset="fadeOffset(light)"
            stop-color="#01020a"
            :stop-opacity="strength(light) * 0.8"
          />
          <stop offset="100%" stop-color="#020307" stop-opacity="0" />
        </radialGradient>
      </template>
      <mask :id="darknessMaskId" mask-type="luminance">
        <rect width="100%" height="100%" fill="#fff" />
        <path
          v-for="light in lightSources"
          :key="`mask-${light.id}`"
          :d="path(light)"
          :fill="areaFill(light, `url(#${maskId(light)})`, '#000')"
          :fill-opacity="areaOpacity(light, 1)"
        />
      </mask>
      <mask :id="visionMaskId" mask-type="luminance">
        <rect width="100%" height="100%" fill="#fff" />
        <path
          v-for="source in visionSources"
          :key="`vision-${source.id}`"
          :d="visionPath(source)"
          fill="#000"
        />
      </mask>
    </defs>
    <rect
      class="scene-lighting__ambient"
      width="100%"
      height="100%"
      fill="#020307"
      :fill-opacity="ambientOpacity"
      :mask="`url(#${darknessMaskId})`"
    />
    <path
      v-for="light in lightSources"
      :key="`glow-${light.id}`"
      :class="animationClass(light)"
      :style="animationStyle(light)"
      :d="path(light)"
      :fill="areaFill(light, `url(#${glowId(light)})`, light.color)"
      :fill-opacity="areaOpacity(light, 0.32)"
    />
    <path
      v-for="light in lightSources"
      :key="`clarity-${light.id}`"
      class="scene-lighting__clarity"
      :d="path(light)"
      :fill="light.color"
      :fill-opacity="strength(light) * 0.14"
    />
    <path
      v-for="light in darknessSources"
      :key="`dark-${light.id}`"
      :class="animationClass(light)"
      :style="animationStyle(light)"
      :d="path(light)"
      :fill="areaFill(light, `url(#${darkId(light)})`, '#01020a')"
      :fill-opacity="areaOpacity(light, 0.85)"
    />
    <rect
      v-if="visionConstrained"
      class="scene-lighting__fog"
      width="100%"
      height="100%"
      fill="#010208"
      :fill-opacity="scene.fogExploration ? 0.86 : 0.98"
      :mask="`url(#${visionMaskId})`"
    />
  </svg>
</template>

<script>
import {
  lightIsActive,
  lightPolygonPath,
  lightTransitionOffsets,
  tokenVisionSource,
} from "@/lib/vtt/lightGeometry";
import { effectiveLight, lumenStrength } from "@/lib/vtt/lightPhotometry";

export default {
  name: "SceneLightingVisual",
  props: {
    uid: { type: Number, required: true },
    scene: { type: Object, required: true },
    lights: { type: Array, default: () => [] },
    walls: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
  },
  computed: {
    darkness() {
      const global = Number(this.scene.globalLightLevel);
      if (Number.isFinite(global)) return 1 - Math.min(1, Math.max(0, global));
      return Math.min(1, Math.max(0, Number(this.scene.darknessLevel) || 0));
    },
    ambientOpacity() {
      return this.darkness;
    },
    activeLights() {
      return this.lights.filter((light) => lightIsActive(light, this.darkness));
    },
    lightSources() {
      return this.activeLights.filter(
        (light) => light.sourceType !== "darkness",
      );
    },
    darknessSources() {
      return this.activeLights.filter(
        (light) => light.sourceType === "darkness",
      );
    },
    tokens() {
      return this.$store?.getters?.["vtt/selectedSceneTokens"] || [];
    },
    tokenVisionSources() {
      return this.tokens
        .map((token) => tokenVisionSource(token, this.scene))
        .filter(Boolean);
    },
    visionSources() {
      return [
        ...this.tokenVisionSources,
        ...this.activeLights.filter((light) => light.providesVision),
      ];
    },
    visionConstrained() {
      return !this.canManage && this.tokenVisionSources.length > 0;
    },
    paths() {
      return Object.fromEntries(
        this.activeLights.map((light) => [
          light.id,
          lightPolygonPath(light, this.walls, this.scene),
        ]),
      );
    },
    viewBox() {
      return `0 0 ${this.scene.width} ${this.scene.height}`;
    },
    darknessMaskId() {
      return `scene-darkness-${this.uid}`;
    },
    visionMaskId() {
      return `scene-vision-${this.uid}`;
    },
  },
  methods: {
    maskId(light) {
      return `light-mask-${this.uid}-${light.id}`;
    },
    glowId(light) {
      return `light-glow-${this.uid}-${light.id}`;
    },
    darkId(light) {
      return `light-dark-${this.uid}-${light.id}`;
    },
    path(light) {
      return this.paths[light.id] || "";
    },
    visionPath(source) {
      return (
        this.paths[source.id] ||
        lightPolygonPath(source, this.walls, this.scene)
      );
    },
    strength(light) {
      return lumenStrength(light) * light.opacity;
    },
    geometry(light) {
      return effectiveLight(light);
    },
    areaFill(light, gradient, solid) {
      return light.sourceType === "area" ? solid : gradient;
    },
    areaOpacity(light, multiplier) {
      return light.sourceType === "area"
        ? this.strength(light) * multiplier
        : 1;
    },
    brightOffset(light) {
      return `${lightTransitionOffsets(light).bright}%`;
    },
    fadeOffset(light) {
      return `${lightTransitionOffsets(light).fade}%`;
    },
    animationClass(light) {
      return `scene-lighting--${light.animation}`;
    },
    animationStyle(light) {
      return {
        "--light-animation-duration": `${3 / Math.max(0.1, light.animationSpeed)}s`,
        "--light-animation-opacity": 1 - light.animationIntensity * 0.45,
        "--light-animation-scale": 1 - light.animationIntensity * 0.05,
        transformOrigin: `${light.x}px ${light.y}px`,
      };
    },
  },
};
</script>
