<template>
  <form
    ref="panel"
    class="wall-properties"
    :class="{ 'wall-properties--embedded': floating }"
    :style="floating ? undefined : panelStyle"
    @submit.prevent="save"
    @pointerdown.stop
  >
    <header
      v-if="!floating"
      class="wall-properties__handle"
      :title="$t('vtt.wall.dragProperties')"
      @pointerdown.prevent="startDrag"
    >
      <strong>{{ $t("vtt.wall.properties") }}</strong>
      <button
        type="button"
        :title="$t('vtt.wall.close')"
        @pointerdown.stop
        @click="$emit('close')"
      >
        ×
      </button>
    </header>
    <div class="wall-properties__shell">
      <section
        class="wall-properties__identity"
        :class="{ 'wall-properties__identity--portal': doorLike }"
      >
        <label class="wall-properties__identity-name">
          {{ $t("vtt.wall.name") }}
          <input v-model.trim="form.name" maxlength="150" required />
        </label>
        <label>
          {{ $t("vtt.wall.type") }}
          <select v-model="form.type" @change="applyPreset(form.type)">
            <option v-for="type in types" :key="type" :value="type">
              {{ $t(`vtt.wall.types.${type}`) }}
            </option>
          </select>
        </label>
        <label v-if="doorLike">
          {{ $t("vtt.wall.doorState") }}
          <select v-model="form.doorState">
            <option v-for="state in states" :key="state" :value="state">
              {{ $t(`vtt.wall.states.${state}`) }}
            </option>
          </select>
        </label>
      </section>

      <nav
        class="wall-properties__tabs"
        :class="{ 'wall-properties__tabs--two': !doorLike }"
        :aria-label="$t('vtt.wall.tabsLabel')"
        role="tablist"
      >
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'general'"
          :class="{ 'is-active': activeTab === 'general' }"
          @click="setTab('general')"
        >
          <span aria-hidden="true">◇</span>
          {{ $t("vtt.wall.propertyTabs.general") }}
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'sound'"
          :class="{ 'is-active': activeTab === 'sound' }"
          @click="setTab('sound')"
        >
          <span aria-hidden="true">◉</span>
          {{ $t("vtt.wall.propertyTabs.sound") }}
          <small>{{ soundRuleCount }}</small>
        </button>
        <button
          v-if="doorLike"
          type="button"
          role="tab"
          :aria-selected="activeTab === 'animation'"
          :class="{ 'is-active': activeTab === 'animation' }"
          @click="setTab('animation')"
        >
          <span aria-hidden="true">↗</span>
          {{ $t("vtt.wall.propertyTabs.animation") }}
        </button>
      </nav>

      <div class="wall-properties__viewport">
        <section
          v-show="activeTab === 'general'"
          class="wall-properties__tab-panel"
          role="tabpanel"
        >
          <section class="wall-properties__card">
            <header class="wall-properties__card-heading">
              <div>
                <h3>{{ $t("vtt.wall.propertySections.behavior") }}</h3>
                <small>{{
                  $t("vtt.wall.propertySections.behaviorHint")
                }}</small>
              </div>
            </header>
            <div
              class="wall-properties__field-grid wall-properties__field-grid--behavior"
            >
              <label>
                {{ $t("vtt.wall.wallType") }}
                <select v-model="form.wallType">
                  <option
                    v-for="value in wallTypes"
                    :key="value"
                    :value="value"
                  >
                    {{ $t(`vtt.wall.wallTypes.${value}`) }}
                  </option>
                </select>
              </label>
              <label>
                {{ $t("vtt.wall.doorType") }}
                <select v-model="form.doorType">
                  <option
                    v-for="value in doorTypes"
                    :key="value"
                    :value="value"
                  >
                    {{ $t(`vtt.wall.doorTypes.${value}`) }}
                  </option>
                </select>
              </label>
              <label>
                {{ $t("vtt.wall.restrictionType") }}
                <select v-model="form.restrictionType">
                  <option
                    v-for="value in restrictions"
                    :key="value"
                    :value="value"
                  >
                    {{ $t(`vtt.wall.restrictions.${value}`) }}
                  </option>
                </select>
              </label>
              <label
                v-if="
                  form.doorType === 'window' ||
                  form.restrictionType === 'proximity'
                "
              >
                {{ $t("vtt.wall.proximityThreshold") }}
                <input
                  v-model.number="form.proximityThreshold"
                  type="number"
                  min="0"
                  max="100000"
                  step="0.5"
                />
              </label>
            </div>
          </section>

          <section class="wall-properties__card">
            <header class="wall-properties__card-heading">
              <div>
                <h3>{{ $t("vtt.wall.propertySections.geometry") }}</h3>
                <small>{{
                  $t("vtt.wall.propertySections.geometryHint")
                }}</small>
              </div>
            </header>
            <div class="wall-properties__geometry-row">
              <div class="wall-properties__coordinates">
                <label v-for="coordinate in coordinates" :key="coordinate">
                  {{ coordinate.toUpperCase() }}
                  <input
                    v-model.number="form[coordinate]"
                    type="number"
                    min="0"
                    step="0.5"
                  />
                </label>
              </div>
              <div class="wall-properties__appearance">
                <label>
                  {{ $t("vtt.wall.color") }}
                  <input v-model="form.color" type="color" />
                </label>
                <label
                  class="wall-properties__check wall-properties__check--color"
                >
                  <input v-model="form.customColor" type="checkbox" />
                  <span>
                    {{ $t("vtt.wall.customColorShort") }}
                    <small>{{ $t("vtt.wall.customColorHint") }}</small>
                  </span>
                </label>
              </div>
            </div>
          </section>

          <section class="wall-properties__card wall-properties__card--rules">
            <header class="wall-properties__card-heading">
              <div>
                <h3>{{ $t("vtt.wall.propertySections.rules") }}</h3>
                <small>{{ $t("vtt.wall.propertySections.rulesHint") }}</small>
              </div>
            </header>
            <div class="wall-properties__toggles">
              <label
                v-for="flag in flags"
                :key="flag.field"
                class="wall-properties__check"
                :class="{ 'is-checked': form[flag.field] }"
              >
                <input v-model="form[flag.field]" type="checkbox" />
                <span>{{ $t(flag.label) }}</span>
              </label>
            </div>
          </section>
        </section>

        <section
          v-show="activeTab === 'sound'"
          class="wall-properties__tab-panel wall-properties__tab-panel--sound"
          role="tabpanel"
        >
          <WallSoundRulesEditor
            v-model="form.soundConfig"
            :portal-like="doorLike"
            :unit="sceneUnit"
            @active-rule="$emit('active-sound-rule', $event)"
          />
        </section>

        <section
          v-if="doorLike"
          v-show="activeTab === 'animation'"
          class="wall-properties__tab-panel"
          role="tabpanel"
        >
          <section class="wall-properties__card">
            <header class="wall-properties__card-heading">
              <div>
                <h3>{{ $t("vtt.wall.propertySections.animation") }}</h3>
                <small>{{
                  $t("vtt.wall.propertySections.animationHint")
                }}</small>
              </div>
            </header>
            <div
              class="wall-properties__field-grid wall-properties__field-grid--animation"
            >
              <label>
                {{ $t("vtt.wall.animationType") }}
                <select v-model="form.animationConfig.type">
                  <option value="none">
                    {{ $t("vtt.wall.animations.none") }}
                  </option>
                  <option value="swing">
                    {{ $t("vtt.wall.animations.swing") }}
                  </option>
                  <option value="slide">
                    {{ $t("vtt.wall.animations.slide") }}
                  </option>
                  <option value="fade">
                    {{ $t("vtt.wall.animations.fade") }}
                  </option>
                </select>
              </label>
              <label>
                {{ $t("vtt.wall.animationDuration") }}
                <input
                  v-model.number="form.animationConfig.duration"
                  type="number"
                  min="50"
                  max="10000"
                  step="50"
                />
              </label>
              <label>
                {{ $t("vtt.wall.animationStrength") }}
                <input
                  v-model.number="form.animationConfig.strength"
                  type="number"
                  min="0"
                  max="2"
                  step="0.1"
                />
              </label>
              <label>
                {{ $t("vtt.wall.animationDirection") }}
                <select v-model="form.animationConfig.direction">
                  <option value="forward">{{ $t("vtt.wall.forward") }}</option>
                  <option value="reverse">{{ $t("vtt.wall.reverse") }}</option>
                </select>
              </label>
              <label class="wall-properties__wide">
                {{ $t("vtt.wall.assetUrl") }}
                <input
                  v-model.trim="form.animationConfig.assetUrl"
                  type="url"
                  maxlength="2048"
                />
              </label>
            </div>
          </section>
        </section>
      </div>

      <footer class="wall-properties__footer">
        <span
          class="wall-properties__footer-meta"
          :class="{ 'is-dirty': hasChanges }"
        >
          <i aria-hidden="true" />
          <span>
            {{
              $t(
                hasChanges
                  ? "vtt.wall.unsavedChanges"
                  : "vtt.wall.noPendingChanges",
              )
            }}
            · {{ $t("vtt.wall.configuredRules", { count: soundRuleCount }) }}
          </span>
        </span>
        <div>
          <button type="button" class="wall-properties__cancel" @click="cancel">
            {{ $t("vtt.wall.cancel") }}
          </button>
          <button type="submit" class="wall-properties__save" :disabled="busy">
            {{ $t("vtt.wall.save") }}
          </button>
        </div>
      </footer>
    </div>
  </form>
