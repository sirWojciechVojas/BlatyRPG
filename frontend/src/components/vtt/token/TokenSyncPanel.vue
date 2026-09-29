<template>
  <section class="token-sync" :aria-busy="busy">
    <header class="token-sync__intro">
      <div>
        <small>{{ $t("vtt.tokenSync.kicker") }}</small>
        <h2>{{ $t("vtt.tokenSync.title") }}</h2>
        <p>{{ $t("vtt.tokenSync.description") }}</p>
      </div>
      <button type="button" :disabled="busy" @click="load">
        {{ $t("vtt.tokenSync.refresh") }}
      </button>
    </header>

    <p
      v-if="error"
      class="token-sync__notice token-sync__notice--error"
      role="alert"
    >
      {{ errorMessage }}
    </p>
    <p v-if="success" class="token-sync__notice" role="status">
      {{ success }}
    </p>

    <div class="token-sync__layout">
      <section
        class="token-sync__builder"
        aria-labelledby="token-sync-builder-title"
      >
        <h3 id="token-sync-builder-title">
          {{ $t("vtt.tokenSync.newTitle") }}
        </h3>

        <div class="token-sync__source-grid">
          <label>
            <span>{{ $t("vtt.tokenSync.sourceScene") }}</span>
            <select
              v-model.number="sourceSceneId"
              :disabled="busy"
              @change="sourceSceneChanged"
            >
              <option :value="null">
                {{ $t("vtt.tokenSync.chooseScene") }}
              </option>
              <option
                v-for="scene in sourceScenes"
                :key="scene.id"
                :value="scene.id"
              >
                {{ scene.name }}
              </option>
            </select>
          </label>
          <label>
            <span>{{ $t("vtt.tokenSync.sourceToken") }}</span>
            <select
              v-model.number="sourceTokenId"
              :disabled="busy || !sourceSceneId"
              @change="sourceChanged"
            >
              <option :value="null">
                {{ $t("vtt.tokenSync.chooseToken") }}
              </option>
              <option
                v-for="token in sourceTokens"
                :key="token.id"
                :value="token.id"
              >
                {{ token.name }}
              </option>
            </select>
          </label>
        </div>

        <template v-if="sourceToken">
          <fieldset class="token-sync__scenes">
            <legend>{{ $t("vtt.tokenSync.targetScenes") }}</legend>
            <label v-for="scene in candidateScenes" :key="scene.id">
              <input
                v-model="targetSceneIds"
                type="checkbox"
                :value="scene.id"
                :disabled="busy"
                @change="targetsChanged"
              />
              <span>{{ scene.name }}</span>
              <small>{{ candidateCount(scene.id) }}</small>
            </label>
          </fieldset>

          <div class="token-sync__target-heading">
            <h4>{{ $t("vtt.tokenSync.targets") }}</h4>
            <button
              type="button"
              :disabled="busy || !selectableTargets.length"
              @click="toggleAll"
            >
              {{
                allSelected
                  ? $t("vtt.tokenSync.clearSelection")
                  : $t("vtt.tokenSync.selectAll")
              }}
            </button>
          </div>

          <p v-if="!candidateScenes.length" class="token-sync__empty">
            {{ $t("vtt.tokenSync.noTargets") }}
          </p>
          <div v-else class="token-sync__targets">
            <label v-for="token in selectableTargets" :key="token.id">
              <input
                v-model="targetTokenIds"
                type="checkbox"
                :value="token.id"
                :disabled="busy"
                @change="targetsChanged"
              />
              <span class="token-sync__avatar">
                <AuthenticatedImage
                  v-if="token.imageUrl"
                  :src="token.imageUrl"
                  alt=""
                  draggable="false"
                />
                <b v-else>{{ initials(token.name) }}</b>
              </span>
              <span>
                <strong>{{ token.name }}</strong>
                <small>{{ token.sceneName }}</small>
              </span>
              <em v-if="incomingLink(token.id)">
                {{ $t("vtt.tokenSync.alreadyListening") }}
              </em>
            </label>
          </div>

          <div class="token-sync__preview-actions">
            <button
              type="button"
              :disabled="busy || !targetTokenIds.length"
              @click="previewChanges"
            >
              {{ $t("vtt.tokenSync.preview") }}
            </button>
            <span>{{
              $t("vtt.tokenSync.selected", { count: targetTokenIds.length })
            }}</span>
          </div>

          <section
            v-if="preview"
            class="token-sync__preview"
            aria-live="polite"
          >
            <h4>{{ $t("vtt.tokenSync.previewTitle") }}</h4>
            <article v-for="target in preview.targets" :key="target.tokenId">
              <header>
                <strong>{{ tokenLabel(target.tokenId) }}</strong>
                <span>{{
                  $t("vtt.tokenSync.changeCount", {
                    count: target.changes.length,
                  })
                }}</span>
              </header>
              <ul v-if="target.changes.length">
                <li v-for="change in target.changes" :key="change.field">
                  {{ fieldLabel(change.field) }}
                </li>
              </ul>
              <p v-else>{{ $t("vtt.tokenSync.noChanges") }}</p>
            </article>
          </section>

          <footer class="token-sync__builder-actions">
            <button
              type="button"
              :disabled="busy || !previewCurrent"
              @click="apply('transfer')"
            >
              {{ $t("vtt.tokenSync.transferOnce") }}
            </button>
            <button
              type="button"
              class="token-sync__primary"
              :disabled="busy || !previewCurrent || liveSelectionInvalid"
              @click="apply('createLinks')"
            >
              {{ $t("vtt.tokenSync.enableLive") }}
            </button>
          </footer>
        </template>
      </section>

      <section
        class="token-sync__links"
        aria-labelledby="token-sync-links-title"
      >
        <h3 id="token-sync-links-title">
          {{ $t("vtt.tokenSync.linksTitle") }}
        </h3>
        <p v-if="!links.length" class="token-sync__empty">
          {{ $t("vtt.tokenSync.noLinks") }}
        </p>
        <article v-for="group in linkGroups" :key="group.source.id">
          <header>
            <span class="token-sync__avatar">
              <AuthenticatedImage
                v-if="group.source.imageUrl"
                :src="group.source.imageUrl"
                alt=""
              />
              <b v-else>{{ initials(group.source.name) }}</b>
            </span>
            <span>
              <strong>{{ group.source.name }}</strong>
              <small>{{ group.source.sceneName }}</small>
            </span>
          </header>
          <ul>
            <li v-for="link in group.links" :key="link.id">
              <div>
                <strong>{{ tokenLabel(link.targetTokenId) }}</strong>
                <small>
                  {{ sceneLabel(link.targetSceneId) }} ·
                  {{
                    link.enabled
                      ? $t("vtt.tokenSync.active")
                      : $t("vtt.tokenSync.paused")
                  }}
                </small>
                <small>{{ lastSyncLabel(link.lastSyncedAt) }}</small>
                <p
                  v-if="link.divergedFields.length"
                  class="token-sync__diverged"
                >
                  {{
                    $t("vtt.tokenSync.diverged", {
                      fields: link.divergedFields.map(fieldLabel).join(", "),
                    })
                  }}
                </p>
              </div>
              <div class="token-sync__link-actions">
                <button
                  type="button"
                  :disabled="busy"
                  @click="toggleLink(link)"
                >
                  {{
                    link.enabled
                      ? $t("vtt.tokenSync.pause")
                      : $t("vtt.tokenSync.resume")
                  }}
                </button>
                <button type="button" :disabled="busy" @click="applyLink(link)">
                  {{ $t("vtt.tokenSync.syncNow") }}
                </button>
                <button
                  type="button"
                  class="token-sync__danger"
                  :disabled="busy"
                  @click="removeLink(link)"
                >
                  {{ $t("vtt.tokenSync.remove") }}
                </button>
              </div>
            </li>
          </ul>
        </article>
      </section>
    </div>
  </section>
