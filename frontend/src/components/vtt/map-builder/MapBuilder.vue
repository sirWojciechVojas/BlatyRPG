<template>
  <section
    class="map-builder"
    :class="{ 'map-builder--preview': playerPreview }"
  >
    <header class="map-builder__topbar">
      <label class="map-builder__name">
        <span>Nazwa</span>
        <input v-model.trim="name" maxlength="150" @input="markDirty" />
      </label>
      <span
        class="map-builder__save-state"
        :class="`map-builder__save-state--${saveState}`"
      >
        {{ saveStateLabel }}
      </span>
      <select
        v-model="currentMapId"
        aria-label="Projekt mapy"
        @change="openSelectedMap"
      >
        <option value="">Nowy projekt</option>
        <option
          v-for="item in projects"
          :key="item.id"
          :value="String(item.id)"
        >
          {{ item.name }}{{ item.isTemplate ? " · szablon" : "" }}
        </option>
      </select>
      <button type="button" title="Nowa mapa" @click="newProject">＋</button>
      <button
        type="button"
        :disabled="!canUndo"
        title="Cofnij (Ctrl+Z)"
        @click="undo"
      >
        ↶
      </button>
      <button
        type="button"
        :disabled="!canRedo"
        title="Ponów (Ctrl+Shift+Z)"
        @click="redo"
      >
        ↷
      </button>
      <button
        type="button"
        :class="{ active: playerPreview }"
        @click="playerPreview = !playerPreview"
      >
        Podgląd gracza
      </button>
      <button type="button" :disabled="busy || readOnly" @click="save()">
        Zapisz
      </button>
      <button type="button" :disabled="busy || readOnly" @click="save(true)">
        Zapisz jako szablon
      </button>
      <button
        type="button"
        class="map-builder__publish"
        :disabled="busy || !mapId || readOnly"
        @click="publish"
      >
        Publikuj
      </button>
    </header>

    <div v-if="error" class="map-builder__notice" role="alert">
      <span>{{ error }}</span>
      <button type="button" @click="error = ''">×</button>
    </div>
    <div v-if="recoveryAvailable" class="map-builder__recovery" role="status">
      Znaleziono nowszy lokalny zapis awaryjny.
      <button type="button" @click="restoreRecovery">Przywróć</button>
      <button type="button" @click="discardRecovery">Odrzuć</button>
    </div>
    <div v-if="readOnly" class="map-builder__notice" role="status">
      Projekt edytuje {{ lock?.ownerName || "inny użytkownik" }}. Tryb tylko do
      odczytu do {{ lock?.expiresAt || "wygaśnięcia blokady" }}.
    </div>

    <div class="map-builder__workspace">
      <aside class="map-builder__tools" aria-label="Narzędzia kreatora map">
        <button
          v-for="tool in tools"
          :key="tool.id"
          type="button"
          :class="{ active: activeTool === tool.id }"
          :title="tool.hint"
          @click="selectTool(tool.id)"
        >
          <span aria-hidden="true">{{ tool.icon }}</span>
          <small>{{ tool.label }}</small>
        </button>
      </aside>

      <aside class="map-builder__library" :class="{ collapsed: !libraryOpen }">
        <button
          class="map-builder__panel-toggle"
          type="button"
          @click="libraryOpen = !libraryOpen"
        >
          {{ libraryOpen ? "‹" : "Biblioteka ›" }}
        </button>
        <template v-if="libraryOpen">
          <header>
            <strong>Biblioteka · {{ availableAssets.length }}</strong>
            <small>{{ starterAssetCount }} elementy startowe</small>
          </header>
          <input
            v-model="assetSearch"
            type="search"
            placeholder="Szukaj po polskiej nazwie lub tagu…"
          />
          <div class="map-builder__filters">
            <select v-model="assetCategory" aria-label="Kategoria assetów">
              <option value="">Wszystkie kategorie</option>
              <option v-for="category in assetCategories" :key="category">
                {{ category }}
              </option>
            </select>
            <button
              type="button"
              :class="{ active: favoritesOnly }"
              @click="favoritesOnly = !favoritesOnly"
            >
              ★
            </button>
            <button
              type="button"
              :class="{ active: recentOnly }"
              @click="recentOnly = !recentOnly"
            >
              Ostatnie
            </button>
          </div>
          <div class="map-builder__asset-grid" @scroll.passive="onAssetScroll">
            <button
              v-for="asset in visibleAssets"
              :key="asset.id"
              type="button"
              :class="{ active: selectedAssetId === asset.id }"
              :title="`${asset.name} · ${asset.tags.join(', ')}`"
              @click="chooseAsset(asset)"
              @dblclick="toggleFavorite(asset.id)"
            >
              <span
                class="map-builder__asset-thumb"
                :style="assetThumbStyle(asset)"
              >
                <AuthenticatedImage
                  v-if="asset.kind === 'sprite' && !asset.source?.frame"
                  :src="asset.thumbnails?.small || asset.source?.url"
                  :alt="asset.name"
                />
                <span v-if="asset.kind !== 'sprite'">{{
                  asset.kind === "material" ? "▧" : "◫"
                }}</span>
              </span>
              <small>{{ asset.name }}</small>
              <i v-if="favoriteIds.includes(asset.id)">★</i>
            </button>
          </div>
          <label class="map-builder__collection">
            Kolekcja importu
            <input
              v-model.trim="assetCollection"
              maxlength="80"
              placeholder="np. Moje ruiny"
            />
          </label>
          <footer class="map-builder__library-footer">
            <label class="map-builder__file-button">
              Importuj grafikę
              <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                @change="importAsset"
              />
            </label>
            <label class="map-builder__file-button">
              Importuj pakiet ZIP
              <input
                type="file"
                accept=".zip,application/zip"
                @change="importPackage"
              />
            </label>
          </footer>
        </template>
      </aside>

      <main class="map-builder__canvas-shell">
        <div class="map-builder__canvas-toolbar">
          <label
            >Rozmiar
            <input v-model.number="brush.size" type="range" min="10" max="600"
          /></label>
          <label
            >Miękkość
            <input
              v-model.number="brush.hardness"
              type="range"
              min="0"
              max="1"
              step="0.05"
          /></label>
          <label
            >Krycie
            <input
              v-model.number="brush.opacity"
              type="range"
              min="0.05"
              max="1"
              step="0.05"
          /></label>
          <label
            >Przepływ
            <input
              v-model.number="brush.flow"
              type="range"
              min="0.05"
              max="1"
              step="0.05"
          /></label>
          <button
            type="button"
            :class="{ active: activeTool === 'erase' }"
            @click="selectTool('erase')"
          >
            Gumka
          </button>
          <button
            v-if="polygonDraft.length"
            type="button"
            @click="finishPolygon"
          >
            Zamknij wielokąt ({{ polygonDraft.length }})
          </button>
          <label
            ><input
              v-model="document.grid.snap"
              type="checkbox"
              @change="markDirty"
            />
            Przyciąganie</label
          >
          <label
            >Maska
            <select v-model="brush.maskMode">
              <option value="none">Brak</option>
              <option value="inside-rooms">W pomieszczeniach</option>
              <option value="outside-rooms">Poza pomieszczeniami</option>
            </select></label
          >
          <select v-model="document.grid.type" @change="markDirty">
            <option value="square">Siatka kwadratowa</option>
            <option value="hex">Siatka heksagonalna</option>
            <option value="none">Bez siatki</option>
          </select>
          <button type="button" @click="$refs.canvas?.fit()">Dopasuj</button>
        </div>
        <MapCanvas
          ref="canvas"
          :document="document"
          :selected-ids="selectedIds"
          :active-tool="activeTool"
          :show-grid="showGrid"
          :player-preview="playerPreview"
          @select="selectObject"
          @gesture="handleGesture"
        />
      </main>

      <aside class="map-builder__right" :class="{ collapsed: !rightPanelOpen }">
        <button
          class="map-builder__panel-toggle"
          type="button"
          @click="rightPanelOpen = !rightPanelOpen"
        >
          {{ rightPanelOpen ? "›" : "‹ Właściwości" }}
        </button>
        <template v-if="rightPanelOpen">
          <section class="map-builder__properties">
            <header>
              <strong>Właściwości</strong>
              <span>{{
                selectedObjects.length
                  ? `${selectedObjects.length} zazn.`
                  : "brak zaznaczenia"
              }}</span>
            </header>
            <template v-if="primarySelection">
              <label
                >X
                <input
                  :value="primarySelection.x"
                  type="number"
                  @change="changeSelected('x', $event.target.value)"
              /></label>
              <label
                >Y
                <input
                  :value="primarySelection.y"
                  type="number"
                  @change="changeSelected('y', $event.target.value)"
              /></label>
              <label
                >Szer.
                <input
                  :value="primarySelection.width"
                  type="number"
                  min="1"
                  @change="changeSelected('width', $event.target.value)"
              /></label>
              <label
                >Wys.
                <input
                  :value="primarySelection.height"
                  type="number"
                  min="1"
                  @change="changeSelected('height', $event.target.value)"
              /></label>
              <label
                >Obrót
                <input
                  :value="primarySelection.rotation"
                  type="number"
                  @change="changeSelected('rotation', $event.target.value)"
              /></label>
              <label
                >Krycie
                <input
                  :value="primarySelection.opacity"
                  type="number"
                  min="0"
                  max="1"
                  step="0.05"
                  @change="changeSelected('opacity', $event.target.value)"
              /></label>
              <label
                ><input
                  :checked="primarySelection.locked"
                  type="checkbox"
                  @change="
                    changeSelected('locked', $event.target.checked, false)
                  "
                />
                Blokada</label
              >
              <div class="map-builder__property-actions">
                <button type="button" @click="flipSelected('x')">
                  Odbij X
                </button>
                <button type="button" @click="flipSelected('y')">
                  Odbij Y
                </button>
                <button type="button" @click="duplicateSelected">Kopiuj</button>
                <button type="button" @click="groupSelected">Grupuj</button>
                <button
                  v-if="selectedRooms.length >= 2"
                  type="button"
                  @click="combineRooms('union')"
                >
                  Połącz pokoje
                </button>
                <button
                  v-if="selectedRooms.length >= 2"
                  type="button"
                  @click="combineRooms('subtract')"
                >
                  Odejmij pokoje
                </button>
                <button type="button" @click="removeSelected">Usuń</button>
              </div>
            </template>
            <label v-else
              >Tekst/znacznik <input v-model="annotationText" maxlength="120"
            /></label>
          </section>

          <section class="map-builder__layers">
            <header><strong>Warstwy i poziomy</strong></header>
            <select v-model="document.activeLevelId" @change="markDirty">
              <option
                v-for="level in document.levels"
                :key="level.id"
                :value="level.id"
              >
                {{ level.name }}
              </option>
            </select>
            <button type="button" @click="addLevel">＋ poziom budynku</button>
            <div class="map-builder__level-list">
              <button
                v-for="level in document.levels"
                :key="level.id"
                type="button"
                :class="{ active: document.activeLevelId === level.id }"
                @click="document.activeLevelId = level.id"
              >
                <span>{{ level.visible === false ? "○" : "◉" }}</span>
                {{ level.name }} · {{ level.elevation }} m
                <i
                  role="button"
                  tabindex="0"
                  @click.stop="toggleLevel(level)"
                  @keydown.enter.stop="toggleLevel(level)"
                  >{{ level.visible === false ? "Pokaż" : "Ukryj" }}</i
                >
              </button>
            </div>
            <ol>
              <li v-for="layer in orderedLayers" :key="layer.id">
                <button
                  type="button"
                  :title="layer.visible ? 'Ukryj' : 'Pokaż'"
                  @click="toggleLayer(layer, 'visible')"
                >
                  {{ layer.visible ? "◉" : "○" }}
                </button>
                <button
                  type="button"
                  :title="layer.locked ? 'Odblokuj' : 'Zablokuj'"
                  @click="toggleLayer(layer, 'locked')"
                >
                  {{ layer.locked ? "🔒" : "·" }}
                </button>
                <span>{{ layer.name }}</span>
                <input
                  v-model.number="layer.opacity"
                  type="range"
                  min="0"
                  max="1"
                  step="0.05"
                  @change="markDirty"
                />
                <button type="button" @click="moveLayer(layer, 1)">↑</button>
                <button type="button" @click="moveLayer(layer, -1)">↓</button>
              </li>
            </ol>
          </section>

          <section class="map-builder__ai">
            <header>
              <strong>AI</strong
              ><span :class="{ available: aiStatus.available }">{{
                aiStatusLabel
              }}</span>
            </header>
            <select v-model="aiMode" :disabled="!aiStatus.available">
              <option value="layout">Opis → układ lokacji</option>
              <option value="selection">Wyposaż / zmień zaznaczenie</option>
              <option value="asset">Wygeneruj brakujący asset</option>
            </select>
            <textarea
              v-model="aiPrompt"
              rows="4"
              maxlength="4000"
              placeholder="Np. karczma z podwórzem, kuchnią, izbą wspólną i pokojami…"
            />
            <div class="map-builder__ai-actions">
              <button
                type="button"
                :disabled="!aiStatus.available || aiBusy || !aiPrompt.trim()"
                @click="requestAi"
              >
                Generuj propozycję
              </button>
              <button v-if="aiBusy" type="button" @click="cancelAi">
                Anuluj
              </button>
            </div>
            <div v-if="aiProposal" class="map-builder__proposal">
              <strong>{{ aiProposal.summary || "Propozycja AI" }}</strong>
              <p>
                {{ aiProposal.objects?.length || 0 }} nowych elementów. Zmiany
                nie zostały jeszcze zastosowane.
              </p>
              <button type="button" @click="acceptAi">
                Akceptuj jako jedną operację
              </button>
              <button type="button" @click="aiProposal = null">Odrzuć</button>
            </div>
          </section>

          <section class="map-builder__export">
            <header><strong>Eksport i rewizje</strong></header>
            <label
              ><input v-model="exportGrid" type="checkbox" /> Siatka w
              eksporcie</label
            >
            <button type="button" @click="downloadExport('png')">PNG</button>
            <button type="button" @click="downloadExport('webp')">WebP</button>
            <button type="button" :disabled="!mapId" @click="loadRevisions">
              Rewizje
            </button>
            <select v-if="revisions.length" v-model="restoreRevisionId">
              <option value="">Wybierz rewizję…</option>
              <option
                v-for="revision in revisions"
                :key="revision.id"
                :value="revision.id"
              >
                #{{ revision.number }} · {{ revision.createdAt }}
              </option>
            </select>
            <button
              v-if="restoreRevisionId"
              type="button"
              @click="restoreRevision"
            >
              Przywróć
            </button>
          </section>
        </template>
      </aside>
    </div>
  </section>
