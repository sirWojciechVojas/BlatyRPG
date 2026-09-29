<template>
  <section class="admin-compendium">
    <header class="admin-compendium__toolbar">
      <div>
        <h2>{{ $t("admin.compendium.title") }}</h2>
        <p>{{ $t("admin.compendium.description") }}</p>
      </div>
      <label>
        <span>{{ $t("admin.compendium.world") }}</span>
        <select v-model.number="selectedUniverseId" @change="worldChanged">
          <option
            v-for="world in data.worlds"
            :key="world.universeId"
            :value="world.universeId"
          >
            {{ world.name }} · {{ world.entries }}
          </option>
        </select>
      </label>
      <nav :aria-label="$t('admin.compendium.views')">
        <button
          v-for="view in views"
          :key="view"
          type="button"
          :class="{ active: mode === view }"
          @click="setMode(view)"
        >
          {{ $t(`admin.compendium.view.${view}`) }}
        </button>
      </nav>
      <button
        type="button"
        class="admin-compendium__new-world"
        :aria-expanded="showCreateWorld && !editingWorld"
        @click="openCreateWorld"
      >
        + {{ $t("admin.compendium.newWorld") }}
      </button>
      <button type="button" :disabled="loading" @click="load">↻</button>
    </header>

    <p v-if="error" class="admin-alert error" role="alert">{{ error }}</p>
    <div v-if="loading && !data.worlds.length" class="admin-loading">
      <i></i>{{ $t("admin.compendium.loading") }}
    </div>

    <div
      v-else-if="mode === 'overview'"
      class="admin-compendium__overview"
      :class="{ 'has-create': showCreateWorld }"
    >
      <div class="admin-compendium__kpis">
        <article v-for="metric in metricCards" :key="metric.key">
          <span>{{ $t(`admin.compendium.metric.${metric.key}`) }}</span>
          <strong>{{ metric.value }}</strong>
        </article>
      </div>
      <form
        v-if="showCreateWorld"
        class="admin-compendium__create-world"
        @submit.prevent="saveWorld"
      >
        <header>
          <strong>{{
            $t(
              editingWorld
                ? "admin.compendium.editWorldTitle"
                : "admin.compendium.createWorld",
            )
          }}</strong>
          <small>{{
            $t(
              editingWorld
                ? "admin.compendium.editWorldHint"
                : "admin.compendium.createWorldHint",
            )
          }}</small>
        </header>
        <label>
          <span>{{ $t("admin.compendium.worldName") }}</span>
          <input
            v-model.trim="worldDraft.name"
            required
            maxlength="100"
            autocomplete="off"
          />
        </label>
        <label>
          <span>{{ $t("admin.compendium.worldCode") }}</span>
          <input
            v-model.trim="worldDraft.code"
            maxlength="50"
            pattern="[a-z][a-z0-9_]*"
            :disabled="editingWorld"
            :placeholder="$t('admin.compendium.worldCodeHint')"
            autocomplete="off"
          />
        </label>
        <label>
          <span>{{ $t("admin.compendium.rpgSystem") }}</span>
          <select v-model.number="worldDraft.systemId" required>
            <option :value="null" disabled>
              {{ $t("admin.compendium.chooseRpgSystem") }}
            </option>
            <option
              v-for="system in data.systems"
              :key="system.id"
              :value="system.id"
            >
              {{ system.name }} · {{ system.code }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("admin.compendium.worldDescription") }}</span>
          <input v-model.trim="worldDraft.description" maxlength="5000" />
        </label>
        <label class="admin-compendium__create-world-active">
          <input v-model="worldDraft.isActive" type="checkbox" />
          <span>{{ $t("admin.compendium.gameAvailable") }}</span>
        </label>
        <div class="admin-compendium__create-world-actions">
          <button type="button" @click="closeWorldForm">
            {{ $t("admin.compendium.cancel") }}
          </button>
          <button
            type="submit"
            :disabled="creatingWorld || !data.systems.length"
          >
            {{
              creatingWorld
                ? $t(
                    editingWorld
                      ? "admin.compendium.updatingWorld"
                      : "admin.compendium.creatingWorld",
                  )
                : $t(
                    editingWorld
                      ? "admin.compendium.saveWorld"
                      : "admin.compendium.addWorld",
                  )
            }}
          </button>
        </div>
      </form>
      <div class="admin-compendium__worlds">
        <div class="admin-compendium__worlds-head" aria-hidden="true">
          <span>{{ $t("admin.compendium.world") }}</span>
          <span>{{ $t("admin.compendium.rpgSystem") }}</span>
          <span>{{ $t("admin.compendium.owner") }}</span>
          <span>{{ $t("admin.compendium.published") }}</span>
          <span>{{ $t("admin.compendium.verified") }}</span>
          <span>{{ $t("admin.compendium.assets") }}</span>
          <span>{{ $t("admin.compendium.status") }}</span>
        </div>
        <button
          v-for="world in data.worlds"
          :key="world.universeId"
          type="button"
          :class="{ active: world.universeId === selectedUniverseId }"
          @click="selectWorld(world)"
        >
          <span class="admin-compendium__world-title">
            <strong>{{ world.name }}</strong>
            <code>{{ world.code }}</code>
          </span>
          <span class="admin-compendium__system">
            <strong>{{
              world.systemName || $t("admin.compendium.noRpgSystem")
            }}</strong>
            <code v-if="world.systemCode">{{ world.systemCode }}</code>
          </span>
          <span
            >{{ $t("admin.compendium.owner") }}:
            {{
              world.owner?.username || $t("admin.compendium.adminOnly")
            }}</span
          >
          <span
            >{{ world.published }}/{{ world.entries }}
            {{ $t("admin.compendium.published") }}</span
          >
          <span
            >{{ world.verified }} {{ $t("admin.compendium.verified") }}</span
          >
          <span
            >{{ world.assetsAvailable }}/{{ world.assetsRegistered }}
            {{ $t("admin.compendium.assets") }}</span
          >
          <i :class="{ warning: world.needsReview || world.unresolvedLinks }">
            {{ world.needsReview }} {{ $t("admin.compendium.toReview") }} ·
            {{ world.unresolvedLinks }} {{ $t("admin.compendium.unresolved") }}
          </i>
        </button>
      </div>
      <section v-if="currentWorld" class="admin-compendium__world-detail">
        <dl>
          <div>
            <dt>{{ $t("admin.compendium.campaigns") }}</dt>
            <dd>{{ currentWorld.campaigns }}</dd>
          </div>
          <div>
            <dt>{{ $t("admin.compendium.editors") }}</dt>
            <dd>{{ currentWorld.editors }}</dd>
          </div>
          <div>
            <dt>{{ $t("admin.compendium.drafts") }}</dt>
            <dd>{{ currentWorld.drafts }}</dd>
          </div>
          <div>
            <dt>{{ $t("admin.compendium.mechanics") }}</dt>
            <dd>
              {{ currentWorld.usableProfiles }}/{{
                currentWorld.mechanicalProfiles
              }}
            </dd>
          </div>
          <div>
            <dt>{{ $t("admin.compendium.storage") }}</dt>
            <dd>
              {{ formatBytes(currentWorld.usedBytes) }} /
              {{ formatBytes(currentWorld.storageLimitBytes) }}
            </dd>
          </div>
          <div>
            <dt>{{ $t("admin.compendium.lastImport") }}</dt>
            <dd>
              {{
                currentWorld.lastImport
                  ? formatDate(currentWorld.lastImport.finishedAt)
                  : "—"
              }}
            </dd>
          </div>
        </dl>
        <div>
          <button type="button" @click="editWorld">
            {{ $t("admin.compendium.editWorld") }}
          </button>
          <button type="button" @click="setMode('editor')">
            {{ $t("admin.compendium.openEditor") }}
          </button>
          <button type="button" @click="setMode('review')">
            {{ $t("admin.compendium.startReview") }}
          </button>
        </div>
      </section>
    </div>

    <CompendiumWorkspace
      v-else-if="mode === 'editor' && selectedUniverseId"
      :key="`admin-world-${selectedUniverseId}-${editorEntryId || 0}`"
      class="admin-compendium__workspace"
      :universe-id="selectedUniverseId"
      :initial-entry-id="editorEntryId"
    />

    <div v-else-if="mode === 'review'" class="admin-compendium__review">
      <aside>
        <div class="admin-compendium__review-filters">
          <input
            v-model.trim="reviewQuery"
            type="search"
            :placeholder="$t('admin.compendium.search')"
            @input="queueReview"
          />
          <select v-model="reviewVerification" @change="loadReview">
            <option value="">
              {{ $t("admin.compendium.allVerification") }}
            </option>
            <option value="unverified">
              {{ $t("admin.compendium.unverified") }}
            </option>
            <option value="verified">
              {{ $t("admin.compendium.verified") }}
            </option>
            <option value="rejected">
              {{ $t("admin.compendium.rejected") }}
            </option>
          </select>
          <select v-model="reviewEditorial" @change="loadReview">
            <option value="">{{ $t("admin.compendium.allEditorial") }}</option>
            <option value="source_preserved_not_proofread">
              {{ $t("admin.compendium.notProofread") }}
            </option>
            <option value="needs_review">
              {{ $t("admin.compendium.needsReview") }}
            </option>
            <option value="reviewed">
              {{ $t("admin.compendium.reviewed") }}
            </option>
            <option value="approved">
              {{ $t("admin.compendium.approved") }}
            </option>
          </select>
        </div>
        <ol>
          <li v-for="entry in reviewEntries" :key="entry.id">
            <button
              type="button"
              :class="{ active: entry.id === reviewEntry?.id }"
              @click="selectReviewEntry(entry)"
            >
              <strong>{{ entry.title }}</strong>
              <span
                >{{ entry.sourceType }} · {{ entry.verificationStatus }}</span
              >
            </button>
          </li>
        </ol>
        <button v-if="reviewHasMore" type="button" @click="loadMoreReview">
          {{ $t("admin.compendium.more") }}
        </button>
      </aside>
      <main v-if="reviewEntry">
        <header>
          <div>
            <small>{{ reviewEntry.sourceId }}</small>
            <h3>{{ reviewEntry.title }}</h3>
          </div>
          <button type="button" @click="openEntryEditor">
            {{ $t("admin.compendium.editArticle") }}
          </button>
        </header>
        <form @submit.prevent="savePolicy">
          <div class="admin-compendium__policy-grid">
            <label
              >{{ $t("admin.compendium.type") }}
              <input v-model.trim="policy.typeCode" required maxlength="64" />
            </label>
            <label
              >{{ $t("admin.compendium.visibility") }}
              <select v-model="policy.visibility">
                <option v-for="value in policyOptions.visibility" :key="value">
                  {{ value }}
                </option>
              </select>
            </label>
            <label
              >{{ $t("admin.compendium.verification") }}
              <select v-model="policy.verificationStatus">
                <option
                  v-for="value in policyOptions.verification"
                  :key="value"
                >
                  {{ value }}
                </option>
              </select>
            </label>
            <label
              >{{ $t("admin.compendium.editorialStatus") }}
              <select v-model="policy.editorialStatus">
                <option v-for="value in policyOptions.editorial" :key="value">
                  {{ value }}
                </option>
              </select>
            </label>
            <label
              >{{ $t("admin.compendium.spoiler") }}
              <select v-model="policy.spoilerLevel">
                <option v-for="value in policyOptions.spoiler" :key="value">
                  {{ value }}
                </option>
              </select>
            </label>
            <label
              >{{ $t("admin.compendium.canon") }}
              <select v-model="policy.canonStatus">
                <option v-for="value in policyOptions.canon" :key="value">
                  {{ value }}
                </option>
              </select>
            </label>
            <label
              >{{ $t("admin.compendium.edition") }}
              <input v-model.trim="policy.edition" maxlength="32" />
            </label>
          </div>
          <label class="admin-compendium__long-field">
            {{ $t("admin.compendium.playerDescription") }}
            <textarea v-model="policy.playerDescription" rows="4" />
          </label>
          <label class="admin-compendium__long-field">
            {{ $t("admin.compendium.gmNotes") }}
            <textarea v-model="policy.gmNotes" rows="4" />
          </label>
          <p class="admin-compendium__hint">
            {{ $t("admin.compendium.richTextHint") }}
          </p>
          <button type="submit" :disabled="savingPolicy">
            {{ $t("admin.compendium.savePolicy") }}
          </button>
        </form>

        <section v-if="reviewEntry.mechanicalProfiles?.length">
          <h4>{{ $t("admin.compendium.mechanicalProfiles") }}</h4>
          <article
            v-for="profile in reviewEntry.mechanicalProfiles"
            :key="profile.id"
            class="admin-compendium__profile"
          >
            <strong>{{ profile.kind }} · {{ profile.systemCode }}</strong>
            <select v-model="profile.status">
              <option v-for="status in policyOptions.profile" :key="status">
                {{ status }}
              </option>
            </select>
            <label
              ><input v-model="profile.usable" type="checkbox" />
              {{ $t("admin.compendium.usable") }}</label
            >
            <button type="button" @click="saveProfile(profile)">
              {{ $t("admin.compendium.save") }}
            </button>
          </article>
        </section>
      </main>
      <p v-else>{{ $t("admin.compendium.chooseEntry") }}</p>
    </div>

    <div v-else-if="mode === 'operations'" class="admin-compendium__operations">
      <section>
        <header>
          <div>
            <h3>{{ $t("admin.compendium.assetLibrary") }}</h3>
            <p>{{ $t("admin.compendium.assetLibraryHint") }}</p>
          </div>
        </header>
        <div class="admin-compendium__table-wrap">
          <table>
            <thead>
              <tr>
                <th>{{ $t("admin.compendium.file") }}</th>
                <th>{{ $t("admin.compendium.format") }}</th>
                <th>{{ $t("admin.compendium.size") }}</th>
                <th>{{ $t("admin.compendium.references") }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="asset in authoredAssets" :key="asset.id">
                <td>{{ asset.name }}</td>
                <td>{{ asset.mimeType }}</td>
                <td>{{ formatBytes(asset.byteSize) }}</td>
                <td>{{ asset.referenceCount }}</td>
                <td>
                  <button
                    v-if="asset.referenceCount === 0"
                    type="button"
                    @click="deleteAsset(asset)"
                  >
                    {{ $t("admin.compendium.delete") }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <section>
        <header>
          <div>
            <h3>{{ $t("admin.compendium.sources") }}</h3>
            <p>{{ $t("admin.compendium.sourcesHint") }}</p>
          </div>
        </header>
        <div class="admin-compendium__table-wrap">
          <table>
            <thead>
              <tr>
                <th>{{ $t("admin.compendium.source") }}</th>
                <th>{{ $t("admin.compendium.documents") }}</th>
                <th>{{ $t("admin.compendium.license") }}</th>
                <th>{{ $t("admin.compendium.attribution") }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="source in data.sources" :key="source.id">
                <td>
                  <strong>{{ source.name }}</strong
                  ><small>{{ source.key }}</small>
                </td>
                <td>{{ source.documents }} / {{ source.revisions }}</td>
                <td>
                  <input v-model.trim="source.licenseStatus" maxlength="64" />
                </td>
                <td>
                  <input
                    v-model.trim="source.attributionStatus"
                    maxlength="64"
                  />
                </td>
                <td>
                  <button type="button" @click="saveSource(source)">
                    {{ $t("admin.compendium.save") }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <section>
        <header>
          <div>
            <h3>{{ $t("admin.compendium.imports") }}</h3>
            <p>{{ $t("admin.compendium.importsHint") }}</p>
          </div>
          <button
            v-if="currentWorld?.systemCode === 'wfrp2ed'"
            type="button"
            :disabled="operationBusy"
            @click="syncCatalog"
          >
            {{ $t("admin.compendium.syncCatalog") }}
          </button>
        </header>
        <div class="admin-compendium__table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>{{ $t("admin.compendium.package") }}</th>
                <th>{{ $t("admin.compendium.status") }}</th>
                <th>{{ $t("admin.compendium.result") }}</th>
                <th>{{ $t("admin.compendium.finished") }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in worldImports" :key="item.id">
                <td>#{{ item.id }}</td>
                <td>
                  <strong>{{ item.packName }}</strong
                  ><small>{{ item.mode }}</small>
                </td>
                <td>{{ item.status }}</td>
                <td>
                  +{{ item.added }} · ~{{ item.updated }} · ={{
                    item.skipped
                  }}
                  · !{{ item.errors }}
                </td>
                <td>{{ formatDate(item.finishedAt) }}</td>
                <td>
                  <button
                    v-if="canRollback(item)"
                    type="button"
                    :disabled="operationBusy"
                    @click="rollbackImport(item)"
                  >
                    {{ $t("admin.compendium.rollback") }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </section>
</template>

<script>
import CompendiumWorkspace from "@/components/vtt/compendium/CompendiumWorkspace.vue";
import { adminApiClient } from "@/lib/admin/adminApiClient";
import { compendiumApiClient } from "@/lib/compendium/compendiumApiClient";

const emptyData = () => ({
  metrics: {},
  worlds: [],
  systems: [],
  imports: [],
  sources: [],
});
const emptyWorldDraft = () => ({
  name: "",
  code: "",
  systemId: null,
  description: "",
  isActive: true,
});

export default {
  name: "AdminCompendiumTab",
  components: { CompendiumWorkspace },
  emits: ["loaded"],
  data: () => ({
    data: emptyData(),
    mode: "overview",
    selectedUniverseId: null,
    editorEntryId: null,
    loading: false,
    error: "",
    reviewEntries: [],
    reviewEntry: null,
    reviewQuery: "",
    reviewVerification: "",
    reviewEditorial: "",
    reviewPage: 1,
    reviewHasMore: false,
    reviewTimer: null,
    policy: {},
    savingPolicy: false,
    operationBusy: false,
    authoredAssets: [],
    showCreateWorld: false,
    editingWorld: false,
    creatingWorld: false,
    worldDraft: emptyWorldDraft(),
    views: ["overview", "editor", "review", "operations"],
    policyOptions: {
      visibility: ["public", "player", "gm", "secret"],
      verification: ["unverified", "verified", "rejected"],
      editorial: [
        "source_preserved_not_proofread",
        "draft",
        "needs_review",
        "reviewed",
        "approved",
        "rejected",
      ],
      spoiler: ["unreviewed", "none", "minor", "major", "secret"],
      canon: ["unreviewed", "canon", "non_canon", "disputed"],
      profile: [
        "repository_unverified",
        "custom_unverified",
        "unverified",
        "verified",
        "rejected",
      ],
    },
  }),
  computed: {
    currentWorld() {
      return this.data.worlds.find(
        (world) => world.universeId === Number(this.selectedUniverseId),
      );
    },
    metricCards() {
      return [
        "worlds",
        "entries",
        "published",
        "verified",
        "needsReview",
        "assetsAvailable",
        "unresolvedLinks",
      ].map((key) => ({ key, value: Number(this.data.metrics[key] || 0) }));
    },
    worldImports() {
      return this.data.imports.filter(
        (item) => item.universeId === Number(this.selectedUniverseId),
      );
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    clearTimeout(this.reviewTimer);
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = "";
      try {
        this.data = await adminApiClient.compendiumOverview();
        if (
          !this.selectedUniverseId ||
          !this.data.worlds.some(
            (world) => world.universeId === Number(this.selectedUniverseId),
          )
        ) {
          this.selectedUniverseId = this.data.worlds[0]?.universeId || null;
        }
        this.$emit("loaded", this.data.metrics);
        if (this.mode === "review") await this.loadReview();
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      } finally {
        this.loading = false;
      }
    },
    setMode(mode) {
      this.mode = mode;
      if (mode !== "overview" && this.showCreateWorld) this.closeWorldForm();
      if (mode === "review") this.loadReview();
      if (mode === "operations") this.loadAssets();
    },
    openCreateWorld() {
      if (
        this.mode === "overview" &&
        this.showCreateWorld &&
        !this.editingWorld
      ) {
        this.closeWorldForm();
        return;
      }
      this.mode = "overview";
      this.editingWorld = false;
      this.showCreateWorld = true;
      this.worldDraft = emptyWorldDraft();
      this.worldDraft.systemId =
        this.currentWorld?.systemId || this.data.systems[0]?.id || null;
    },
    editWorld() {
      if (!this.currentWorld) return;
      this.mode = "overview";
      this.editingWorld = true;
      this.showCreateWorld = true;
      this.worldDraft = {
        name: this.currentWorld.name,
        code: this.currentWorld.code,
        systemId: this.currentWorld.systemId,
        description: this.currentWorld.description,
        isActive: this.currentWorld.gameIsActive,
      };
    },
    closeWorldForm() {
      this.showCreateWorld = false;
      this.editingWorld = false;
      this.worldDraft = emptyWorldDraft();
    },
    async saveWorld() {
      if (this.creatingWorld) return;
      this.creatingWorld = true;
      this.error = "";
      try {
        const payload = {
          ...this.worldDraft,
          code: this.worldDraft.code || null,
        };
        const saved = this.editingWorld
          ? await adminApiClient.updateCompendiumWorld(
              this.selectedUniverseId,
              payload,
            )
          : await adminApiClient.createCompendiumWorld(payload);
        this.selectedUniverseId = saved.universeId;
        this.closeWorldForm();
        await this.load();
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      } finally {
        this.creatingWorld = false;
      }
    },
    worldChanged() {
      if (this.editingWorld) this.closeWorldForm();
      this.editorEntryId = null;
      this.reviewEntry = null;
      if (this.mode === "review") this.loadReview();
      if (this.mode === "operations") this.loadAssets();
    },
    selectWorld(world) {
      this.selectedUniverseId = world.universeId;
      this.worldChanged();
    },
    queueReview() {
      clearTimeout(this.reviewTimer);
      this.reviewTimer = setTimeout(() => this.loadReview(), 300);
    },
    async loadReview(append = false) {
      if (!this.selectedUniverseId) return;
      if (!append) this.reviewPage = 1;
      this.loading = true;
      this.error = "";
      try {
        const response = await compendiumApiClient.worldEntries(
          this.selectedUniverseId,
          {
            q: this.reviewQuery,
            verification: this.reviewVerification,
            editorial: this.reviewEditorial,
            sourceBacked: 1,
            status: "all",
            page: this.reviewPage,
            limit: 50,
          },
        );
        this.reviewEntries = append
          ? [...this.reviewEntries, ...response.items]
          : response.items;
        this.reviewHasMore = response.hasMore;
        if (!append && this.reviewEntries.length)
          await this.selectReviewEntry(this.reviewEntries[0]);
        else if (!this.reviewEntries.length) this.reviewEntry = null;
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      } finally {
        this.loading = false;
      }
    },
    async loadMoreReview() {
      this.reviewPage += 1;
      await this.loadReview(true);
    },
    async selectReviewEntry(entry) {
      try {
        const response = await compendiumApiClient.worldEntry(
          this.selectedUniverseId,
          entry.id,
        );
        this.reviewEntry = response.entry;
        this.resetPolicy();
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      }
    },
    resetPolicy() {
      const entry = this.reviewEntry || {};
      this.policy = {
        typeCode: entry.sourceType || "lore",
        visibility: entry.visibility || "gm",
        verificationStatus: entry.verificationStatus || "unverified",
        editorialStatus:
          entry.editorialStatus || "source_preserved_not_proofread",
        spoilerLevel: entry.spoilerLevel || "unreviewed",
        canonStatus: entry.canonStatus || "unreviewed",
        edition: entry.edition || "",
        playerDescription: entry.playerDescription || "",
        gmNotes: entry.gmNotes || "",
      };
    },
    async savePolicy() {
      const exposesToPlayers =
        this.policy.verificationStatus === "verified" &&
        ["public", "player"].includes(this.policy.visibility) &&
        (this.reviewEntry.verificationStatus !== "verified" ||
          !["public", "player"].includes(this.reviewEntry.visibility));
      if (
        exposesToPlayers &&
        !window.confirm(this.$t("admin.compendium.exposeConfirm"))
      )
        return;
      this.savingPolicy = true;
      this.error = "";
      try {
        const entryId = this.reviewEntry.id;
        await adminApiClient.updateCompendiumEntryPolicy(
          this.selectedUniverseId,
          entryId,
          this.policy,
        );
        const response = await compendiumApiClient.worldEntry(
          this.selectedUniverseId,
          entryId,
        );
        this.reviewEntry = response.entry;
        this.resetPolicy();
        await this.loadReview();
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      } finally {
        this.savingPolicy = false;
      }
    },
    async saveProfile(profile) {
      this.error = "";
      try {
        const response = await adminApiClient.updateCompendiumProfile(
          this.selectedUniverseId,
          profile.id,
          { status: profile.status, usable: profile.usable },
        );
        Object.assign(profile, response.profile);
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      }
    },
    openEntryEditor() {
      this.editorEntryId = this.reviewEntry.id;
      this.mode = "editor";
    },
    async saveSource(source) {
      this.error = "";
      try {
        const response = await adminApiClient.updateCompendiumSource(
          source.id,
          {
            licenseStatus: source.licenseStatus,
            attributionStatus: source.attributionStatus,
          },
        );
        Object.assign(source, response.source);
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      }
    },
    async loadAssets() {
      if (!this.selectedUniverseId) return;
      try {
        const response = await compendiumApiClient.listAssets(
          this.selectedUniverseId,
        );
        this.authoredAssets = response.items || [];
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      }
    },
    async deleteAsset(asset) {
      if (!window.confirm(this.$t("admin.compendium.deleteAssetConfirm")))
        return;
      try {
        await compendiumApiClient.deleteAsset(
          this.selectedUniverseId,
          asset.id,
        );
        await this.loadAssets();
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      }
    },
    canRollback(item) {
      return (
        item.mode === "publish" &&
        !item.rolledBackAt &&
        ["completed", "completed_with_errors", "failed"].includes(item.status)
      );
    },
    async rollbackImport(item) {
      if (!window.confirm(this.$t("admin.compendium.rollbackConfirm"))) return;
      this.operationBusy = true;
      try {
        await adminApiClient.rollbackCompendiumImport(
          this.selectedUniverseId,
          item.id,
        );
        await this.load();
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      } finally {
        this.operationBusy = false;
      }
    },
    async syncCatalog() {
      if (!window.confirm(this.$t("admin.compendium.syncConfirm"))) return;
      this.operationBusy = true;
      try {
        await adminApiClient.syncCompendiumWfrp2(this.selectedUniverseId);
        await this.load();
      } catch (error) {
        this.error = error?.payload?.message || error?.message || "Error";
      } finally {
        this.operationBusy = false;
      }
    },
    formatBytes(value) {
      const bytes = Number(value || 0);
      if (bytes < 1024) return `${bytes} B`;
      if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KiB`;
      return `${(bytes / 1048576).toFixed(1)} MiB`;
    },
    formatDate(value) {
      if (!value) return "—";
      return new Intl.DateTimeFormat(this.$i18n.locale, {
        dateStyle: "short",
        timeStyle: "short",
      }).format(new Date(value));
    },
  },
};
</script>
