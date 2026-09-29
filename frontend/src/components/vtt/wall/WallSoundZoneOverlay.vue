<template>
  <svg
    v-if="rule"
    ref="surface"
    class="wall-sound-zone-overlay"
    :width="scene.width"
    :height="scene.height"
    :viewBox="`0 0 ${scene.width} ${scene.height}`"
    :aria-label="$t('vtt.wall.soundRules.mapPreview')"
  >
    <g class="wall-sound-zone-overlay__zones">
      <template v-if="rule.geometry.mode === 'points'">
        <g
          v-for="(emitter, emitterIndex) in emitters"
          :key="`emitter-${emitterIndex}`"
        >
          <g v-for="zone in reversedZones" :key="`zone-${zone.index}`">
            <circle
              :cx="emitter.x"
              :cy="emitter.y"
              :r="radius(zone.outer)"
              :style="zoneStyle(zone)"
            />
            <text
              class="wall-sound-zone-overlay__zone-label"
              :x="emitter.x + radius((zone.inner + zone.outer) / 2)"
              :y="emitter.y - screenSize(3)"
              :style="{ fontSize: `${screenSize(9)}px` }"
            >
              {{ zoneLabel(zone) }}
            </text>
          </g>
          <text
            :x="emitter.x + radius(rule.range) + screenSize(8)"
            :y="emitter.y - screenSize(5)"
            :style="{ fontSize: `${screenSize(11)}px` }"
          >
            {{ rule.range }} {{ unit }} · {{ rule.zoneCount }}
          </text>
        </g>
      </template>
      <template v-else>
        <g v-for="zone in reversedZones" :key="`line-zone-${zone.index}`">
          <line
            :x1="segment.x1"
            :y1="segment.y1"
            :x2="segment.x2"
            :y2="segment.y2"
            stroke-linecap="round"
            :style="{
              ...zoneStyle(zone),
              strokeWidth: `${radius(zone.outer) * 2}px`,
            }"
          />
          <text
            class="wall-sound-zone-overlay__zone-label"
            :x="
              (segment.x1 + segment.x2) / 2 +
              segment.nx * radius((zone.inner + zone.outer) / 2)
            "
            :y="
              (segment.y1 + segment.y2) / 2 +
              segment.ny * radius((zone.inner + zone.outer) / 2)
            "
            :style="{ fontSize: `${screenSize(9)}px` }"
          >
            {{ zoneLabel(zone) }}
          </text>
        </g>
        <text
          :x="(segment.x1 + segment.x2) / 2"
          :y="
            (segment.y1 + segment.y2) / 2 - radius(rule.range) - screenSize(8)
          "
          :style="{ fontSize: `${screenSize(11)}px` }"
        >
          {{ rule.range }} {{ unit }} · {{ rule.zoneCount }}
        </text>
      </template>
    </g>

    <line
      class="wall-sound-zone-overlay__wall-hit"
      :x1="wall.x1"
      :y1="wall.y1"
      :x2="wall.x2"
      :y2="wall.y2"
      @dblclick.stop.prevent="addPoint"
    />

    <g v-if="rule.geometry.mode === 'points'">
      <g
        v-for="(emitter, index) in emitters"
        :key="`handle-${index}`"
        class="wall-sound-zone-overlay__handle"
        :transform="`translate(${emitter.x} ${emitter.y})`"
        @pointerdown.stop.prevent="beginPointDrag($event, index)"
        @contextmenu.stop.prevent="removePoint(index)"
      >
        <circle :r="screenSize(10)" />
        <path
          :d="`M${-screenSize(4)} 0H${screenSize(4)}M0 ${-screenSize(4)}V${screenSize(4)}`"
        />
        <title>{{ $t("vtt.wall.soundRules.pointHandleHint") }}</title>
      </g>
    </g>
    <g
      v-else
      class="wall-sound-zone-overlay__handle wall-sound-zone-overlay__offset-handle"
      :transform="`translate(${offsetHandle.x} ${offsetHandle.y})`"
      @pointerdown.stop.prevent="beginOffsetDrag"
    >
      <circle :r="screenSize(11)" />
      <path
        :d="`M0 ${-screenSize(5)}V${screenSize(5)}M${-screenSize(3)} ${-screenSize(2)}L0 ${-screenSize(5)}L${screenSize(3)} ${-screenSize(2)}M${-screenSize(3)} ${screenSize(2)}L0 ${screenSize(5)}L${screenSize(3)} ${screenSize(2)}`"
      />
      <title>{{ $t("vtt.wall.soundRules.offsetHandleHint") }}</title>
    </g>
  </svg>
</template>