</template>

<script>
import MapCanvas from "./MapCanvas.vue";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import {
  addAssetObject,
  applyAiProposal,
  cloneMapDocument,
  createCommandHistory,
  createMapDocument,
  mapObjectId,
  normalizeMapObject,
  snapPoint,
} from "./mapDocument";
import {
  STARTER_ASSETS,
  STARTER_ASSET_COUNT,
  searchStarterAssets,
  starterAssetById,
} from "./starterAssets";
import { mapBuilderApiClient } from "@/lib/vtt/mapBuilderApiClient";

const TOOLS = Object.freeze([
  {
    id: "select",
    icon: "↖",
    label: "Zaznacz",
    hint: "Zaznaczanie pojedyncze i wielokrotne (Shift)",
  },
  {
    id: "pan",
    icon: "✋",
    label: "Przesuń",
    hint: "Przesuwanie płótna; kółko zmienia zoom względem kursora",
  },
  {
    id: "asset",
    icon: "♜",
    label: "Asset",
    hint: "Umieść wybrany asset lub kompozycję",
  },
  {
    id: "terrain",
    icon: "▧",
    label: "Teren",
    hint: "Pędzel materiału z miękkością, kryciem i przepływem",
  },
  { id: "road", icon: "⌁", label: "Droga", hint: "Edytowalna krzywa drogi" },
  { id: "river", icon: "≈", label: "Rzeka", hint: "Edytowalna krzywa rzeki" },
  {
    id: "fence",
    icon: "╫",
    label: "Płot",
    hint: "Edytowalna linia ogrodzenia",
  },
  {
    id: "roomRect",
    icon: "□",
    label: "Pokój",
    hint: "Pomieszczenie prostokątne",
  },
  {
    id: "roomCircle",
    icon: "○",
    label: "Okrąg",
    hint: "Pomieszczenie okrągłe",
  },
  {
    id: "roomPolygon",
    icon: "⬠",
    label: "Wielokąt",
    hint: "Pomieszczenie wielokątne; klikaj wierzchołki",
  },
  {
    id: "wall",
    icon: "▥",
    label: "Ściana",
    hint: "Ściana z materiałem i grubością",
  },
  {
    id: "door",
    icon: "⋂",
    label: "Drzwi",
    hint: "Drzwi publikowane jako działający portal VTT",
  },
  { id: "window", icon: "▦", label: "Okno", hint: "Okno osadzane w ścianie" },
  {
    id: "scatter",
    icon: "⁙",
    label: "Rozsyp",
    hint: "Losuj położenie, obrót i skalę z kontrolą odstępów",
  },
  { id: "text", icon: "T", label: "Tekst", hint: "Tekst mapy" },
  {
    id: "marker",
    icon: "◆",
    label: "Znacznik",
    hint: "Znacznik MG lub publiczny",
  },
  {
    id: "light",
    icon: "☀",
    label: "Światło",
    hint: "Światło publikowane do istniejącego silnika scen",
  },
]);