</template>

<script>
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";

const positiveId = (value) => {
  const id = Number(value);
  return Number.isInteger(id) && id > 0 ? id : null;
};

export default {
  name: "TokenSyncPanel",
  components: { AuthenticatedImage },
  props: {
    campaignId: { type: [Number, String], required: true },
    initialSceneId: { type: [Number, String], default: null },
  },
  data: () => ({
    sourceSceneId: null,
    sourceTokenId: null,
    targetSceneIds: [],
    targetTokenIds: [],
    preview: null,
    previewSignature: "",
    success: "",
    successTimer: null,
    initialSceneApplied: false,
  }),
  computed: {
    state() {
      return this.$store.state.vtt || {};
    },
    catalog() {
      return (
        this.state.tokenSyncCatalog || {
          scenes: [],
          tokens: [],
          links: [],
        }
      );
    },
    scenes() {
      return this.catalog.scenes || [];
    },
    tokens() {
      return this.catalog.tokens || [];
    },
    links() {
      return this.catalog.links || [];
    },
    busy() {
      return ["loading", "saving"].includes(this.state.tokenSyncPhase);
    },
    error() {
      return this.state.tokenSyncError;
    },
    errorMessage() {
      const key = String(this.error?.code || "unknown_error");
      const translation = `vtt.tokenSync.errors.${key}`;
      return this.$te(translation)
        ? this.$t(translation)
        : this.$t("vtt.tokenSync.errors.unknown_error");
    },
    sourceScenes() {
      const sceneIds = new Set(
        this.tokens.map((token) => Number(token.sceneId)),
      );
      return this.scenes.filter((scene) => sceneIds.has(Number(scene.id)));
    },
    sourceTokens() {
      return this.tokens.filter(
        (token) => Number(token.sceneId) === Number(this.sourceSceneId),
      );
    },
    sourceToken() {
      return this.tokens.find(
        (token) => Number(token.id) === Number(this.sourceTokenId),
      );
    },
    candidateTokens() {
      if (!this.sourceToken?.characterId) return [];
      return this.tokens.filter(
        (token) =>
          Number(token.id) !== Number(this.sourceToken.id) &&
          Number(token.sceneId) !== Number(this.sourceToken.sceneId) &&
          Number(token.characterId) === Number(this.sourceToken.characterId),
      );
    },
    candidateScenes() {
      const ids = new Set(
        this.candidateTokens.map((token) => Number(token.sceneId)),
      );
      return this.scenes.filter((scene) => ids.has(Number(scene.id)));
    },
    selectableTargets() {
      const sceneIds = new Set(this.targetSceneIds.map(Number));
      return this.candidateTokens.filter((token) =>
        sceneIds.has(Number(token.sceneId)),
      );
    },
    allSelected() {
      return (
        this.selectableTargets.length > 0 &&
        this.selectableTargets.every((token) =>
          this.targetTokenIds.map(Number).includes(Number(token.id)),
        )
      );
    },
    currentSignature() {
      return `${Number(this.sourceTokenId) || 0}:${this.targetTokenIds
        .map(Number)
        .sort((a, b) => a - b)
        .join(",")}`;
    },
    previewCurrent() {
      return (
        Boolean(this.preview) && this.previewSignature === this.currentSignature
      );
    },
    liveSelectionInvalid() {
      const selected = new Set(this.targetTokenIds.map(Number));
      const sourceIsTarget = this.links.some(
        (link) => Number(link.targetTokenId) === Number(this.sourceTokenId),
      );
      const selectedTokenIsSource = this.links.some((link) =>
        selected.has(Number(link.sourceTokenId)),
      );
      return (
        sourceIsTarget ||
        selectedTokenIsSource ||
        this.links.some((link) => selected.has(Number(link.targetTokenId)))
      );
    },
    linkGroups() {
      const groups = new Map();
      for (const link of this.links) {
        const source = this.tokens.find(
          (token) => Number(token.id) === Number(link.sourceTokenId),
        );
        if (!source) continue;
        if (!groups.has(source.id))
          groups.set(source.id, { source, links: [] });
        groups.get(source.id).links.push(link);
      }
      return [...groups.values()];
    },
  },
  watch: {
    initialSceneId() {
      this.initialSceneApplied = this.applyInitialScene();
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    window.clearTimeout(this.successTimer);
  },
  methods: {
    async load() {
      try {
        await this.$store.dispatch("vtt/loadTokenSync");
        if (!this.initialSceneApplied) {
          this.applyInitialScene();
          this.initialSceneApplied = true;
        }
      } catch (_error) {
        // The store keeps a stable error while preserving the current selection.
      }
    },
    applyInitialScene() {
      const sceneId = positiveId(this.initialSceneId);
      if (
        !sceneId ||
        !this.sourceScenes.some((scene) => Number(scene.id) === sceneId)
      )
        return false;
      this.sourceSceneId = sceneId;
      this.sourceSceneChanged();
      return true;
    },
    sourceSceneChanged() {
      if (
        !this.sourceTokens.some(
          (token) => Number(token.id) === Number(this.sourceTokenId),
        )
      ) {
        this.sourceTokenId = null;
      }
      this.resetTargets();
    },
    sourceChanged() {
      this.targetSceneIds = this.candidateScenes.map((scene) =>
        Number(scene.id),
      );
      this.targetTokenIds = [];
      this.invalidatePreview();
    },
    resetTargets() {
      this.targetSceneIds = [];
      this.targetTokenIds = [];
      this.invalidatePreview();
    },
    targetsChanged() {
      const valid = new Set(
        this.selectableTargets.map((token) => Number(token.id)),
      );
      this.targetTokenIds = this.targetTokenIds
        .map(Number)
        .filter((id) => valid.has(id));
      this.invalidatePreview();
    },
    toggleAll() {
      this.targetTokenIds = this.allSelected
        ? []
        : this.selectableTargets.map((token) => Number(token.id));
      this.invalidatePreview();
    },
    invalidatePreview() {
      this.preview = null;
      this.previewSignature = "";
    },
    candidateCount(sceneId) {
      return this.candidateTokens.filter(
        (token) => Number(token.sceneId) === Number(sceneId),
      ).length;
    },
    incomingLink(tokenId) {
      return this.links.find(
        (link) => Number(link.targetTokenId) === Number(tokenId),
      );
    },
    async previewChanges() {
      try {
        const signature = this.currentSignature;
        const preview = await this.$store.dispatch("vtt/previewTokenSync", {
          sourceTokenId: Number(this.sourceTokenId),
          targetTokenIds: this.targetTokenIds.map(Number),
        });
        if (signature !== this.currentSignature) return;
        this.preview = preview;
        this.previewSignature = signature;
      } catch (_error) {
        this.preview = null;
      }
    },
    async apply(action) {
      if (!this.previewCurrent) return;
      const data = {
        sourceTokenId: Number(this.preview.sourceTokenId),
        sourceRevision: Number(this.preview.sourceRevision),
        targets: this.preview.targets.map((target) => ({
          tokenId: Number(target.tokenId),
          revision: Number(target.revision),
        })),
      };
      try {
        await this.$store.dispatch("vtt/commandTokenSync", { action, data });
        this.showSuccess(
          action === "transfer"
            ? this.$t("vtt.tokenSync.transferComplete")
            : this.$t("vtt.tokenSync.liveComplete"),
        );
        this.targetTokenIds = [];
        this.invalidatePreview();
      } catch (_error) {
        // Selection is intentionally retained after an API or revision error.
      }
    },
    async toggleLink(link) {
      await this.runCommand("updateLink", {
        linkId: Number(link.id),
        enabled: !link.enabled,
      });
    },
    async applyLink(link) {
      await this.runCommand("applyLink", { linkId: Number(link.id) });
    },
    async removeLink(link) {
      if (!window.confirm(this.$t("vtt.tokenSync.removeConfirm"))) return;
      await this.runCommand("deleteLink", { linkId: Number(link.id) });
    },
    async runCommand(action, data) {
      try {
        await this.$store.dispatch("vtt/commandTokenSync", { action, data });
        this.showSuccess(this.$t("vtt.tokenSync.linkUpdated"));
      } catch (_error) {
        // The store exposes the error without replacing current UI state.
      }
    },
    showSuccess(message) {
      window.clearTimeout(this.successTimer);
      this.success = message;
      this.successTimer = window.setTimeout(() => {
        this.success = "";
      }, 2600);
    },
    tokenLabel(tokenId) {
      return (
        this.tokens.find((token) => Number(token.id) === Number(tokenId))
          ?.name || `#${tokenId}`
      );
    },
    sceneLabel(sceneId) {
      return (
        this.scenes.find((scene) => Number(scene.id) === Number(sceneId))
          ?.name || `#${sceneId}`
      );
    },
    fieldLabel(field) {
      const key = `vtt.tokenSync.fields.${field}`;
      return this.$te(key) ? this.$t(key) : String(field);
    },
    lastSyncLabel(value) {
      if (!value) return this.$t("vtt.tokenSync.neverSynced");
      const date = new Date(String(value).replace(" ", "T"));
      return this.$t("vtt.tokenSync.lastSync", {
        date: Number.isNaN(date.getTime()) ? value : date.toLocaleString(),
      });
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