</template>

<script>
import { wallColor } from "@/lib/vtt/wallGeometry";
import { WALL_PRESETS, wallPreset } from "@/lib/vtt/wallPresets";
import { normalizeWallSoundConfig } from "@/lib/vtt/wallSound";
import WallSoundRulesEditor from "./WallSoundRulesEditor.vue";

const snapshot = (wall) => ({
  name: wall.name,
  type: wall.type,
  doorState: wall.doorState || "closed",
  x1: wall.x1,
  y1: wall.y1,
  x2: wall.x2,
  y2: wall.y2,
  color: wall.color || wallColor(wall),
  customColor: Boolean(wall.color),
  blocksMovement: wall.blocksMovement,
  blocksSight: wall.blocksSight,
  blocksLight: wall.blocksLight,
  blocksSound: wall.blocksSound,
  wallType: wall.wallType || "solid",
  doorType: wall.doorType || "none",
  restrictionType: wall.restrictionType || "normal",
  proximityThreshold: Number(wall.proximityThreshold) || 10,
  playerOperable: wall.playerOperable !== false,
  soundConfig: normalizeWallSoundConfig(wall.soundConfig),
  animationConfig: {
    type: "none",
    duration: 250,
    strength: 1,
    direction: "forward",
    assetUrl: "",
    ...(wall.animationConfig || {}),
  },
  enabled: wall.enabled,
  hidden: wall.hidden,
});

