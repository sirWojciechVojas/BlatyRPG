<template>
  <section class="token-settings-preview">
    <header>
      <div>
        <strong>{{ $t("vtt.token.settings.previewTitle") }}</strong>
        <small>{{ $t("vtt.token.settings.previewHint") }}</small>
      </div>
      <div class="token-settings-preview__modes" role="group">
        <button
          type="button"
          :class="{ active: selected }"
          :aria-pressed="selected"
          @click="selected = true"
        >
          {{ $t("vtt.token.settings.previewActive") }}
        </button>
        <button
          type="button"
          :class="{ active: !selected }"
          :aria-pressed="!selected"
          @click="selected = false"
        >
          {{ $t("vtt.token.settings.previewInactive") }}
        </button>
      </div>
    </header>

    <div class="token-settings-preview__stage">
      <svg v-if="pattern" aria-hidden="true">
        <defs>
          <pattern
            id="token-settings-preview-grid"
            patternUnits="userSpaceOnUse"
            :width="pattern.width"
            :height="pattern.height"
          >
            <path :d="pattern.path" />
          </pattern>
        </defs>
        <rect
          width="100%"
          height="100%"
          fill="url(#token-settings-preview-grid)"
        />
      </svg>
      <div
        class="token-settings-preview__token-wrap"
        :class="{
          'is-selected': selected,
          'is-hidden': previewToken.hidden,
          'is-locked': previewToken.locked,
        }"
        :style="boxStyle"
      >
        <i class="scene-token-facing" :style="facingStyle" aria-hidden="true" />
        <span
          v-if="previewToken.facingHandleEnabled"
          class="token-settings-preview__handle token-settings-preview__handle--facing"
          :style="facingStyle"
          aria-hidden="true"
          >▲</span
        >
        <span
          v-if="previewToken.rotationHandleEnabled"
          class="token-settings-preview__handle token-settings-preview__handle--rotation"
          aria-hidden="true"
          >↻</span
        >
        <span
          class="token-settings-preview__artwork"
          :class="`scene-token--${previewToken.disposition}`"
          :style="artworkStyle"
        >
          <AuthenticatedImage
            v-if="previewToken.imageUrl && !imageFailed"
            :src="previewToken.imageUrl"
            alt=""
            @error="imageFailed = true"
          />
          <b v-else>{{ initials }}</b>
        </span>
        <TokenResourceOverlay
          v-if="informationVisible"
          :resources="previewToken.resources"
          :bar-position="previewToken.resourceBarPosition"
        />
        <TokenInfoStack v-if="informationVisible" :token="previewToken" />
      </div>
    </div>

    <footer>
      <span>{{ sizeLabel }}</span>
      <span>↻ {{ Math.round(previewToken.rotation) }}°</span>
      <span>⌁ {{ Math.round(previewToken.facing) }}°</span>
      <span>↥ {{ previewToken.elevation }}</span>
    </footer>
  </section>
</template>

<script>
import TokenInfoStack from "./TokenInfoStack.vue";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import TokenResourceOverlay from "./TokenResourceOverlay.vue";
import { buildGridPattern } from "@/lib/vtt/grid";
import {
  tokenPreviewMetrics,
  tokenSettingsPreview,
} from "@/lib/vtt/tokenSettingsPreview";

export default {
  name: "TokenSettingsPreview",
  components: { AuthenticatedImage, TokenInfoStack, TokenResourceOverlay },
  props: {
    draft: { type: Object, required: true },
    token: { type: Object, required: true },
    gridType: { type: String, default: "square" },
  },
  data: () => ({ selected: true, imageFailed: false }),
  computed: {
    previewToken() {
      return tokenSettingsPreview(this.draft, this.token);
    },
    metrics() {
      return tokenPreviewMetrics(this.draft);
    },
    pattern() {
      return buildGridPattern({
        gridType: this.gridType,
        gridSize: this.metrics.cellSize,
      });
    },
    boxStyle() {
      return {
        width: `${this.metrics.width}px`,
        height: `${this.metrics.height}px`,
        top: this.previewTop,
      };
    },
    previewTop() {
      return {
        above: "61%",
        "top-overlap": "55%",
        "bottom-overlap": "45%",
        below: "39%",
      }[this.previewToken.resourceBarPosition];
    },
    artworkStyle() {
      return { transform: `rotate(${this.previewToken.rotation}deg)` };
    },
    facingStyle() {
      return { transform: `rotate(${this.previewToken.facing}deg)` };
    },
    informationVisible() {
      return this.selected || this.previewToken.showInfoUnselected;
    },
    initials() {
      return String(this.previewToken.name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
    sizeLabel() {
      return `${this.draft.widthCells || 0}×${this.draft.heightCells || 0}`;
    },
  },
  watch: {
    "draft.imageUrl"() {
      this.imageFailed = false;
    },
  },
};
</script>
