<template>
  <aside
    class="scene-settings-preview"
    :aria-label="$t('vtt.scene.settings.preview.label')"
  >
    <header>
      <span>{{ $t("vtt.scene.settings.preview.title") }}</span>
      <strong>{{ previewStatus }}</strong>
    </header>
    <div class="scene-settings-preview__frame">
      <div class="scene-settings-preview__map" :style="mapStyle">
        <div class="scene-settings-preview__board">
          <svg
            class="scene-settings-preview__terrain"
            :viewBox="`0 0 ${previewWidth} ${previewHeight}`"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <rect
              class="scene-settings-preview__ground"
              :width="previewWidth"
              :height="previewHeight"
            />
            <path
              class="scene-settings-preview__ground-detail"
              d="M0 48 C92 18 155 76 230 51 S375 22 462 63 S555 82 600 55 V0 H0 Z"
            />
            <path
              class="scene-settings-preview__water"
              d="M-30 248 C74 179 134 270 226 206 S397 134 476 189 S563 243 630 189"
            />
            <path
              class="scene-settings-preview__road"
              d="M55 368 C95 280 167 249 224 225 S343 160 370 79 S431 9 480 -25"
            />
            <g class="scene-settings-preview__ruins">
              <path d="M392 195 H551 V303 H517 V231 H426 V282 H392 Z" />
              <path d="M426 231 H517 V247 H426 Z" />
            </g>
            <g class="scene-settings-preview__grove">
              <circle cx="92" cy="83" r="34" />
              <circle cx="143" cy="70" r="27" />
              <circle cx="126" cy="112" r="31" />
            </g>
            <g class="scene-settings-preview__rocks">
              <path d="M253 77 l31 -28 37 19 -8 42 -48 5 Z" />
              <path d="M286 127 l22 -13 28 21 -16 27 -34 -5 Z" />
            </g>
          </svg>

          <svg
            v-if="pattern"
            class="scene-settings-preview__grid"
            :viewBox="`0 0 ${previewWidth} ${previewHeight}`"
            preserveAspectRatio="none"
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

          <span
            class="scene-settings-preview__token scene-settings-preview__token--hero"
            aria-hidden="true"
          />
          <span
            class="scene-settings-preview__token scene-settings-preview__token--enemy"
            aria-hidden="true"
          />
          <div class="scene-settings-preview__ambient" :style="ambientStyle" />
          <div
            v-if="scene.fogEnabled"
            class="scene-settings-preview__fog scene-settings-preview__fog--explored"
            :style="exploredFogStyle"
          />
          <div
            v-if="scene.fogEnabled"
            class="scene-settings-preview__fog scene-settings-preview__fog--unexplored"
            :style="unexploredFogStyle"
          />
        </div>
      </div>
    </div>
    <dl>
      <div>
        <dt>{{ $t("vtt.scene.fields.dimensions") }}</dt>
        <dd>{{ safeWidth }} × {{ safeHeight }} px</dd>
      </div>
      <div>
        <dt>{{ $t("vtt.scene.settings.grid") }}</dt>
        <dd>{{ gridSummary }}</dd>
      </div>
      <div>
        <dt>{{ $t("vtt.scene.fields.globalLightLevel") }}</dt>
        <dd>{{ lightPercent }}%</dd>
      </div>
      <div>
        <dt>{{ $t("vtt.fog.settings") }}</dt>
        <dd>
          {{
            scene.fogEnabled
              ? $t("vtt.scene.settings.enabled")
              : $t("vtt.scene.settings.disabled")
          }}
        </dd>
      </div>
    </dl>
  </aside>
</template>

<script>
import { getCurrentInstance } from "vue";
import { buildGridPattern } from "@/lib/vtt/grid";

const clamped = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, Number(value) || 0));

export default {
  name: "SceneSettingsPreview",
  props: {
    scene: { type: Object, required: true },
  },
  data() {
    return {
      patternId: `scene-settings-grid-${getCurrentInstance().uid}`,
      previewWidth: 600,
      previewHeight: 340,
    };
  },
  computed: {
    safeWidth() {
      return Math.max(1, Number(this.scene.width) || 1);
    },
    safeHeight() {
      return Math.max(1, Number(this.scene.height) || 1);
    },
    pattern() {
      return buildGridPattern(this.scene);
    },
    lightPercent() {
      return Math.round(clamped(this.scene.globalLightLevel, 0, 1) * 100);
    },
    mapStyle() {
      const padding = clamped(this.scene.padding, 0, 5000);
      const longest = Math.max(this.safeWidth, this.safeHeight);
      return {
        backgroundColor: this.scene.backgroundColor,
        padding: `${Math.min(10, (padding / longest) * 200)}px`,
      };
    },
    ambientStyle() {
      return {
        opacity: (1 - clamped(this.scene.globalLightLevel, 0, 1)) * 0.82,
      };
    },
    exploredFogStyle() {
      return {
        backgroundColor: this.scene.fogUnexploredColor,
        opacity: clamped(this.scene.fogExploredOpacity, 0, 1),
      };
    },
    unexploredFogStyle() {
      return {
        backgroundColor: this.scene.fogUnexploredColor,
        opacity: clamped(this.scene.fogUnexploredOpacity, 0, 1),
        filter: `blur(${clamped(this.scene.fogEdgeSoftness, 0, 200) * 0.08}px)`,
      };
    },
    gridSummary() {
      if (!this.pattern) return this.$t("vtt.scene.grid.gridless");
      return `${this.$t(`vtt.scene.grid.${this.scene.gridType}`)} · ${this.scene.gridSize} px`;
    },
    previewStatus() {
      return `${this.$t("vtt.scene.settings.preview.sample")} · ${this.lightPercent}%`;
    },
  },
};
</script>