const equivalent = (left, right) =>
  left && right && typeof left === "object" && typeof right === "object"
    ? JSON.stringify(left) === JSON.stringify(right)
    : left === right;

export default {
  name: "WallPropertiesPanel",
  components: { WallSoundRulesEditor },
  props: {
    wall: { type: Object, required: true },
    scene: { type: Object, default: null },
    busy: { type: Boolean, default: false },
    floating: { type: Boolean, default: false },
    geometryPatch: { type: Object, default: null },
  },
  emits: [
    "save",
    "close",
    "preview-change",
    "preview-clear",
    "active-sound-rule",
  ],
  data() {
    return {
      form: snapshot(this.wall),
      activeTab: "general",
      position: { left: null, top: 132 },
      drag: null,
      states: ["closed", "open", "locked"],
      wallTypes: ["solid", "terrain", "invisible", "ethereal", "custom"],
      doorTypes: ["none", "door", "secret", "window"],
      restrictions: ["normal", "limited", "proximity"],
      coordinates: ["x1", "y1", "x2", "y2"],
      flags: [
        { field: "blocksMovement", label: "vtt.wall.movement" },
        { field: "blocksSight", label: "vtt.wall.sight" },
        { field: "blocksLight", label: "vtt.wall.light" },
        { field: "blocksSound", label: "vtt.wall.sound" },
        { field: "playerOperable", label: "vtt.wall.playerOperable" },
        { field: "enabled", label: "vtt.wall.enabled" },
        { field: "hidden", label: "vtt.wall.hidden" },
      ],
    };
  },
  watch: {
    "wall.revision": "reset",
    doorLike(value) {
      if (!value && this.activeTab === "animation") this.setTab("general");
    },
    "form.soundConfig": {
      deep: true,
      handler(value) {
        if (this.activeTab === "sound") {
          this.$emit("preview-change", normalizeWallSoundConfig(value));
        }
      },
    },
    geometryPatch: {
      deep: true,
      handler: "applyGeometryPatch",
    },
  },
  computed: {
    panelStyle() {
      if (this.position.left === null) return {};
      return {
        left: `${this.position.left}px`,
        right: "auto",
        top: `${this.position.top}px`,
      };
    },
    types() {
      return Object.values(WALL_PRESETS).map((preset) => preset.type);
    },
    doorLike() {
      return ["door", "secret", "window"].includes(this.form.doorType);
    },
    sceneUnit() {
      return String(this.scene?.gridUnit || "m");
    },
    soundRuleCount() {
      return normalizeWallSoundConfig(this.form.soundConfig).rules.length;
    },
    changeSet() {
      const current = snapshot(this.wall);
      const candidate = {
        ...this.form,
        doorState: this.doorLike ? this.form.doorState : "closed",
        color: this.form.customColor ? this.form.color : null,
      };
      delete candidate.customColor;
      return Object.fromEntries(
        Object.entries(candidate).filter(([key, value]) => {
          const previous = key === "color" ? this.wall.color : current[key];
          return !equivalent(value, previous);
        }),
      );
    },
    hasChanges() {
      return Object.keys(this.changeSet).length > 0;
    },
  },
  mounted() {
    if (!this.floating) {
      this.$nextTick(this.placeInitially);
      window.addEventListener("resize", this.keepInViewport);
    }
  },
  beforeUnmount() {
    this.$emit("preview-clear");
    this.stopDrag();
    if (!this.floating)
      window.removeEventListener("resize", this.keepInViewport);
  },
  methods: {
    bounds(left, top) {
      const panel = this.$refs.panel;
      const width = panel?.offsetWidth || 0;
      const height = panel?.offsetHeight || 0;
      return {
        left: Math.max(0, Math.min(left, window.innerWidth - width)),
        top: Math.max(0, Math.min(top, window.innerHeight - height)),
      };
    },
    placeInitially() {
      this.position = this.bounds(16, 132);
    },
    startDrag(event) {
      if (event.button !== 0 || event.target.closest("button")) return;
      const panel = this.$refs.panel;
      if (!panel) return;
      const rect = panel.getBoundingClientRect();
      this.position = { left: rect.left, top: rect.top };
      this.drag = {
        pointerId: event.pointerId,
        offsetX: event.clientX - rect.left,
        offsetY: event.clientY - rect.top,
      };
      window.addEventListener("pointermove", this.moveDrag);
      window.addEventListener("pointerup", this.stopDrag);
      window.addEventListener("pointercancel", this.stopDrag);
    },
    moveDrag(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      this.position = this.bounds(
        event.clientX - this.drag.offsetX,
        event.clientY - this.drag.offsetY,
      );
    },
    stopDrag(event) {
      if (event && this.drag && event.pointerId !== this.drag.pointerId) return;
      this.drag = null;
      window.removeEventListener("pointermove", this.moveDrag);
      window.removeEventListener("pointerup", this.stopDrag);
      window.removeEventListener("pointercancel", this.stopDrag);
    },
    keepInViewport() {
      if (this.position.left === null) return this.placeInitially();
      this.position = this.bounds(this.position.left, this.position.top);
    },
    reset() {
      this.form = snapshot(this.wall);
      if (this.activeTab === "sound") {
        this.$emit(
          "preview-change",
          normalizeWallSoundConfig(this.form.soundConfig),
        );
      }
    },
    cancel() {
      this.reset();
      this.$emit("preview-clear");
      this.$emit("close");
    },
    applyPreset(name) {
      this.form = { ...this.form, ...wallPreset(name) };
    },
    setTab(tab) {
      this.activeTab = tab;
      if (tab === "sound") {
        this.$emit(
          "preview-change",
          normalizeWallSoundConfig(this.form.soundConfig),
        );
      } else {
        this.$emit("preview-clear");
      }
    },
    applyGeometryPatch(patch) {
      if (!patch?.ruleId || !patch.geometry) return;
      const config = normalizeWallSoundConfig(this.form.soundConfig);
      if (!config.rules.some((rule) => rule.id === patch.ruleId)) return;
      this.form.soundConfig = {
        ...config,
        rules: config.rules.map((rule) =>
          rule.id === patch.ruleId
            ? { ...rule, geometry: { ...rule.geometry, ...patch.geometry } }
            : rule,
        ),
      };
    },
    save() {
      if (this.hasChanges) this.$emit("save", this.changeSet);
      else this.$emit("close");
    },
  },
};
</script>
