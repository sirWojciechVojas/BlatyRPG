<template>
  <section class="wall-sound-editor">
    <header class="wall-sound-editor__header">
      <div>
        <strong>{{ $t("vtt.wall.soundRules.title") }}</strong>
        <small>{{ $t("vtt.wall.soundRules.description") }}</small>
      </div>
      <button type="button" @click="addRule">
        + {{ $t("vtt.wall.soundRules.add") }}
      </button>
    </header>

    <p v-if="!rules.length" class="wall-sound-editor__empty">
      {{ $t("vtt.wall.soundRules.empty") }}
    </p>

    <article
      v-for="(rule, index) in rules"
      :key="rule.id"
      class="wall-sound-rule"
      :class="{ 'wall-sound-rule--active': rule.id === activeRuleId }"
      @pointerdown.stop="activate(rule.id)"
    >
      <header>
        <button
          type="button"
          class="wall-sound-rule__select"
          @click="activate(rule.id)"
        >
          {{ index + 1 }}.
          {{ $t(`vtt.wall.soundRules.triggers.${rule.trigger}`) }}
        </button>
        <label class="wall-sound-rule__enabled">
          <input
            type="checkbox"
            :checked="rule.enabled"
            @change="patch(rule.id, { enabled: $event.target.checked })"
          />
          {{ $t("vtt.wall.soundRules.enabled") }}
        </label>
        <button
          type="button"
          class="wall-sound-rule__remove"
          :title="$t('vtt.wall.soundRules.remove')"
          @click="removeRule(rule.id)"
        >
          ×
        </button>
      </header>

      <div class="wall-sound-rule__grid">
        <label>
          {{ $t("vtt.wall.soundRules.trigger") }}
          <select
            :value="rule.trigger"
            @change="patch(rule.id, { trigger: $event.target.value })"
          >
            <option
              v-for="trigger in triggers"
              :key="trigger"
              :value="trigger"
              :disabled="trigger !== 'proximityLoop' && !portalLike"
            >
              {{ $t(`vtt.wall.soundRules.triggers.${trigger}`) }}
            </option>
          </select>
        </label>
        <label>
          {{ $t("vtt.wall.soundRules.geometry") }}
          <select
            :value="rule.geometry.mode"
            @change="patchGeometry(rule.id, { mode: $event.target.value })"
          >
            <option value="points">
              {{ $t("vtt.wall.soundRules.geometries.points") }}
            </option>
            <option value="offsetLine">
              {{ $t("vtt.wall.soundRules.geometries.offsetLine") }}
            </option>
          </select>
        </label>
        <label class="wall-sound-rule__search">
          {{ $t("vtt.wall.soundRules.search") }}
          <input
            v-model.trim="trackSearch"
            type="search"
            :placeholder="$t('vtt.wall.soundRules.searchPlaceholder')"
          />
        </label>
        <label class="wall-sound-rule__track">
          {{ $t("vtt.wall.soundRules.track") }}
          <span class="wall-sound-rule__track-row">
            <select
              :value="rule.trackId || ''"
              @change="selectTrack(rule.id, $event.target.value)"
            >
              <option value="">{{ $t("vtt.wall.soundRules.noTrack") }}</option>
              <option
                v-for="track in tracksForRule(rule)"
                :key="track.id"
                :value="track.id"
                :disabled="!playable(track)"
              >
                {{ track.title }} · {{ track.category
                }}{{
                  playable(track)
                    ? ""
                    : ` — ${$t("vtt.wall.soundRules.unsupported")}`
                }}
              </option>
            </select>
            <button
              type="button"
              :disabled="!playable(track(rule.trackId))"
              :title="$t('vtt.wall.soundRules.previewHint')"
              @click="preview(rule)"
            >
              ▶ {{ $t("vtt.wall.soundRules.preview") }}
            </button>
          </span>
          <small v-if="rule.legacyUrl" class="wall-sound-rule__legacy">
            {{ $t("vtt.wall.soundRules.legacy") }}: {{ rule.legacyUrl }}
          </small>
        </label>
        <label>
          {{ $t("vtt.wall.soundRules.range", { unit }) }}
          <input
            type="number"
            min="0.1"
            max="100000"
            step="0.5"
            :value="rule.range"
            @input="patchNumber(rule.id, 'range', $event.target.value)"
          />
        </label>
        <label>
          {{ $t("vtt.wall.soundRules.zoneCount") }}
          <input
            type="number"
            min="1"
            max="12"
            step="1"
            :value="rule.zoneCount"
            @input="patchNumber(rule.id, 'zoneCount', $event.target.value)"
          />
        </label>
        <label>
          {{ $t("vtt.wall.soundRules.volume") }}
          <input
            type="range"
            min="0"
            max="1"
            step="0.01"
            :value="rule.volume"
            @input="patchNumber(rule.id, 'volume', $event.target.value)"
          />
          <output>{{ Math.round(rule.volume * 100) }}%</output>
        </label>
        <label>
          {{ $t("vtt.wall.soundRules.fadeIn") }}
          <input
            type="number"
            min="0"
            max="10000"
            step="50"
            :value="rule.fadeInMs"
            @input="patchNumber(rule.id, 'fadeInMs', $event.target.value)"
          />
        </label>
        <label>
          {{ $t("vtt.wall.soundRules.fadeOut") }}
          <input
            type="number"
            min="0"
            max="10000"
            step="50"
            :value="rule.fadeOutMs"
            @input="patchNumber(rule.id, 'fadeOutMs', $event.target.value)"
          />
        </label>
      </div>

      <div
        v-if="rule.geometry.mode === 'points'"
        class="wall-sound-rule__points"
      >
        <div class="wall-sound-rule__geometry-title">
          <span>{{ $t("vtt.wall.soundRules.points") }}</span>
          <button type="button" @click="addPoint(rule)">
            + {{ $t("vtt.wall.soundRules.addPoint") }}
          </button>
        </div>
        <label
          v-for="(position, pointIndex) in rule.geometry.points"
          :key="`${rule.id}-${pointIndex}`"
        >
          <span>P{{ pointIndex + 1 }}</span>
          <input
            type="range"
            min="0"
            max="1"
            step="0.001"
            :value="position"
            @input="setPoint(rule, pointIndex, $event.target.value)"
          />
          <output>{{ Math.round(position * 100) }}%</output>
          <button
            type="button"
            :disabled="rule.geometry.points.length <= 1"
            :title="$t('vtt.wall.soundRules.removePoint')"
            @click="removePoint(rule, pointIndex)"
          >
            ×
          </button>
        </label>
        <small>{{ $t("vtt.wall.soundRules.pointMapHint") }}</small>
      </div>

      <div v-else class="wall-sound-rule__offset">
        <label>
          {{ $t("vtt.wall.soundRules.offset", { unit }) }}
          <input
            type="number"
            min="-100000"
            max="100000"
            step="0.5"
            :value="rule.geometry.offset"
            @input="
              patchGeometry(rule.id, { offset: Number($event.target.value) })
            "
          />
        </label>
        <button type="button" @click="flip(rule)">
          ⇄ {{ $t("vtt.wall.soundRules.flip") }}
        </button>
        <small>{{ $t("vtt.wall.soundRules.offsetMapHint") }}</small>
      </div>
    </article>
  </section>