<script>
import {
  normalizeWallSoundConfig,
  pointAlongWall,
  projectPointToWall,
  sceneDistanceToPixels,
  scenePixelsToDistance,
  wallSoundEmitters,
  wallSoundOffsetSegment,
  wallSoundZones,
} from "@/lib/vtt/wallSound";

export default {
  name: "WallSoundZoneOverlay",
  props: {
    scene: { type: Object, required: true },
    wall: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    activeRuleId: { type: String, default: null },
    scale: { type: Number, default: 1 },
  },
  emits: ["geometry-change"],
  data: () => ({ drag: null }),
  computed: {
    rule() {
      const rules = normalizeWallSoundConfig(this.config).rules;
      return (
        rules.find((rule) => rule.id === this.activeRuleId) || rules[0] || null
      );
    },
    zones() {
      return this.rule ? wallSoundZones(this.rule) : [];
    },
    reversedZones() {
      return [...this.zones].reverse();
    },
    emitters() {
      return this.rule
        ? wallSoundEmitters(this.wall, this.rule, this.scene)
        : [];
    },
    segment() {
      return this.rule
        ? wallSoundOffsetSegment(this.wall, this.rule.geometry, this.scene)
        : { x1: 0, y1: 0, x2: 0, y2: 0, nx: 0, ny: 1 };
    },
    offsetHandle() {
      return {
        x: (this.segment.x1 + this.segment.x2) / 2,
        y: (this.segment.y1 + this.segment.y2) / 2,
      };
    },
    unit() {
      return String(this.scene.gridUnit || "m");
    },
  },
  beforeUnmount() {
    this.stopDrag();
  },
  methods: {
    screenSize(value) {
      return Number(value) / Math.max(0.1, Number(this.scale) || 1);
    },
    radius(value) {
      return sceneDistanceToPixels(value, this.scene);
    },
    zoneStyle(zone) {
      const ratio = (zone.index + 1) / Math.max(1, this.rule.zoneCount);
      const hue = 42 - ratio * 24;
      return {
        fill: `hsl(${hue} 82% 55% / ${0.075 + ratio * 0.035})`,
        stroke: `hsl(${hue} 90% 68% / 0.62)`,
        strokeWidth: `${this.screenSize(1.25)}px`,
      };
    },
    zoneLabel(zone) {
      const gain = ((zone.innerGain + zone.outerGain) / 2) * 100;
      return `${zone.index + 1} · ${Math.round(gain)}%`;
    },
    localPoint(event) {
      const rect = this.$refs.surface.getBoundingClientRect();
      return {
        x:
          ((event.clientX - rect.left) / rect.width) * Number(this.scene.width),
        y:
          ((event.clientY - rect.top) / rect.height) *
          Number(this.scene.height),
      };
    },
    emitGeometry(geometry) {
      this.$emit("geometry-change", {
        ruleId: this.rule.id,
        geometry: { ...this.rule.geometry, ...geometry },
      });
    },
    beginPointDrag(event, index) {
      this.drag = { type: "point", pointerId: event.pointerId, index };
      this.startListeners();
    },
    beginOffsetDrag(event) {
      this.drag = { type: "offset", pointerId: event.pointerId };
      this.startListeners();
    },
    startListeners() {
      window.addEventListener("pointermove", this.moveDrag);
      window.addEventListener("pointerup", this.stopDrag);
      window.addEventListener("pointercancel", this.stopDrag);
    },
    moveDrag(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      const point = this.localPoint(event);
      if (this.drag.type === "point") {
        const points = [...this.rule.geometry.points];
        points[this.drag.index] = projectPointToWall(this.wall, point);
        this.emitGeometry({ points });
        return;
      }
      const midpoint = pointAlongWall(this.wall, 0.5);
      const offsetPixels =
        (point.x - midpoint.x) * this.segment.nx +
        (point.y - midpoint.y) * this.segment.ny;
      this.emitGeometry({
        offset: scenePixelsToDistance(offsetPixels, this.scene),
      });
    },
    stopDrag(event) {
      if (event && this.drag && event.pointerId !== this.drag.pointerId) return;
      this.drag = null;
      window.removeEventListener("pointermove", this.moveDrag);
      window.removeEventListener("pointerup", this.stopDrag);
      window.removeEventListener("pointercancel", this.stopDrag);
    },
    addPoint(event) {
      if (!this.rule || this.rule.geometry.mode !== "points") return;
      if (this.rule.geometry.points.length >= 16) return;
      const position = projectPointToWall(this.wall, this.localPoint(event));
      this.emitGeometry({ points: [...this.rule.geometry.points, position] });
    },
    removePoint(index) {
      if (this.rule.geometry.points.length <= 1) return;
      this.emitGeometry({
        points: this.rule.geometry.points.filter(
          (_point, pointIndex) => pointIndex !== index,
        ),
      });
    },
  },
};
</script>