const ASSET_CATEGORIES = Object.freeze([
  "natura",
  "zabudowa",
  "wnętrza",
  "lochy",
  "jaskinie",
  "ruiny",
  "wyposażenie",
  "dekoracje",
  "materiały",
  "gotowe kompozycje",
]);

export default {
  name: "MapBuilder",
  components: { AuthenticatedImage, MapCanvas },
  props: {
    campaignId: { type: [Number, String], required: true },
    sceneId: { type: [Number, String], default: null },
    initialMapId: { type: [Number, String], default: null },
  },
  emits: ["meta-change", "published"],
  data() {
    const document = createMapDocument();
    return {
      tools: TOOLS,
      starterAssetCount: STARTER_ASSET_COUNT,
      editorId: mapObjectId("editor"),
      document,
      history: createCommandHistory(document),
      name: "Nowa mapa",
      mapId: null,
      currentMapId: "",
      currentRevision: 0,
      isTemplate: false,
      projects: [],
      revisions: [],
      restoreRevisionId: "",
      activeTool: "select",
      selectedIds: [],
      selectedAssetId: "starter.material.grass",
      customAssets: [],
      assetCollection: "własne",
      assetSearch: "",
      assetCategory: "",
      assetVisibleLimit: 100,
      favoriteIds: [],
      recentIds: [],
      favoritesOnly: false,
      recentOnly: false,
      libraryOpen: true,
      rightPanelOpen: true,
      showGrid: true,
      playerPreview: false,
      annotationText: "Znacznik",
      polygonDraft: [],
      brush: {
        size: 180,
        hardness: 0.65,
        opacity: 0.8,
        flow: 0.75,
        spacing: 0.35,
        maskMode: "none",
      },
      saveState: "saved",
      busy: false,
      error: "",
      lock: null,
      readOnly: false,
      recoveryAvailable: false,
      recovery: null,
      autosaveTimer: null,
      lockTimer: null,
      aiStatus: { available: false, provider: null, reason: "loading" },
      aiMode: "layout",
      aiPrompt: "",
      aiBusy: false,
      aiJob: null,
      aiPollTimer: null,
      aiProposal: null,
      exportGrid: true,
    };
  },
  computed: {
    availableAssets() {
      return [...STARTER_ASSETS, ...this.customAssets];
    },
    filteredAssets() {
      const starterIds = new Set(
        searchStarterAssets(this.assetSearch, this.assetCategory).map(
          (item) => item.id,
        ),
      );
      const needle = this.assetSearch.trim().toLocaleLowerCase("pl");
      return this.availableAssets.filter((asset) => {
        const matchesStarter = asset.id.startsWith("starter.")
          ? starterIds.has(asset.id)
          : true;
        const matchesCustom =
          asset.id.startsWith("starter.") ||
          !needle ||
          [asset.name, asset.category, ...(asset.tags || [])]
            .join(" ")
            .toLocaleLowerCase("pl")
            .includes(needle);
        return (
          matchesStarter &&
          matchesCustom &&
          (!this.assetCategory || asset.category === this.assetCategory) &&
          (!this.favoritesOnly || this.favoriteIds.includes(asset.id)) &&
          (!this.recentOnly || this.recentIds.includes(asset.id))
        );
      });
    },
    visibleAssets() {
      return this.filteredAssets.slice(0, this.assetVisibleLimit);
    },
    assetCategories() {
      return [
        ...new Set([
          ...ASSET_CATEGORIES,
          ...this.availableAssets.map((asset) => asset.category),
        ]),
      ].sort((a, b) => a.localeCompare(b, "pl"));
    },
    selectedObjects() {
      const ids = new Set(this.selectedIds);
      return this.document.objects.filter((object) => ids.has(object.id));
    },
    primarySelection() {
      return this.selectedObjects[0] || null;
    },
    selectedRooms() {
      return this.selectedObjects.filter(
        (object) =>
          object.type === "room" &&
          !object.locked &&
          !this.isLayerLocked(object.layerId),
      );
    },
    orderedLayers() {
      return [...this.document.layers].sort(
        (a, b) => Number(b.order) - Number(a.order),
      );
    },
    canUndo() {
      return this.history?.canUndo === true;
    },
    canRedo() {
      return this.history?.canRedo === true;
    },
    saveStateLabel() {
      return {
        saved: "Zapisano",
        dirty: "Niezapisane",
        saving: "Zapisywanie…",
        error: "Błąd zapisu",
        conflict: "Konflikt wersji",
      }[this.saveState];
    },
    aiStatusLabel() {
      if (this.aiStatus.available)
        return `${this.aiStatus.provider} · dostępne`;
      return this.aiStatus.reason === "not_configured"
        ? "Brak konfiguracji klucza"
        : "Niedostępne";
    },
  },
  async mounted() {
    this.readPreferences();
    window.addEventListener("keydown", this.onKeyDown);
    await Promise.allSettled([
      this.loadProjects(),
      this.loadAssets(),
      this.loadAiStatus(),
    ]);
    const requested = Number(this.initialMapId);
    const sceneProject = this.projects.find(
      (item) => Number(item.sceneId) === Number(this.sceneId),
    );
    if (requested > 0) await this.openMap(requested);
    else if (sceneProject) await this.openMap(sceneProject.id);
    else this.checkRecovery();
    this.autosaveTimer = window.setInterval(() => this.autosave(), 15000);
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.onKeyDown);
    window.clearInterval(this.autosaveTimer);
    window.clearInterval(this.lockTimer);
    window.clearTimeout(this.aiPollTimer);
    this.writeRecovery();
    if (this.mapId && !this.readOnly)
      mapBuilderApiClient
        .releaseLock(this.campaignId, this.mapId, this.editorId)
        .catch(() => {});
  },
  methods: {
    recoveryKey(mapId = this.mapId || "new") {
      return `blatyrpg.map-builder.recovery.${this.campaignId}.${mapId}`;
    },
    readPreferences() {
      try {
        this.favoriteIds = JSON.parse(
          localStorage.getItem("blatyrpg.map-builder.favorites") || "[]",
        );
        this.recentIds = JSON.parse(
          localStorage.getItem("blatyrpg.map-builder.recent") || "[]",
        );
      } catch (_error) {
        this.favoriteIds = [];
        this.recentIds = [];
      }
    },
    writePreferences() {
      try {
        localStorage.setItem(
          "blatyrpg.map-builder.favorites",
          JSON.stringify(this.favoriteIds.slice(0, 500)),
        );
        localStorage.setItem(
          "blatyrpg.map-builder.recent",
          JSON.stringify(this.recentIds.slice(0, 30)),
        );
      } catch (_error) {
        // Preferences remain available for this session.
      }
    },
    markDirty() {
      if (this.readOnly) return;
      this.saveState = "dirty";
      this.document.metadata.updatedAt = new Date().toISOString();
      this.$emit("meta-change", {
        mapId: this.mapId,
        name: this.name,
        dirty: true,
        status: "dirty",
      });
      this.writeRecovery();
    },
    commit(label, producer) {
      if (this.readOnly) return;
      this.document = this.history.execute(label, producer);
      this.markDirty();
    },
    undo() {
      this.document = this.history.undo();
      this.markDirty();
    },
    redo() {
      this.document = this.history.redo();
      this.markDirty();
    },
    async newProject() {
      await this.releaseCurrentLock();
      const document = createMapDocument();
      this.document = document;
      this.history = createCommandHistory(document);
      this.mapId = null;
      this.currentMapId = "";
      this.currentRevision = 0;
      this.isTemplate = false;
      this.name = "Nowa mapa";
      this.selectedIds = [];
      this.readOnly = false;
      this.lock = null;
      this.saveState = "dirty";
      this.checkRecovery();
      this.$nextTick(() => this.$refs.canvas?.fit());
      this.$emit("meta-change", {
        mapId: null,
        name: this.name,
        dirty: true,
        status: "dirty",
      });
    },
    async loadProjects() {
      const payload = await mapBuilderApiClient.list(this.campaignId);
      this.projects = payload.items || [];
    },
    async openSelectedMap() {
      if (!this.currentMapId) return this.newProject();
      await this.openMap(Number(this.currentMapId));
    },
    async openMap(id) {
      this.busy = true;
      this.error = "";
      try {
        if (this.mapId && Number(this.mapId) !== Number(id)) {
          await this.releaseCurrentLock();
        }
        const payload = await mapBuilderApiClient.get(this.campaignId, id);
        this.mapId = Number(payload.map.id);
        this.currentMapId = String(this.mapId);
        this.name = payload.map.name;
        this.currentRevision = Number(payload.revision.number);
        this.isTemplate = payload.map.isTemplate === true;
        this.document = payload.revision.document;
        this.history = createCommandHistory(this.document);
        this.selectedIds = [];
        this.saveState = "saved";
        const lockResult = await mapBuilderApiClient
          .acquireLock(this.campaignId, this.mapId, this.editorId)
          .catch((error) => {
            if (error.status === 423)
              return { lock: error.payload?.lock, acquired: false };
            throw error;
          });
        this.lock = lockResult.lock || null;
        this.readOnly = lockResult.acquired === false;
        if (!this.readOnly) {
          window.clearInterval(this.lockTimer);
          this.lockTimer = window.setInterval(() => this.renewLock(), 45000);
        }
        this.checkRecovery();
        this.$nextTick(() => this.$refs.canvas?.fit());
        this.$emit("meta-change", {
          mapId: this.mapId,
          name: this.name,
          dirty: false,
          status: "saved",
        });
      } catch (error) {
        this.error = this.apiError(
          error,
          "Nie udało się otworzyć projektu mapy.",
        );
      } finally {
        this.busy = false;
      }
    },
    async renewLock() {
      if (!this.mapId || this.readOnly) return;
      try {
        const payload = await mapBuilderApiClient.acquireLock(
          this.campaignId,
          this.mapId,
          this.editorId,
        );
        this.lock = payload.lock;
      } catch (_error) {
        this.readOnly = true;
        this.error =
          "Blokada edycji wygasła lub została przejęta. Lokalna kopia pozostała dostępna.";
      }
    },
    async releaseCurrentLock() {
      window.clearInterval(this.lockTimer);
      this.lockTimer = null;
      if (!this.mapId || this.readOnly) return;
      await mapBuilderApiClient
        .releaseLock(this.campaignId, this.mapId, this.editorId)
        .catch(() => {});
    },
    async save(asTemplate = null) {
      if (this.busy || this.readOnly) return null;
      const wasNew = !this.mapId;
      const templateState = asTemplate === null ? this.isTemplate : asTemplate;
      this.busy = true;
      this.saveState = "saving";
      this.$emit("meta-change", { status: "saving" });
      try {
        const payload = this.mapId
          ? await mapBuilderApiClient.save(this.campaignId, this.mapId, {
              name: this.name,
              sceneId: Number(this.sceneId) || null,
              baseRevision: this.currentRevision,
              document: this.document,
              isTemplate: templateState,
              editorId: this.editorId,
            })
          : await mapBuilderApiClient.create(this.campaignId, {
              name: this.name,
              sceneId: Number(this.sceneId) || null,
              document: this.document,
              isTemplate: templateState,
              editorId: this.editorId,
            });
        this.mapId = Number(payload.map.id);
        this.currentMapId = String(this.mapId);
        this.currentRevision = Number(payload.revision.number);
        this.isTemplate = payload.map.isTemplate === true;
        this.lock = payload.lock || this.lock;
        if (wasNew) {
          window.clearInterval(this.lockTimer);
          this.lockTimer = window.setInterval(() => this.renewLock(), 45000);
        }
        this.saveState = "saved";
        this.clearRecovery();
        await this.loadProjects();
        this.$emit("meta-change", {
          mapId: this.mapId,
          name: this.name,
          dirty: false,
          status: "saved",
        });
        return payload;
      } catch (error) {
        this.saveState = error.status === 409 ? "conflict" : "error";
        this.error = this.apiError(error, "Nie udało się zapisać mapy.");
        this.writeRecovery();
        return null;
      } finally {
        this.busy = false;
      }
    },
    autosave() {
      if (this.saveState !== "dirty" || this.busy || this.readOnly) return;
      this.writeRecovery();
      if (this.mapId) this.save();
    },
    writeRecovery() {
      if (
        this.saveState !== "dirty" &&
        this.saveState !== "error" &&
        this.saveState !== "conflict"
      )
        return;
      try {
        localStorage.setItem(
          this.recoveryKey(),
          JSON.stringify({
            savedAt: new Date().toISOString(),
            name: this.name,
            revision: this.currentRevision,
            document: this.document,
          }),
        );
      } catch (_error) {
        // Browser storage quota can disable emergency saves without blocking server saves.
      }
    },
    checkRecovery() {
      try {
        const recovery = JSON.parse(
          localStorage.getItem(this.recoveryKey()) || "null",
        );
        this.recovery = recovery?.document ? recovery : null;
        this.recoveryAvailable = Boolean(
          this.recovery &&
          this.recovery.savedAt > (this.document.metadata?.updatedAt || ""),
        );
      } catch (_error) {
        this.recovery = null;
        this.recoveryAvailable = false;
      }
    },
    restoreRecovery() {
      if (!this.recovery) return;
      this.document = cloneMapDocument(this.recovery.document);
      this.history = createCommandHistory(this.document);
      this.name = this.recovery.name || this.name;
      this.recoveryAvailable = false;
      this.markDirty();
    },
    discardRecovery() {
      this.clearRecovery();
      this.recoveryAvailable = false;
    },
    clearRecovery() {
      try {
        localStorage.removeItem(this.recoveryKey());
      } catch (_error) {
        // Nothing else to do.
      }
    },
    selectTool(id) {
      this.activeTool = id;
      if (id !== "roomPolygon") this.polygonDraft = [];
    },
    chooseAsset(asset) {
      this.selectedAssetId = asset.id;
      this.activeTool = asset.kind === "material" ? "terrain" : "asset";
      this.recentIds = [
        asset.id,
        ...this.recentIds.filter((id) => id !== asset.id),
      ].slice(0, 30);
      this.writePreferences();
    },
    toggleFavorite(id) {
      this.favoriteIds = this.favoriteIds.includes(id)
        ? this.favoriteIds.filter((item) => item !== id)
        : [id, ...this.favoriteIds];
      this.writePreferences();
    },
    onAssetScroll(event) {
      const target = event.currentTarget;
      if (
        target.scrollHeight - target.scrollTop - target.clientHeight < 300 &&
        this.assetVisibleLimit < this.filteredAssets.length
      ) {
        this.assetVisibleLimit = Math.min(
          this.filteredAssets.length,
          this.assetVisibleLimit + 100,
        );
      }
    },
    assetThumbStyle(asset) {
      if (asset.kind !== "sprite" || !asset.source?.frame) {
        return { backgroundColor: asset.material?.color || "#342d24" };
      }
      const frame = asset.source.frame;
      const max = 1254 - frame.width;
      return {
        backgroundImage: `url(${asset.source.url})`,
        backgroundSize: "400% 400%",
        backgroundPosition: `${(frame.x / max) * 100}% ${(frame.y / max) * 100}%`,
      };
    },
    selectObject({ id, additive }) {
      if (!id) {
        if (!additive) this.selectedIds = [];
      } else if (additive) {
        this.selectedIds = this.selectedIds.includes(id)
          ? this.selectedIds.filter((item) => item !== id)
          : [...this.selectedIds, id];
      } else this.selectedIds = [id];
    },
    isLayerLocked(layerId, document = this.document) {
      return (
        document.layers.find((layer) => layer.id === layerId)?.locked === true
      );
    },
    requireEditableLayer(layerId) {
      if (!this.isLayerLocked(layerId)) return true;
      this.error = "Warstwa docelowa jest zablokowana.";
      return false;
    },
    handleGesture(gesture) {
      if (gesture.phase !== "end" || this.readOnly) return;
      if (gesture.tool === "moveSelection") {
        const movedId = String(gesture.objectId || "");
        const moved = this.document.objects.find(
          (object) => object.id === movedId,
        );
        if (!moved || moved.locked || this.isLayerLocked(moved.layerId)) return;
        const selected = new Set(this.selectedIds);
        if (moved.groupId) {
          for (const object of this.document.objects) {
            if (object.groupId === moved.groupId) selected.add(object.id);
          }
        }
        const dx = Number(gesture.end.x) - Number(gesture.start.x);
        const dy = Number(gesture.end.y) - Number(gesture.start.y);
        this.commit("Przesuń obiekty", (draft) => {
          for (const object of draft.objects) {
            if (
              !selected.has(object.id) ||
              object.locked ||
              this.isLayerLocked(object.layerId, draft)
            )
              continue;
            const point = snapPoint(draft, {
              x: object.x + dx,
              y: object.y + dy,
            });
            object.x = point.x;
            object.y = point.y;
            if (Array.isArray(object.points) && object.points.length) {
              object.points = object.points.map((pathPoint) => ({
                x: Math.max(0, Math.min(draft.width, pathPoint.x + dx)),
                y: Math.max(0, Math.min(draft.height, pathPoint.y + dy)),
              }));
            }
            if (Array.isArray(object.booleanParts)) {
              object.booleanParts = object.booleanParts.map((part) => ({
                ...part,
                x: Number(part.x) + dx,
                y: Number(part.y) + dy,
                points: (part.points || []).map((pathPoint) => ({
                  x: pathPoint.x + dx,
                  y: pathPoint.y + dy,
                })),
              }));
            }
          }
        });
        return;
      }
      const start = snapPoint(this.document, gesture.start);
      const end = snapPoint(this.document, gesture.end);
      if (gesture.tool === "roomPolygon") {
        if (!this.requireEditableLayer("rooms")) return;
        this.polygonDraft.push(end);
        return;
      }
      if (gesture.tool === "asset") return this.placeAsset(end);
      if (gesture.tool === "scatter") return this.scatterAssets(end);
      if (gesture.tool === "erase") return this.eraseAt(end);
      const width = Math.max(
        this.document.grid.size / 2,
        Math.abs(end.x - start.x),
      );
      const height = Math.max(
        this.document.grid.size / 2,
        Math.abs(end.y - start.y),
      );
      const center = { x: (start.x + end.x) / 2, y: (start.y + end.y) / 2 };
      const pathColors = {
        road: "#806344",
        river: "#315b69",
        fence: "#6b5138",
        wall: "#8b8170",
      };
      const object = {
        id: mapObjectId(gesture.tool),
        type: gesture.tool,
        layerId: ["wall", "fence", "door", "window"].includes(gesture.tool)
          ? "walls"
          : gesture.tool === "light"
            ? "lights"
            : gesture.tool.startsWith("room")
              ? "rooms"
              : gesture.tool === "text" || gesture.tool === "marker"
                ? "annotations"
                : "terrain",
        levelId: this.document.activeLevelId,
        x: center.x,
        y: center.y,
        width,
        height,
        color:
          pathColors[gesture.tool] ||
          (gesture.tool === "light" ? "#ffb35c" : "#765d42"),
        opacity: this.brush.opacity,
        hardness: this.brush.hardness,
        flow: this.brush.flow,
        maskMode: this.brush.maskMode,
      };
      if (!this.requireEditableLayer(object.layerId)) return;
      if (
        ["terrain", "road", "river", "fence", "wall"].includes(gesture.tool)
      ) {
        object.type = gesture.tool === "terrain" ? "brush" : gesture.tool;
        object.points = gesture.points;
        object.thickness =
          gesture.tool === "terrain"
            ? this.brush.size
            : gesture.tool === "river"
              ? 140
              : gesture.tool === "road"
                ? 110
                : 24;
        const material = starterAssetById(this.selectedAssetId);
        if (material?.kind === "material")
          ((object.color = material.material.color),
            (object.materialId = material.id));
      } else if (gesture.tool === "roomCircle")
        ((object.type = "room"), (object.shape = "circle"));
      else if (gesture.tool === "roomRect")
        ((object.type = "room"), (object.shape = "rect"));
      else if (["door", "window"].includes(gesture.tool)) {
        object.width = Math.max(this.document.grid.size, width);
        object.height = Math.max(12, this.document.grid.size * 0.16);
        object.doorState = "closed";
      } else if (gesture.tool === "text" || gesture.tool === "marker") {
        object.text = this.annotationText;
        object.width = Math.max(160, width);
        object.height = 60;
        object.color = "#f5e6bd";
      } else if (gesture.tool === "light") {
        object.width = object.height = Math.max(this.document.grid.size, width);
        object.light = {
          brightRadius: object.width,
          dimRadius: object.width * 2,
          color: "#ffb35c",
          animation: "torch",
        };
      }
      this.commit(`Dodaj: ${gesture.tool}`, (draft) => {
        draft.objects.push(normalizeMapObject(object, draft));
      });
    },
    finishPolygon() {
      if (this.polygonDraft.length < 3) return;
      if (!this.requireEditableLayer("rooms")) return;
      const points = [...this.polygonDraft];
      const x = points.reduce((sum, point) => sum + point.x, 0) / points.length;
      const y = points.reduce((sum, point) => sum + point.y, 0) / points.length;
      this.commit("Dodaj wielokąt pomieszczenia", (draft) => {
        draft.objects.push(
          normalizeMapObject(
            {
              id: mapObjectId("room"),
              type: "room",
              shape: "polygon",
              layerId: "rooms",
              levelId: draft.activeLevelId,
              x,
              y,
              width:
                Math.max(...points.map((point) => point.x)) -
                Math.min(...points.map((point) => point.x)),
              height:
                Math.max(...points.map((point) => point.y)) -
                Math.min(...points.map((point) => point.y)),
              points,
              color: "#765d42",
            },
            draft,
          ),
        );
      });
      this.polygonDraft = [];
    },
    placeAsset(point) {
      if (!this.selectedAssetId) return;
      if (!this.requireEditableLayer("objects")) return;
      const asset = this.availableAssets.find(
        (item) => item.id === this.selectedAssetId,
      );
      if (!asset) return;
      this.commit("Umieść asset", (draft) => {
        const result = addAssetObject(draft, this.selectedAssetId, point, {
          asset,
        });
        draft.objects.push(...(Array.isArray(result) ? result : [result]));
      });
    },
    scatterAssets(center) {
      if (!this.requireEditableLayer("objects")) return;
      const asset = this.availableAssets.find(
        (item) => item.id === this.selectedAssetId,
      );
      if (!asset || asset.kind !== "sprite") return;
      this.commit("Rozsyp obiekty", (draft) => {
        const radius = this.brush.size;
        const minimumSpacing = Math.max(
          30,
          Math.min(asset.physicalSize.width, asset.physicalSize.height) *
            draft.pixelsPerMeter *
            0.6,
        );
        const points = [];
        for (let attempt = 0; attempt < 80 && points.length < 8; attempt += 1) {
          const angle = Math.random() * Math.PI * 2;
          const distance = Math.sqrt(Math.random()) * radius;
          const point = {
            x: center.x + Math.cos(angle) * distance,
            y: center.y + Math.sin(angle) * distance,
          };
          const blocked = [
            ...points,
            ...draft.objects.filter((item) =>
              ["door", "window"].includes(item.type),
            ),
          ].some(
            (item) =>
              Math.hypot(point.x - item.x, point.y - item.y) < minimumSpacing,
          );
          if (!blocked) points.push(point);
        }
        for (const point of points) {
          const object = addAssetObject(draft, asset.id, point, {
            asset,
            rotation: Math.random() * 360,
          });
          object.scaleX = object.scaleY = 0.8 + Math.random() * 0.4;
          draft.objects.push(object);
        }
      });
    },
    eraseAt(point) {
      const radius = this.brush.size / 2;
      this.commit("Gumka", (draft) => {
        draft.objects = draft.objects.filter(
          (object) =>
            object.locked ||
            this.isLayerLocked(object.layerId, draft) ||
            Math.hypot(point.x - object.x, point.y - object.y) > radius,
        );
      });
    },
    changeSelected(field, value, numeric = true) {
      const ids = new Set(this.selectedIds);
      this.commit("Zmień właściwości", (draft) => {
        draft.objects = draft.objects.map((object) => {
          if (
            !ids.has(object.id) ||
            object.locked ||
            this.isLayerLocked(object.layerId, draft)
          )
            return object;
          const nextValue = numeric ? Number(value) : value;
          const changed = { ...object, [field]: nextValue };
          if (Array.isArray(object.booleanParts)) {
            const dx = field === "x" ? nextValue - object.x : 0;
            const dy = field === "y" ? nextValue - object.y : 0;
            const sx = field === "width" ? nextValue / object.width : 1;
            const sy = field === "height" ? nextValue / object.height : 1;
            changed.booleanParts = object.booleanParts.map((part) => ({
              ...part,
              x: object.x + (part.x - object.x) * sx + dx,
              y: object.y + (part.y - object.y) * sy + dy,
              width: part.width * sx,
              height: part.height * sy,
              points: (part.points || []).map((point) => ({
                x: object.x + (point.x - object.x) * sx + dx,
                y: object.y + (point.y - object.y) * sy + dy,
              })),
            }));
          }
          return normalizeMapObject(changed, draft);
        });
      });
    },
    flipSelected(axis) {
      const field = axis === "x" ? "scaleX" : "scaleY";
      const ids = new Set(this.selectedIds);
      this.commit(`Odbij ${axis.toUpperCase()}`, (draft) => {
        for (const object of draft.objects)
          if (
            ids.has(object.id) &&
            !object.locked &&
            !this.isLayerLocked(object.layerId, draft)
          )
            object[field] = -(Number(object[field]) || 1);
      });
    },
    duplicateSelected() {
      const selected = this.selectedObjects.filter(
        (object) => !object.locked && !this.isLayerLocked(object.layerId),
      );
      this.commit("Kopiuj obiekty", (draft) => {
        const copies = selected.map((object) => {
          const copy = {
            ...cloneMapDocument(object),
            id: mapObjectId(object.type),
            x: object.x + 24,
            y: object.y + 24,
          };
          copy.points = (copy.points || []).map((point) => ({
            x: point.x + 24,
            y: point.y + 24,
          }));
          copy.booleanParts = (copy.booleanParts || []).map((part) => ({
            ...part,
            x: part.x + 24,
            y: part.y + 24,
            points: (part.points || []).map((point) => ({
              x: point.x + 24,
              y: point.y + 24,
            })),
          }));
          return copy;
        });
        draft.objects.push(...copies);
        this.selectedIds = copies.map((item) => item.id);
      });
    },
    groupSelected() {
      if (this.selectedIds.length < 2) return;
      const groupId = mapObjectId("group");
      const ids = new Set(this.selectedIds);
      this.commit("Grupuj obiekty", (draft) => {
        for (const object of draft.objects)
          if (
            ids.has(object.id) &&
            !object.locked &&
            !this.isLayerLocked(object.layerId, draft)
          )
            object.groupId = groupId;
      });
    },
    combineRooms(operation) {
      if (this.selectedRooms.length < 2) return;
      const source = cloneMapDocument(this.selectedRooms);
      const selected = new Set(source.map((room) => room.id));
      const minX = Math.min(...source.map((room) => room.x - room.width / 2));
      const maxX = Math.max(...source.map((room) => room.x + room.width / 2));
      const minY = Math.min(...source.map((room) => room.y - room.height / 2));
      const maxY = Math.max(...source.map((room) => room.y + room.height / 2));
      const id = mapObjectId("room-composite");
      const booleanParts = source.flatMap((room, roomIndex) => {
        const parts = Array.isArray(room.booleanParts)
          ? room.booleanParts
          : [
              {
                shape: room.shape || "rect",
                operation: "add",
                x: room.x,
                y: room.y,
                width: room.width,
                height: room.height,
                rotation: room.rotation || 0,
                scaleX: room.scaleX || 1,
                scaleY: room.scaleY || 1,
                points: room.points || [],
              },
            ];
        return parts.map((part) => ({
          ...part,
          operation:
            operation === "union" || roomIndex === 0
              ? part.operation || "add"
              : part.operation === "subtract"
                ? "add"
                : "subtract",
        }));
      });
      this.commit(
        operation === "subtract"
          ? "Odejmij pomieszczenia"
          : "Połącz pomieszczenia",
        (draft) => {
          draft.objects = draft.objects.filter(
            (object) => !selected.has(object.id),
          );
          draft.objects.push(
            normalizeMapObject(
              {
                id,
                type: "room",
                shape: "composite",
                layerId: source[0].layerId,
                levelId: source[0].levelId,
                x: (minX + maxX) / 2,
                y: (minY + maxY) / 2,
                width: maxX - minX,
                height: maxY - minY,
                color: source[0].color || "#765d42",
                booleanParts,
              },
              draft,
            ),
          );
        },
      );
      this.selectedIds = [id];
    },
    removeSelected() {
      const ids = new Set(this.selectedIds);
      this.commit("Usuń obiekty", (draft) => {
        draft.objects = draft.objects.filter(
          (object) =>
            !ids.has(object.id) ||
            object.locked ||
            this.isLayerLocked(object.layerId, draft),
        );
      });
      this.selectedIds = [];
    },
    toggleLayer(layer, field) {
      layer[field] = !layer[field];
      this.markDirty();
    },
    moveLayer(layer, direction) {
      layer.order += direction * 10;
      this.markDirty();
    },
    addLevel() {
      const index = this.document.levels.length;
      const level = {
        id: mapObjectId("level"),
        name: `Poziom ${index}`,
        elevation: index * 3,
        visible: true,
      };
      this.document.levels.push(level);
      this.document.activeLevelId = level.id;
      this.markDirty();
    },
    toggleLevel(level) {
      level.visible = level.visible === false;
      this.markDirty();
    },
    async loadAssets() {
      const payload = await mapBuilderApiClient.assets(this.campaignId);
      this.customAssets = payload.items || [];
    },
    async importAsset(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file) return;
      try {
        await mapBuilderApiClient.uploadAsset(this.campaignId, file, {
          name: file.name.replace(/\.[^.]+$/u, ""),
          category: this.assetCollection || "własne",
          tags: ["import"],
          physicalSize: { width: 1, height: 1, unit: "m" },
          anchor: { x: 0.5, y: 0.5 },
        });
        await this.loadAssets();
      } catch (error) {
        this.error = this.apiError(
          error,
          "Nie udało się zaimportować grafiki.",
        );
      }
    },
    async importPackage(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file) return;
      try {
        const payload = await mapBuilderApiClient.importPackage(
          this.campaignId,
          file,
        );
        await this.loadAssets();
        this.error = `Zaimportowano ${payload.importedCount || 0} elementów pakietu.`;
      } catch (error) {
        this.error = this.apiError(
          error,
          "Nie udało się zaimportować pakietu.",
        );
      }
    },
    async loadAiStatus() {
      try {
        this.aiStatus = await mapBuilderApiClient.aiStatus(this.campaignId);
      } catch (_error) {
        this.aiStatus = { available: false, reason: "unavailable" };
      }
    },
    async requestAi() {
      if (this.aiMode === "selection" && !this.selectedObjects.length) {
        this.error =
          "Zaznacz obiekty lub obszar, który AI ma wyposażyć albo zmienić.";
        return;
      }
      if (!this.mapId && !(await this.save())) return;
      this.aiBusy = true;
      this.aiProposal = null;
      try {
        const payload = await mapBuilderApiClient.createAiJob(
          this.campaignId,
          this.mapId,
          {
            kind: this.aiMode === "asset" ? "generate_asset" : this.aiMode,
            prompt: this.aiPrompt.trim(),
            baseRevision: this.currentRevision,
            selection:
              this.aiMode === "selection" ? this.aiSelectionPayload() : null,
            idempotencyKey:
              window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
            editorId: this.editorId,
          },
        );
        this.aiJob = payload.job;
        this.pollAi();
      } catch (error) {
        this.aiBusy = false;
        this.error = this.apiError(
          error,
          "Nie udało się uruchomić zadania AI.",
        );
      }
    },
    aiSelectionPayload() {
      if (!this.selectedObjects.length) return { objectIds: [], bounds: null };
      return {
        objectIds: [...this.selectedIds],
        bounds: {
          x: Math.max(
            0,
            Math.min(
              ...this.selectedObjects.map(
                (object) => object.x - object.width / 2,
              ),
            ),
          ),
          y: Math.max(
            0,
            Math.min(
              ...this.selectedObjects.map(
                (object) => object.y - object.height / 2,
              ),
            ),
          ),
          right: Math.min(
            this.document.width,
            Math.max(
              ...this.selectedObjects.map(
                (object) => object.x + object.width / 2,
              ),
            ),
          ),
          bottom: Math.min(
            this.document.height,
            Math.max(
              ...this.selectedObjects.map(
                (object) => object.y + object.height / 2,
              ),
            ),
          ),
        },
      };
    },
    async pollAi() {
      if (!this.aiJob || !this.aiBusy) return;
      try {
        const payload = await mapBuilderApiClient.aiJob(
          this.campaignId,
          this.mapId,
          this.aiJob.id,
        );
        this.aiJob = payload.job;
        if (payload.job.status === "completed") {
          this.aiBusy = false;
          if (payload.job.result?.asset) {
            await this.loadAssets();
            this.selectedAssetId = payload.job.result.asset.id;
            this.activeTool = "asset";
          } else this.aiProposal = payload.job.result?.proposal || null;
          return;
        }
        if (["failed", "cancelled"].includes(payload.job.status)) {
          this.aiBusy = false;
          this.error = payload.job.error || `Zadanie AI: ${payload.job.status}`;
          return;
        }
      } catch (error) {
        this.aiBusy = false;
        this.error = this.apiError(error, "Nie udało się odczytać zadania AI.");
        return;
      }
      this.aiPollTimer = window.setTimeout(() => this.pollAi(), 1500);
    },
    async cancelAi() {
      if (!this.aiJob) return;
      await mapBuilderApiClient
        .cancelAiJob(this.campaignId, this.mapId, this.aiJob.id)
        .catch(() => {});
      this.aiBusy = false;
    },
    acceptAi() {
      if (!this.aiProposal) return;
      try {
        this.commit("Akceptuj propozycję AI", (draft) =>
          applyAiProposal(draft, this.aiProposal),
        );
        this.aiProposal = null;
      } catch (error) {
        this.error = `Propozycja AI nie przeszła walidacji: ${error.message}`;
      }
    },
    async publish() {
      if (this.saveState === "dirty" && !(await this.save())) return;
      this.busy = true;
      try {
        const blob = await this.$refs.canvas.exportBlob({
          format: "webp",
          includeGrid: false,
        });
        const render = await mapBuilderApiClient.uploadRender(
          this.campaignId,
          this.mapId,
          blob,
          "webp",
        );
        const payload = await mapBuilderApiClient.publish(
          this.campaignId,
          this.mapId,
          {
            revision: this.currentRevision,
            sceneId: Number(this.sceneId) || null,
            renderAssetUrl: render.asset.url,
            editorId: this.editorId,
          },
        );
        this.$emit("published", payload);
      } catch (error) {
        this.error = this.apiError(error, "Nie udało się opublikować mapy.");
      } finally {
        this.busy = false;
      }
    },
    async downloadExport(format) {
      try {
        const blob = await this.$refs.canvas.exportBlob({
          format,
          includeGrid: this.exportGrid,
        });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement("a");
        anchor.href = url;
        anchor.download = `${this.name || "mapa"}.${format}`;
        anchor.click();
        URL.revokeObjectURL(url);
      } catch (_error) {
        this.error = "Nie udało się wyeksportować mapy.";
      }
    },
    async loadRevisions() {
      try {
        const payload = await mapBuilderApiClient.revisions(
          this.campaignId,
          this.mapId,
        );
        this.revisions = payload.items || [];
      } catch (error) {
        this.error = this.apiError(error, "Nie udało się pobrać rewizji.");
      }
    },
    async restoreRevision() {
      if (!this.restoreRevisionId) return;
      try {
        const payload = await mapBuilderApiClient.restore(
          this.campaignId,
          this.mapId,
          this.restoreRevisionId,
          this.editorId,
        );
        this.currentRevision = payload.revision.number;
        this.document = payload.revision.document;
        this.history = createCommandHistory(this.document);
        this.saveState = "saved";
        this.restoreRevisionId = "";
      } catch (error) {
        this.error = this.apiError(error, "Nie udało się przywrócić rewizji.");
      }
    },
    onKeyDown(event) {
      const editable =
        ["INPUT", "TEXTAREA", "SELECT"].includes(event.target?.tagName) ||
        event.target?.isContentEditable;
      if (editable) return;
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "z") {
        event.preventDefault();
        if (event.shiftKey) this.redo();
        else this.undo();
      } else if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === "s"
      ) {
        event.preventDefault();
        this.save();
      } else if (["Delete", "Backspace"].includes(event.key))
        this.removeSelected();
    },
    apiError(error, fallback) {
      const code = error?.code || error?.payload?.code;
      const messages = {
        map_revision_conflict:
          "Mapa została zmieniona w innym oknie. Otwórz najnowszą wersję przed ponownym zapisem.",
        map_locked: "Ten projekt jest obecnie edytowany przez inną osobę.",
        ai_not_configured:
          "AI jest niedostępne: administrator nie skonfigurował klucza dostawcy.",
        ai_cost_limit_exceeded:
          "Dzienny limit kosztu AI dla kampanii został wyczerpany.",
        ai_validation_failed:
          "Odpowiedź AI nie przeszła walidacji geometrii, assetów lub przejść.",
      };
      return messages[code] || fallback;
    },
  },
  expose: ["save"],
};
</script>

<style src="./map-builder.css"></style>