</template>

<script>
import {
  WALL_SOUND_TRIGGERS,
  createWallSoundRule,
  normalizeWallSoundConfig,
  wallSoundTrackPlayable,
} from "@/lib/vtt/wallSound";

export default {
  name: "WallSoundRulesEditor",
  props: {
    modelValue: { type: Object, default: () => ({}) },
    portalLike: { type: Boolean, default: false },
    unit: { type: String, default: "m" },
  },
  emits: ["update:modelValue", "active-rule"],
  data: () => ({ activeRuleId: null, trackSearch: "" }),
  computed: {
    config() {
      return normalizeWallSoundConfig(this.modelValue);
    },
    rules() {
      return this.config.rules;
    },
    triggers() {
      return WALL_SOUND_TRIGGERS;
    },
    tracks() {
      const source = this.$store.state.jukebox || {};
      const values = [
        ...(source.tracks || []),
        ...(source.settingTracks || []),
        ...(source.personalTracks || []),
      ];
      return [
        ...new Map(values.map((track) => [Number(track.id), track])).values(),
      ].sort((left, right) =>
        String(left.title).localeCompare(String(right.title)),
      );
    },
  },
  watch: {
    rules: {
      immediate: true,
      deep: true,
      handler(rules) {
        if (rules.some((rule) => rule.id === this.activeRuleId)) return;
        this.activeRuleId = rules[0]?.id || null;
        this.$emit("active-rule", this.activeRuleId);
      },
    },
  },
  beforeUnmount() {
    this.$store.dispatch("soundEffects/preview", null).catch(() => {});
  },
  methods: {
    playable: wallSoundTrackPlayable,
    track(trackId) {
      return this.tracks.find((track) => Number(track.id) === Number(trackId));
    },
    tracksForRule(rule) {
      const query = this.trackSearch.toLocaleLowerCase();
      if (!query) return this.tracks;
      const selected = Number(rule.trackId);
      return this.tracks.filter(
        (track) =>
          Number(track.id) === selected ||
          `${track.title} ${track.category} ${(track.tags || []).join(" ")}`
            .toLocaleLowerCase()
            .includes(query),
      );
    },
    emitRules(rules) {
      this.$emit("update:modelValue", { version: 2, rules });
    },
    activate(ruleId) {
      this.activeRuleId = ruleId;
      this.$emit("active-rule", ruleId);
    },
    addRule() {
      const rule = createWallSoundRule();
      this.emitRules([...this.rules, rule]);
      this.activate(rule.id);
    },
    removeRule(ruleId) {
      const rules = this.rules.filter((rule) => rule.id !== ruleId);
      this.emitRules(rules);
      if (this.activeRuleId === ruleId) this.activate(rules[0]?.id || null);
    },
    patch(ruleId, changes) {
      this.emitRules(
        this.rules.map((rule) =>
          rule.id === ruleId
            ? createWallSoundRule({ ...rule, ...changes })
            : rule,
        ),
      );
      this.activate(ruleId);
    },
    patchNumber(ruleId, field, value) {
      this.patch(ruleId, { [field]: Number(value) });
    },
    patchGeometry(ruleId, changes) {
      const rule = this.rules.find((item) => item.id === ruleId);
      if (!rule) return;
      this.patch(ruleId, { geometry: { ...rule.geometry, ...changes } });
    },
    selectTrack(ruleId, value) {
      this.patch(ruleId, { trackId: Number(value) || null, legacyUrl: "" });
    },
    preview(rule) {
      const track = this.track(rule.trackId);
      if (this.playable(track)) {
        this.$store.dispatch("soundEffects/preview", track).catch(() => {});
      }
    },
    largestGapPoint(points) {
      const sorted = [0, ...points.map(Number).sort((a, b) => a - b), 1];
      let candidate = 0.5;
      let width = -1;
      for (let index = 1; index < sorted.length; index += 1) {
        const gap = sorted[index] - sorted[index - 1];
        if (gap > width) {
          width = gap;
          candidate = sorted[index - 1] + gap / 2;
        }
      }
      return candidate;
    },
    addPoint(rule) {
      if (rule.geometry.points.length >= 16) return;
      this.patchGeometry(rule.id, {
        points: [
          ...rule.geometry.points,
          this.largestGapPoint(rule.geometry.points),
        ],
      });
    },
    setPoint(rule, index, value) {
      const points = [...rule.geometry.points];
      points[index] = Number(value);
      this.patchGeometry(rule.id, { points });
    },
    removePoint(rule, index) {
      if (rule.geometry.points.length <= 1) return;
      this.patchGeometry(rule.id, {
        points: rule.geometry.points.filter(
          (_value, itemIndex) => itemIndex !== index,
        ),
      });
    },
    flip(rule) {
      const offset = Number(rule.geometry.offset) || 0;
      this.patchGeometry(rule.id, { offset: offset === 0 ? 1 : -offset });
    },
  },
};
</script>
