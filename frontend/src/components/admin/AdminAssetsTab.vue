<template>
  <section class="asset-library">
    <header class="asset-library__header">
      <div class="asset-library__heading">
        <p>{{ $t("admin.assets.eyebrow") }}</p>
        <div>
          <h2>{{ $t("admin.assets.title") }}</h2>
          <small>{{ pagination.total }}</small>
        </div>
        <span>{{ $t("admin.assets.description") }}</span>
      </div>
      <div class="asset-library__header-actions">
        <button
          class="admin-secondary asset-library__mobile"
          type="button"
          @click="leftOpen = true"
        >
          {{ $t("admin.assets.filters") }}
        </button>
        <label class="admin-primary asset-library__upload">
          {{
            busy === "upload"
              ? $t("admin.actions.saving")
              : $t("admin.assets.upload")
          }}
          <input type="file" :disabled="Boolean(busy)" @change="upload" />
        </label>
        <button
          class="admin-secondary"
          type="button"
          :disabled="Boolean(busy)"
          @click="registerExternal"
        >
          {{ $t("admin.assets.externalUrl") }}
        </button>
        <button class="admin-secondary" type="button" @click="refresh">
          {{ $t("admin.actions.refresh") }}
        </button>
      </div>
    </header>

    <p v-if="error" class="admin-alert error" role="alert">{{ error }}</p>
    <p v-if="notice" class="admin-alert" role="status">{{ notice }}</p>

    <div
      class="asset-library__shell"
      :class="{ 'has-inspector': inspectorOpen }"
    >
      <button
        v-if="leftOpen"
        class="asset-library__backdrop"
        type="button"
        aria-label="Close"
        @click="leftOpen = false"
      ></button>
      <aside class="asset-library__left" :class="{ 'is-open': leftOpen }">
        <div class="asset-library__aside-title">
          <strong>{{ $t("admin.assets.categories") }}</strong>
          <button
            class="asset-library__close"
            type="button"
            @click="leftOpen = false"
          >
            ×
          </button>
        </div>
        <button
          type="button"
          :class="{ active: !filters.category }"
          @click="setCategory('')"
        >
          <span>{{ $t("admin.assets.all") }}</span
          ><small>{{ pagination.total }}</small>
        </button>
        <button
          v-for="category in categories"
          :key="category"
          type="button"
          :class="{ active: filters.category === category }"
          @click="setCategory(category)"
        >
          <span>{{ $t(`admin.assets.category.${category}`) }}</span>
          <small>{{ facetCount("category", category) }}</small>
        </button>

        <div class="asset-library__collections-title">
          <strong>{{ $t("admin.assets.collections") }}</strong>
          <button
            type="button"
            :title="$t('admin.assets.newCollection')"
            @click="createCollection"
          >
            +
          </button>
        </div>
        <button
          type="button"
          :class="{ active: !filters.collectionId }"
          @click="setCollection('')"
        >
          {{ $t("admin.assets.allCollections") }}
        </button>
        <button
          v-for="collection in collections"
          :key="collection.id"
          type="button"
          :class="{ active: Number(filters.collectionId) === collection.id }"
          @click="setCollection(collection.id)"
        >
          <span>{{ collection.name }}</span
          ><small>{{ collection.assetCount }}</small>
        </button>

        <details class="asset-library__composer">
          <summary>{{ $t("admin.assets.characterSet") }}</summary>
          <input
            v-model.trim="characterSet.name"
            :placeholder="$t('admin.assets.setName')"
            maxlength="150"
          />
          <label v-for="slot in characterSlots" :key="slot">
            <span>{{ slot }}</span>
            <input
              v-model.number="characterSet.slots[slot]"
              type="number"
              min="1"
              :placeholder="$t('admin.assets.assetId')"
            />
          </label>
          <button
            class="admin-primary"
            type="button"
            :disabled="Boolean(busy)"
            @click="createCharacterSet"
          >
            {{ $t("admin.assets.createSet") }}
          </button>
        </details>
      </aside>

      <main class="asset-library__center">
        <div class="asset-library__toolbar">
          <div class="asset-library__toolbar-main">
            <label class="asset-library__search">
              <span aria-hidden="true">⌕</span>
              <input
                v-model="search"
                type="search"
                :placeholder="$t('admin.assets.search')"
                @input="scheduleSearch"
              />
            </label>
            <select v-model="filters.resourceType" @change="filterChanged">
              <option value="">{{ $t("admin.assets.allTypes") }}</option>
              <option
                v-for="value in ['image', 'audio', 'video', 'document', 'raw']"
                :key="value"
                :value="value"
              >
                {{ value }}
              </option>
            </select>
            <div
              v-if="filters.category === 'audio'"
              class="asset-library__audio-library-filter"
            >
              <input
                v-model.trim="audioLibrarySearch"
                type="search"
                maxlength="120"
                :placeholder="$t('admin.assets.searchGameMasterAudio')"
                :aria-label="$t('admin.assets.searchGameMasterAudio')"
                @input="scheduleAudioLibrarySearch"
              />
              <select
                :value="filters.audioLibraryId"
                :disabled="audioLibraryLoading"
                @change="setAudioLibrary($event.target.value)"
              >
                <option value="">
                  {{ $t("admin.assets.allGameMasterAudio") }}
                </option>
                <option
                  v-for="library in audioLibraryOptions"
                  :key="library.id"
                  :value="library.id"
                >
                  {{
                    library.owner?.username || $t("admin.assets.unknownOwner")
                  }}
                  · {{ library.name }} ({{ library.trackCount }})
                </option>
              </select>
              <div
                v-if="audioLibraryPagination.total"
                class="asset-library__audio-library-pages"
              >
                <button
                  type="button"
                  :disabled="
                    audioLibraryPagination.page <= 1 || audioLibraryLoading
                  "
                  :aria-label="$t('admin.assets.previousAudioLibraryPage')"
                  @click="goAudioLibraryPage(audioLibraryPagination.page - 1)"
                >
                  ‹
                </button>
                <small>
                  {{
                    $t("admin.assets.audioLibraryPage", {
                      from: audioLibraryRangeStart,
                      to: audioLibraryRangeEnd,
                      total: audioLibraryPagination.total,
                    })
                  }}
                </small>
                <button
                  type="button"
                  :disabled="
                    audioLibraryPagination.page >=
                      audioLibraryPagination.pages || audioLibraryLoading
                  "
                  :aria-label="$t('admin.assets.nextAudioLibraryPage')"
                  @click="goAudioLibraryPage(audioLibraryPagination.page + 1)"
                >
                  ›
                </button>
              </div>
            </div>
            <select v-model="filters.provider" @change="filterChanged">
              <option value="">{{ $t("admin.assets.allProviders") }}</option>
              <option value="external">
                {{ $t("admin.assets.external") }}
              </option>
              <option value="cloudinary">Cloudinary</option>
              <option value="r2">R2</option>
            </select>
            <select v-model="filters.sort" @change="filterChanged">
              <option value="createdAt">{{ $t("admin.assets.newest") }}</option>
              <option value="name">{{ $t("admin.assets.byName") }}</option>
              <option value="fileSize">{{ $t("admin.assets.bySize") }}</option>
            </select>
            <details class="asset-library__advanced-filters">
              <summary>
                {{ $t("admin.assets.moreFilters") }}
                <small v-if="hasAdvancedFilters">•</small>
              </summary>
              <div>
                <input
                  v-model.trim="filters.format"
                  :placeholder="$t('admin.assets.format')"
                  @change="filterChanged"
                />
                <select v-model="filters.visibility" @change="filterChanged">
                  <option value="">
                    {{ $t("admin.assets.allVisibility") }}
                  </option>
                  <option
                    v-for="value in ['public', 'campaign', 'private']"
                    :key="value"
                    :value="value"
                  >
                    {{ $t(`admin.assets.visibility.${value}`) }}
                  </option>
                </select>
                <select v-model="filters.assignment" @change="filterChanged">
                  <option value="">
                    {{ $t("admin.assets.allAssignments") }}
                  </option>
                  <option value="assigned">
                    {{ $t("admin.assets.assigned") }}
                  </option>
                  <option value="unassigned">
                    {{ $t("admin.assets.unassigned") }}
                  </option>
                </select>
                <select v-model="filters.status" @change="filterChanged">
                  <option value="">{{ $t("admin.assets.allStatuses") }}</option>
                  <option
                    v-for="value in [
                      'pending',
                      'ready',
                      'failed',
                      'relocating',
                      'deleting',
                      'delete_failed',
                    ]"
                    :key="value"
                    :value="value"
                  >
                    {{ $t(`admin.assets.status.${value}`) }}
                  </option>
                </select>
                <input
                  v-model.trim="filters.ownerId"
                  inputmode="numeric"
                  :placeholder="$t('admin.assets.ownerId')"
                  @change="filterChanged"
                />
                <input
                  v-model.trim="filters.campaignId"
                  inputmode="numeric"
                  :placeholder="$t('admin.assets.campaignId')"
                  @change="filterChanged"
                />
              </div>
            </details>
            <button
              v-if="hasFilters"
              class="asset-library__clear-filters"
              type="button"
              :title="$t('admin.assets.clearFilters')"
              @click="clearFilters"
            >
              ×
            </button>
            <div class="asset-library__view-toggle">
              <button
                type="button"
                :class="{ active: view === 'grid' }"
                :title="$t('admin.assets.gridView')"
                @click="view = 'grid'"
              >
                ▦
              </button>
              <button
                type="button"
                :class="{ active: view === 'table' }"
                :title="$t('admin.assets.tableView')"
                @click="view = 'table'"
              >
                ☷
              </button>
            </div>
          </div>
        </div>

        <details v-if="selection.size" class="asset-library__bulk">
          <summary>
            <strong>{{
              $t("admin.assets.selected", { count: selection.size })
            }}</strong>
            <span>{{ $t("admin.assets.bulkActions") }}</span>
          </summary>
          <div class="asset-library__bulk-fields">
            <select v-model="bulk.category">
              <option value="">{{ $t("admin.assets.keepCategory") }}</option>
              <option
                v-for="category in categories"
                :key="category"
                :value="category"
              >
                {{ $t(`admin.assets.category.${category}`) }}
              </option>
            </select>
            <select v-model="bulk.tagMode">
              <option value="add">{{ $t("admin.assets.addTags") }}</option>
              <option value="remove">
                {{ $t("admin.assets.removeTags") }}
              </option>
              <option value="replace">
                {{ $t("admin.assets.replaceTags") }}
              </option>
            </select>
            <input v-model="bulk.tags" :placeholder="$t('admin.assets.tags')" />
            <select v-model="bulk.visibility">
              <option value="">{{ $t("admin.assets.keepVisibility") }}</option>
              <option
                v-for="value in ['public', 'campaign', 'private']"
                :key="value"
                :value="value"
              >
                {{ $t(`admin.assets.visibility.${value}`) }}
              </option>
            </select>
            <input
              v-if="bulk.visibility === 'campaign'"
              v-model.number="bulk.campaignId"
              type="number"
              min="1"
              :placeholder="$t('admin.assets.campaignId')"
            />
            <input
              v-model="bulk.metadata"
              :placeholder="$t('admin.assets.bulkMetadata')"
            />
            <select v-model="bulk.collectionId">
              <option value="">{{ $t("admin.assets.noCollection") }}</option>
              <option
                v-for="collection in collections"
                :key="collection.id"
                :value="collection.id"
              >
                {{ collection.name }}
              </option>
            </select>
            <select v-model="bulk.collectionMode">
              <option value="add">
                {{ $t("admin.assets.addCollection") }}
              </option>
              <option value="remove">
                {{ $t("admin.assets.removeCollection") }}
              </option>
            </select>
            <div class="asset-library__bulk-actions">
              <button
                class="admin-primary"
                type="button"
                :disabled="Boolean(busy)"
                @click="applyBulk"
              >
                {{ $t("admin.assets.apply") }}
              </button>
              <button
                class="admin-secondary"
                type="button"
                @click="clearSelection"
              >
                {{ $t("admin.assets.clear") }}
              </button>
            </div>
          </div>
        </details>

        <div v-if="loading" class="admin-loading">
          <i></i>{{ $t("admin.loading") }}
        </div>
        <p v-else-if="!items.length" class="asset-library__empty">
          {{ $t("admin.assets.empty") }}
        </p>

        <div v-else-if="view === 'grid'" class="asset-library__grid">
          <article
            v-for="asset in items"
            :key="asset.id"
            :class="{ selected: selectedId === asset.id }"
            @click="openAsset(asset.id)"
          >
            <label class="asset-library__check" @click.stop>
              <input
                type="checkbox"
                :checked="selection.has(asset.id)"
                @change="toggleSelection(asset.id)"
              />
            </label>
            <div class="asset-library__thumb">
              <img
                v-if="asset.resourceType === 'image' && asset.url"
                :src="asset.url"
                :alt="asset.name"
              />
              <span v-else>{{ typeGlyph(asset.resourceType) }}</span>
            </div>
            <div class="asset-library__card-body">
              <strong :title="asset.name">{{ asset.name }}</strong>
              <small class="asset-library__card-id"
                >#{{ asset.id }} · {{ asset.format || asset.resourceType }} ·
                {{ asset.category }}</small
              >
              <div class="asset-library__card-meta">
                <span>{{ asset.provider }}</span>
                <span>{{ formatBytes(asset.fileSize) }}</span>
              </div>
              <small
                v-if="asset.category === 'audio' && asset.owner?.username"
                class="asset-library__audio-owner"
              >
                {{ $t("admin.assets.gameMasterAudio") }} ·
                {{ asset.owner.username }}
              </small>
              <small v-if="asset.sourceUrl" class="asset-library__source-badge">
                {{ $t("admin.assets.external") }}
              </small>
            </div>
          </article>
        </div>

        <div v-else-if="items.length" class="asset-library__table-wrap">
          <table>
            <thead>
              <tr>
                <th></th>
                <th>{{ $t("admin.assets.name") }}</th>
                <th>ID</th>
                <th>{{ $t("admin.assets.categoryLabel") }}</th>
                <th>{{ $t("admin.assets.type") }}</th>
                <th>{{ $t("admin.assets.provider") }}</th>
                <th>{{ $t("admin.assets.size") }}</th>
                <th>{{ $t("admin.assets.assignment") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="asset in items"
                :key="asset.id"
                :class="{ selected: selectedId === asset.id }"
                @click="openAsset(asset.id)"
              >
                <td @click.stop>
                  <input
                    type="checkbox"
                    :checked="selection.has(asset.id)"
                    @change="toggleSelection(asset.id)"
                  />
                </td>
                <td>
                  <strong>{{ asset.name }}</strong>
                  <small
                    v-if="asset.category === 'audio' && asset.owner?.username"
                    class="asset-library__audio-owner"
                  >
                    {{ asset.owner.username }}
                  </small>
                </td>
                <td>#{{ asset.id }}</td>
                <td>{{ $t(`admin.assets.category.${asset.category}`) }}</td>
                <td>{{ asset.format || asset.resourceType }}</td>
                <td>{{ asset.provider }}</td>
                <td>{{ formatBytes(asset.fileSize) }}</td>
                <td>{{ $t(`admin.assets.${asset.assignment}`) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer class="asset-library__pagination">
          <button
            type="button"
            :disabled="pagination.page <= 1 || loading"
            @click="goPage(pagination.page - 1)"
          >
            ‹
          </button>
          <span
            >{{ pagination.page }} / {{ pagination.pages }} ·
            {{ pagination.total }}</span
          >
          <button
            type="button"
            :disabled="pagination.page >= pagination.pages || loading"
            @click="goPage(pagination.page + 1)"
          >
            ›
          </button>
        </footer>
      </main>

      <button
        v-if="inspectorOpen"
        class="asset-library__backdrop inspector"
        type="button"
        aria-label="Close"
        @click="inspectorOpen = false"
      ></button>
      <aside
        class="asset-library__inspector"
        :class="{ 'is-open': inspectorOpen }"
      >
        <div v-if="detailLoading" class="admin-loading">
          <i></i>{{ $t("admin.loading") }}
        </div>
        <template v-else-if="detail">
          <header>
            <div>
              <small>#{{ detail.id }} · r{{ detail.revision }}</small>
              <h3>{{ detail.name }}</h3>
            </div>
            <button
              class="asset-library__close"
              type="button"
              @click="inspectorOpen = false"
            >
              ×
            </button>
          </header>
          <div class="asset-library__preview">
            <img
              v-if="detail.resourceType === 'image' && detail.url"
              :src="detail.url"
              :alt="detail.name"
            />
            <audio
              v-else-if="detail.resourceType === 'audio' && detail.url"
              :src="detail.url"
              controls
            />
            <video
              v-else-if="detail.resourceType === 'video' && detail.url"
              :src="detail.url"
              controls
            />
            <iframe
              v-else-if="detail.mimeType === 'application/pdf' && detail.url"
              :src="detail.url"
              :title="detail.name"
            ></iframe>
            <span v-else>{{ typeGlyph(detail.resourceType) }}</span>
          </div>
          <form class="asset-library__form" @submit.prevent="saveDetail">
            <label class="asset-library__field--wide"
              ><span>{{ $t("admin.assets.name") }}</span
              ><input v-model.trim="draft.name" maxlength="255" required
            /></label>
            <label class="asset-library__field--wide"
              ><span>{{ $t("admin.assets.descriptionLabel") }}</span
              ><textarea v-model="draft.description" rows="3"></textarea>
            </label>
            <label class="asset-library__field--wide"
              ><span>{{ $t("admin.assets.tags") }}</span
              ><input v-model="draft.tags"
            /></label>
            <label
              ><span>{{ $t("admin.assets.categoryLabel") }}</span
              ><select v-model="draft.category">
                <option
                  v-for="category in categories"
                  :key="category"
                  :value="category"
                >
                  {{ $t(`admin.assets.category.${category}`) }}
                </option>
              </select></label
            >
            <label
              ><span>{{ $t("admin.assets.visibilityLabel") }}</span
              ><select v-model="draft.visibility">
                <option
                  v-for="value in ['public', 'campaign', 'private']"
                  :key="value"
                  :value="value"
                  :disabled="
                    detail.provider === 'external' && value !== 'public'
                  "
                >
                  {{ $t(`admin.assets.visibility.${value}`) }}
                </option>
              </select></label
            >
            <label v-if="draft.visibility === 'campaign'"
              ><span>{{ $t("admin.assets.campaignId") }}</span
              ><input
                v-model.number="draft.campaignId"
                type="number"
                min="1"
                required
            /></label>
            <label class="asset-library__field--wide"
              ><span>{{ $t("admin.assets.metadata") }}</span
              ><textarea
                v-model="draft.metadata"
                rows="6"
                spellcheck="false"
              ></textarea>
            </label>
            <button
              class="admin-primary"
              type="submit"
              :disabled="Boolean(busy)"
            >
              {{ $t("admin.assets.save") }}
            </button>
          </form>
          <dl class="asset-library__facts">
            <div>
              <dt>{{ $t("admin.assets.provider") }}</dt>
              <dd>
                {{ detail.provider }} / {{ detail.providerContainer || "—" }}
              </dd>
            </div>
            <div v-if="detail.sourceUrl" class="asset-library__source-url">
              <dt>{{ $t("admin.assets.sourceUrl") }}</dt>
              <dd>
                <a
                  :href="detail.sourceUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ detail.sourceUrl }}
                </a>
              </dd>
            </div>
            <div v-if="detail.sourceUrl">
              <dt>{{ $t("admin.assets.availability") }}</dt>
              <dd>
                {{
                  $t(
                    `admin.assets.availabilityStatus.${detail.availabilityStatus || "unknown"}`,
                  )
                }}
              </dd>
            </div>
            <div>
              <dt>Provider ID</dt>
              <dd>{{ detail.providerAssetId || "—" }}</dd>
            </div>
            <div>
              <dt>Public ID / key</dt>
              <dd>{{ detail.publicId || detail.providerAssetId || "—" }}</dd>
            </div>
            <div>
              <dt>{{ $t("admin.assets.file") }}</dt>
              <dd>{{ detail.filename || "—" }}</dd>
            </div>
            <div>
              <dt>{{ $t("admin.assets.format") }}</dt>
              <dd>{{ detail.format || "—" }} · {{ detail.mimeType || "—" }}</dd>
            </div>
            <div>
              <dt>{{ $t("admin.assets.size") }}</dt>
              <dd>
                {{ formatBytes(detail.fileSize) }} ·
                {{ detail.width || "—" }}×{{ detail.height || "—" }}
              </dd>
            </div>
            <div>
              <dt>{{ $t("admin.assets.owner") }}</dt>
              <dd>{{ detail.owner?.username || "—" }}</dd>
            </div>
          </dl>
          <p v-if="detail.sourceUrl" class="asset-library__external-note">
            {{ $t("admin.assets.externalAvailabilityNote") }}
          </p>
          <details class="asset-library__provider-metadata">
            <summary>{{ $t("admin.assets.providerMetadata") }}</summary>
            <pre>{{
              JSON.stringify(detail.providerMetadata || {}, null, 2)
            }}</pre>
          </details>
          <section
            v-if="personalAudioTracks.length"
            class="asset-library__audio-publish"
          >
            <strong>{{ $t("admin.assets.publishAudioTitle") }}</strong>
            <p>{{ $t("admin.assets.publishAudioHint") }}</p>
            <article v-for="track in personalAudioTracks" :key="track.id">
              <header>
                <span>{{ track.name }}</span>
                <small>{{
                  track.ownerUsername || $t("admin.assets.unknownOwner")
                }}</small>
              </header>
              <select v-model.number="audioTargets[track.id]">
                <option value="">
                  {{ $t("admin.assets.chooseGlobalAudioLibrary") }}
                </option>
                <option
                  v-for="library in detail.globalAudioLibraries"
                  :key="library.id"
                  :value="library.id"
                >
                  {{ library.settingName || "—" }} · {{ library.name }}
                </option>
              </select>
              <div>
                <button
                  class="admin-secondary"
                  type="button"
                  :disabled="Boolean(busy) || !audioTargets[track.id]"
                  @click="publishAudioTrack(track, 'copy')"
                >
                  {{ $t("admin.assets.copyToGlobal") }}
                </button>
                <button
                  class="admin-primary"
                  type="button"
                  :disabled="Boolean(busy) || !audioTargets[track.id]"
                  @click="publishAudioTrack(track, 'move')"
                >
                  {{ $t("admin.assets.moveToGlobal") }}
                </button>
              </div>
            </article>
            <p
              v-if="!detail.globalAudioLibraries.length"
              class="asset-library__audio-empty"
            >
              {{ $t("admin.assets.noGlobalAudioLibraries") }}
            </p>
          </section>
          <section class="asset-library__relations">
            <strong>{{ $t("admin.assets.relations") }}</strong>
            <p v-if="!detail.relations.length">
              {{ $t("admin.assets.noRelations") }}
            </p>
            <ul v-else>
              <li
                v-for="relation in detail.relations"
                :key="`${relation.module}:${relation.recordId}`"
              >
                {{ relation.label }} #{{ relation.recordId
                }}<span v-if="relation.campaignId">
                  · C{{ relation.campaignId }}</span
                >
                <span v-if="relation.sceneId">
                  · {{ $t("admin.assets.scene") }} #{{ relation.sceneId }}</span
                >
                <span v-if="relation.name"> · {{ relation.name }}</span>
                <span v-if="relation.libraryName">
                  · {{ relation.libraryName }}</span
                >
                <span v-if="relation.ownerUsername">
                  · {{ relation.ownerUsername }}</span
                >
              </li>
            </ul>
          </section>
          <div class="asset-library__danger">
            <label class="admin-secondary"
              >{{ $t("admin.assets.replace")
              }}<input type="file" @change="replaceAsset"
            /></label>
            <button
              v-if="detail.status === 'delete_failed'"
              class="admin-secondary"
              type="button"
              @click="retryPurge"
            >
              {{ $t("admin.assets.retryPurge") }}
            </button>
            <button
              class="admin-danger"
              type="button"
              :disabled="Boolean(busy)"
              @click="deleteAsset"
            >
              {{ $t("admin.assets.delete") }}
            </button>
          </div>
        </template>
        <p v-else class="asset-library__empty">
          {{ $t("admin.assets.choose") }}
        </p>
      </aside>
    </div>
  </section>
</template>

<script>
import { adminApiClient } from "@/lib/admin/adminApiClient";

const categories = [
  "characters",
  "npcs",
  "monsters",
  "items",
  "maps",
  "textures",
  "map-creator",
  "audio",
  "video",
  "documents",
  "other",
];

export default {
  name: "AdminAssetsTab",
  emits: ["loaded"],
  data: () => ({
    categories,
    characterSlots: ["avatar", "portrait", "token", "fullbody"],
    items: [],
    collections: [],
    audioLibrarySearch: "",
    audioLibraryResults: [],
    audioLibrarySelected: null,
    audioLibraryPagination: { page: 1, perPage: 25, total: 0, pages: 1 },
    audioLibraryLoading: false,
    audioLibraryController: null,
    audioLibrarySearchTimer: null,
    audioTargets: {},
    facets: {},
    pagination: { page: 1, perPage: 30, total: 0, pages: 1 },
    filters: {
      category: "",
      resourceType: "",
      provider: "",
      format: "",
      visibility: "",
      assignment: "",
      status: "",
      ownerId: "",
      campaignId: "",
      collectionId: "",
      audioLibraryId: "",
      sort: "createdAt",
      direction: "desc",
    },
    search: "",
    view: "grid",
    selection: new Set(),
    selectedId: null,
    detail: null,
    draft: {},
    loading: false,
    detailLoading: false,
    error: "",
    notice: "",
    busy: "",
    controller: null,
    searchTimer: null,
    leftOpen: false,
    inspectorOpen: false,
    bulk: {
      category: "",
      tags: "",
      tagMode: "add",
      visibility: "",
      campaignId: "",
      metadata: "",
      collectionId: "",
      collectionMode: "add",
    },
    characterSet: {
      name: "",
      slots: { avatar: "", portrait: "", token: "", fullbody: "" },
    },
  }),
  mounted() {
    this.refresh();
  },
  beforeUnmount() {
    this.controller?.abort();
    this.audioLibraryController?.abort();
    clearTimeout(this.searchTimer);
    clearTimeout(this.audioLibrarySearchTimer);
  },
  computed: {
    hasAdvancedFilters() {
      return Boolean(
        this.filters.format ||
        this.filters.visibility ||
        this.filters.assignment ||
        this.filters.status ||
        this.filters.ownerId ||
        this.filters.campaignId,
      );
    },
    hasFilters() {
      return Boolean(
        this.search ||
        this.filters.category ||
        this.filters.resourceType ||
        this.filters.provider ||
        this.filters.collectionId ||
        this.filters.audioLibraryId ||
        this.hasAdvancedFilters ||
        this.filters.sort !== "createdAt" ||
        this.filters.direction !== "desc",
      );
    },
    personalAudioTracks() {
      return (this.detail?.audioTracks || []).filter(
        (track) => track.libraryScope === "personal",
      );
    },
    audioLibraryOptions() {
      if (
        !this.audioLibrarySelected ||
        this.audioLibraryResults.some(
          (library) => library.id === this.audioLibrarySelected.id,
        )
      ) {
        return this.audioLibraryResults;
      }
      return [this.audioLibrarySelected, ...this.audioLibraryResults];
    },
    audioLibraryRangeStart() {
      if (!this.audioLibraryPagination.total) return 0;
      return (
        (this.audioLibraryPagination.page - 1) *
          this.audioLibraryPagination.perPage +
        1
      );
    },
    audioLibraryRangeEnd() {
      return Math.min(
        this.audioLibraryPagination.total,
        this.audioLibraryPagination.page * this.audioLibraryPagination.perPage,
      );
    },
  },
  methods: {
    async refresh() {
      await Promise.all([this.load(), this.loadCollections()]);
    },
    async load() {
      this.controller?.abort();
      const controller = new AbortController();
      this.controller = controller;
      this.loading = true;
      this.error = "";
      try {
        const result = await adminApiClient.mediaAssets(
          {
            ...this.filters,
            q: this.search,
            page: this.pagination.page,
            perPage: this.pagination.perPage,
          },
          { signal: controller.signal },
        );
        this.items = result.items;
        this.pagination = result.pagination;
        this.facets = result.facets;
        this.$emit("loaded", { assets: result.pagination.total });
      } catch (error) {
        if (!controller.signal.aborted)
          this.error =
            error?.payload?.message || this.$t("admin.assets.loadError");
      } finally {
        if (this.controller === controller) this.loading = false;
      }
    },
    async loadCollections() {
      try {
        this.collections = await adminApiClient.mediaCollections();
      } catch (_error) {
        this.collections = [];
      }
    },
    scheduleSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.pagination.page = 1;
        this.load();
      }, 300);
    },
    scheduleAudioLibrarySearch() {
      clearTimeout(this.audioLibrarySearchTimer);
      this.audioLibrarySearchTimer = setTimeout(() => {
        this.audioLibraryPagination.page = 1;
        this.loadAudioLibraries();
      }, 250);
    },
    async loadAudioLibraries() {
      if (this.filters.category !== "audio") return;
      this.audioLibraryController?.abort();
      const controller = new AbortController();
      this.audioLibraryController = controller;
      this.audioLibraryLoading = true;
      try {
        const result = await adminApiClient.mediaAudioLibraries(
          {
            q: this.audioLibrarySearch,
            page: this.audioLibraryPagination.page,
            perPage: this.audioLibraryPagination.perPage,
          },
          { signal: controller.signal },
        );
        this.audioLibraryResults = result.items;
        this.audioLibraryPagination = result.pagination;
      } catch (error) {
        if (!controller.signal.aborted) {
          this.error =
            error?.payload?.message || this.$t("admin.assets.loadError");
        }
      } finally {
        if (this.audioLibraryController === controller) {
          this.audioLibraryLoading = false;
        }
      }
    },
    filterChanged() {
      this.pagination.page = 1;
      this.load();
    },
    clearFilters() {
      this.search = "";
      this.filters = {
        category: "",
        resourceType: "",
        provider: "",
        format: "",
        visibility: "",
        assignment: "",
        status: "",
        ownerId: "",
        campaignId: "",
        collectionId: "",
        audioLibraryId: "",
        sort: "createdAt",
        direction: "desc",
      };
      this.resetAudioLibraryPicker();
      this.filterChanged();
    },
    setCategory(value) {
      const previousCategory = this.filters.category;
      this.filters.category = value;
      if (value === "audio") {
        this.filters.resourceType = "audio";
        this.loadAudioLibraries();
      }
      if (value !== "audio") {
        this.filters.audioLibraryId = "";
        this.resetAudioLibraryPicker();
        if (
          previousCategory === "audio" &&
          this.filters.resourceType === "audio"
        ) {
          this.filters.resourceType = "";
        }
      }
      this.leftOpen = false;
      this.filterChanged();
    },
    setCollection(value) {
      this.filters.collectionId = value;
      this.leftOpen = false;
      this.filterChanged();
    },
    setAudioLibrary(value) {
      const libraryId = Number(value) || "";
      this.filters.audioLibraryId = libraryId;
      this.audioLibrarySelected = libraryId
        ? this.audioLibraryOptions.find(
            (library) => library.id === libraryId,
          ) || null
        : null;
      this.filters.resourceType = "audio";
      this.leftOpen = false;
      this.filterChanged();
    },
    goAudioLibraryPage(page) {
      if (page < 1 || page > this.audioLibraryPagination.pages) return;
      this.audioLibraryPagination.page = page;
      this.loadAudioLibraries();
    },
    resetAudioLibraryPicker() {
      this.audioLibraryController?.abort();
      clearTimeout(this.audioLibrarySearchTimer);
      this.audioLibrarySearch = "";
      this.audioLibraryResults = [];
      this.audioLibrarySelected = null;
      this.audioLibraryPagination = {
        page: 1,
        perPage: 25,
        total: 0,
        pages: 1,
      };
    },
    facetCount(group, value) {
      return (
        this.facets[group]?.find((item) => item.value === value)?.count || 0
      );
    },
    goPage(page) {
      this.pagination.page = page;
      this.load();
    },
    toggleSelection(id) {
      const next = new Set(this.selection);
      next.has(id) ? next.delete(id) : next.add(id);
      this.selection = next;
    },
    clearSelection() {
      this.selection = new Set();
    },
    async openAsset(id) {
      this.selectedId = id;
      this.inspectorOpen = true;
      this.detailLoading = true;
      this.error = "";
      try {
        this.detail = await adminApiClient.mediaAsset(id);
        this.resetDraft();
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.loadError");
      } finally {
        this.detailLoading = false;
      }
    },
    resetDraft() {
      if (!this.detail) return;
      this.draft = {
        name: this.detail.name,
        description: this.detail.description || "",
        tags: this.detail.tags.join(", "),
        category: this.detail.category,
        visibility: this.detail.visibility,
        campaignId: this.detail.campaignId,
        metadata: JSON.stringify(this.detail.customMetadata || {}, null, 2),
      };
      this.audioTargets = Object.fromEntries(
        (this.detail.audioTracks || []).map((track) => [track.id, ""]),
      );
    },
    async saveDetail() {
      let customMetadata;
      try {
        customMetadata = JSON.parse(this.draft.metadata || "{}");
      } catch (_error) {
        this.error = this.$t("admin.assets.invalidMetadata");
        return;
      }
      this.busy = "save";
      this.error = "";
      try {
        this.detail = await adminApiClient.updateMediaAsset(this.detail.id, {
          revision: this.detail.revision,
          name: this.draft.name,
          description: this.draft.description,
          tags: this.tagList(this.draft.tags),
          category: this.draft.category,
          visibility: this.draft.visibility,
          campaignId:
            this.draft.visibility === "campaign"
              ? Number(this.draft.campaignId)
              : null,
          customMetadata,
        });
        this.resetDraft();
        await this.load();
        this.notice = this.$t("admin.assets.saved");
      } catch (error) {
        this.error =
          error?.status === 409
            ? this.$t("admin.assets.conflict")
            : error?.payload?.message || this.$t("admin.assets.saveError");
      } finally {
        this.busy = "";
      }
    },
    async upload(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file) return;
      this.busy = "upload";
      this.error = "";
      try {
        const asset = await adminApiClient.uploadMediaAsset(file, {
          category: this.filters.category || "other",
          visibility: "private",
        });
        await this.refresh();
        await this.openAsset(asset.id);
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.uploadError");
      } finally {
        this.busy = "";
      }
    },
    async registerExternal() {
      const sourceUrl = window.prompt(
        this.$t("admin.assets.externalUrlPrompt"),
      );
      if (!sourceUrl?.trim()) return;
      const name = window.prompt(this.$t("admin.assets.externalNamePrompt"));
      this.busy = "external";
      this.error = "";
      try {
        const asset = await adminApiClient.createExternalMediaAsset({
          sourceUrl: sourceUrl.trim(),
          name: name?.trim() || undefined,
          category: "maps",
        });
        await this.refresh();
        await this.openAsset(asset.id);
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.externalUrlError");
      } finally {
        this.busy = "";
      }
    },
    async replaceAsset(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file || !this.detail) return;
      this.busy = "replace";
      this.error = "";
      try {
        this.detail = await adminApiClient.replaceMediaAsset(
          this.detail.id,
          file,
        );
        this.resetDraft();
        await this.load();
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.replaceError");
      } finally {
        this.busy = "";
      }
    },
    async publishAudioTrack(track, mode) {
      const targetLibraryId = Number(this.audioTargets[track.id]);
      if (!targetLibraryId) {
        this.error = this.$t("admin.assets.chooseGlobalAudioLibrary");
        return;
      }
      if (
        mode === "move" &&
        !window.confirm(
          this.$t("admin.assets.moveToGlobalConfirm", { name: track.name }),
        )
      )
        return;
      this.busy = `audio-publish:${track.id}`;
      this.error = "";
      try {
        await adminApiClient.publishPersonalAudioTrack(track.id, {
          targetLibraryId,
          mode,
        });
        this.notice = this.$t(
          mode === "copy"
            ? "admin.assets.audioCopiedToGlobal"
            : "admin.assets.audioMovedToGlobal",
        );
        await this.load();
        await this.openAsset(this.detail.id);
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.publishAudioError");
      } finally {
        this.busy = "";
      }
    },
    async deleteAsset() {
      if (
        !this.detail ||
        !window.confirm(
          this.$t("admin.assets.deleteConfirm", { name: this.detail.name }),
        )
      )
        return;
      this.busy = "delete";
      this.error = "";
      try {
        await adminApiClient.deleteMediaAsset(this.detail.id);
        this.detail = null;
        this.selectedId = null;
        this.inspectorOpen = false;
        await this.refresh();
      } catch (error) {
        this.error =
          error?.code === "asset_in_use"
            ? this.$t("admin.assets.inUse")
            : error?.payload?.message || this.$t("admin.assets.deleteError");
        if (error?.payload?.errors?.relations)
          this.detail.relations = error.payload.errors.relations;
      } finally {
        this.busy = "";
      }
    },
    async retryPurge() {
      if (!this.detail) return;
      this.busy = "purge";
      try {
        await adminApiClient.retryMediaPurge(this.detail.id);
        this.detail = null;
        await this.refresh();
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.deleteError");
      } finally {
        this.busy = "";
      }
    },
    async applyBulk() {
      const ids = [...this.selection];
      if (!ids.length) return;
      let metadata;
      if (this.bulk.metadata.trim()) {
        try {
          metadata = JSON.parse(this.bulk.metadata);
          if (
            !metadata ||
            Array.isArray(metadata) ||
            typeof metadata !== "object"
          )
            throw new TypeError("metadata_object_required");
        } catch (_error) {
          this.error = this.$t("admin.assets.invalidMetadata");
          return;
        }
      }
      this.busy = "bulk";
      this.error = "";
      try {
        const body = { ids };
        if (this.bulk.category) body.category = this.bulk.category;
        if (this.bulk.tags.trim()) {
          body.tags = this.tagList(this.bulk.tags);
          body.tagMode = this.bulk.tagMode;
        }
        if (this.bulk.visibility) {
          body.visibility = this.bulk.visibility;
          body.campaignId =
            this.bulk.visibility === "campaign"
              ? Number(this.bulk.campaignId)
              : null;
        }
        if (metadata) body.metadata = metadata;
        const result =
          Object.keys(body).length > 1
            ? await adminApiClient.bulkUpdateMediaAssets(body)
            : { results: [] };
        if (this.bulk.collectionId)
          await adminApiClient.changeMediaCollectionAssets(
            this.bulk.collectionId,
            ids,
            this.bulk.collectionMode === "add",
          );
        const failures = result.results?.filter((item) => !item.ok) || [];
        this.notice = failures.length
          ? this.$t("admin.assets.partial", { count: failures.length })
          : this.$t("admin.assets.bulkDone");
        await this.refresh();
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.bulkError");
      } finally {
        this.busy = "";
      }
    },
    async createCollection() {
      const name = window.prompt(this.$t("admin.assets.collectionName"));
      if (!name?.trim()) return;
      try {
        await adminApiClient.createMediaCollection({ name: name.trim() });
        await this.loadCollections();
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.collectionError");
      }
    },
    async createCharacterSet() {
      this.busy = "set";
      try {
        await adminApiClient.createCharacterAssetSet({
          name: this.characterSet.name,
          slots: Object.fromEntries(
            Object.entries(this.characterSet.slots).map(([key, value]) => [
              key,
              Number(value),
            ]),
          ),
        });
        this.notice = this.$t("admin.assets.setCreated");
      } catch (error) {
        this.error =
          error?.payload?.message || this.$t("admin.assets.setError");
      } finally {
        this.busy = "";
      }
    },
    tagList(value) {
      return [
        ...new Set(
          String(value || "")
            .split(",")
            .map((tag) => tag.trim().toLowerCase())
            .filter(Boolean),
        ),
      ];
    },
    typeGlyph(type) {
      return (
        { image: "▧", audio: "♫", video: "▶", document: "PDF", raw: "◇" }[
          type
        ] || "◇"
      );
    },
    formatBytes(value) {
      const bytes = Number(value || 0);
      if (!bytes) return "—";
      const units = ["B", "KB", "MB", "GB"];
      const index = Math.min(
        units.length - 1,
        Math.floor(Math.log(bytes) / Math.log(1024)),
      );
      return `${(bytes / 1024 ** index).toFixed(index ? 1 : 0)} ${units[index]}`;
    },
  },
};
</script>
