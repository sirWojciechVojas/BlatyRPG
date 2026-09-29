<template>
  <section
    class="compendium-workspace"
    :class="{
      'compendium-workspace--compact': compact,
      'compendium-workspace--reading': compact && compactReading,
    }"
  >
    <header class="compendium-workspace__topbar">
      <div class="compendium-workspace__identity">
        <span>{{
          editorial
            ? $t("vtt.table.compendium.editorial")
            : $t("vtt.table.compendium.library")
        }}</span>
        <strong>{{
          overview.world?.name || $t("vtt.table.compendium.titleModule")
        }}</strong>
      </div>
      <nav :aria-label="$t('vtt.table.compendium.views')">
        <button
          type="button"
          :class="{ active: mode === 'articles' }"
          @click="setMode('articles')"
        >
          {{ $t("vtt.table.compendium.articles") }}
        </button>
        <button
          type="button"
          :class="{ active: mode === 'timeline' }"
          @click="setMode('timeline')"
        >
          {{ $t("vtt.table.compendium.timeline") }}
        </button>
        <button
          v-if="navigationCanSeeGm"
          type="button"
          :class="{ active: mode === 'bestiary' }"
          :disabled="!overview.capabilities"
          @click="setMode('bestiary')"
        >
          {{ $t("vtt.table.compendium.bestiary") }}
          <small class="compendium-workspace__view-count">{{
            departmentCount("bestiary")
          }}</small>
        </button>
        <button
          v-if="navigationCanSeeGm"
          type="button"
          :class="{ active: mode === 'npcs' }"
          :disabled="!overview.capabilities"
          @click="setMode('npcs')"
        >
          {{ $t("vtt.table.compendium.npcs") }}
          <small class="compendium-workspace__view-count">{{
            departmentCount("characters")
          }}</small>
        </button>
        <button
          v-if="canManageSchema"
          type="button"
          :class="{ active: mode === 'types' }"
          @click="setMode('types')"
        >
          {{ $t("vtt.table.compendium.types") }}
        </button>
        <button
          v-if="canManageSchema"
          type="button"
          :class="{ active: mode === 'settings' }"
          @click="setMode('settings')"
        >
          {{ $t("vtt.table.compendium.settings") }}
        </button>
      </nav>
      <div class="compendium-workspace__summary">
        <span
          >{{ overview.corpus?.count ?? entries.length }}
          {{ $t("vtt.table.compendium.entries") }}</span
        >
        <button
          type="button"
          :title="$t('vtt.table.compendium.refresh')"
          :aria-label="$t('vtt.table.compendium.refresh')"
          @click="refresh"
        >
          ↻
        </button>
      </div>
    </header>

    <div
      class="compendium-workspace__loadbar"
      :class="{ 'compendium-workspace__loadbar--active': isLoading }"
      role="progressbar"
      :aria-label="$t('vtt.table.compendium.loading')"
      :aria-hidden="isLoading ? undefined : 'true'"
    >
      <span />
    </div>

    <div v-if="errorMessage" class="compendium-workspace__error" role="alert">
      <span>{{ errorMessage }}</span>
      <button type="button" @click="refresh">
        {{ $t("vtt.table.compendium.retry") }}
      </button>
    </div>

    <template v-if="mode === 'types'">
      <section class="compendium-schema">
        <header>
          <h2>{{ $t("vtt.table.compendium.types") }}</h2>
          <button type="button" @click="newType">
            {{ $t("vtt.table.compendium.addType") }}
          </button>
        </header>
        <article v-for="type in overview.types" :key="type.id">
          <strong>{{ type.name }}</strong
          ><code>{{ type.code }}</code
          ><span
            >{{ type.fields.length }}
            {{ $t("vtt.table.compendium.fields") }}</span
          >
          <button v-if="!type.builtin" type="button" @click="editType(type)">
            {{ $t("vtt.table.compendium.edit") }}
          </button>
          <button v-if="!type.builtin" type="button" @click="deleteType(type)">
            {{ $t("vtt.table.compendium.delete") }}
          </button>
        </article>
        <form v-if="typeDraft" @submit.prevent="saveType">
          <input
            v-model.trim="typeDraft.name"
            required
            maxlength="100"
            :placeholder="$t('vtt.table.compendium.typeName')"
          />
          <input
            v-model.trim="typeDraft.code"
            :disabled="Boolean(typeDraft.id)"
            required
            maxlength="64"
            placeholder="code"
          />
          <textarea
            v-model="typeDraft.fieldsText"
            rows="10"
            :placeholder="$t('vtt.table.compendium.fieldsJson')"
          />
          <button type="submit">{{ $t("vtt.table.compendium.save") }}</button
          ><button type="button" @click="typeDraft = null">
            {{ $t("vtt.table.compendium.cancel") }}
          </button>
        </form>
      </section>
    </template>

    <template v-else-if="mode === 'settings'">
      <section class="compendium-settings">
        <div>
          <h2>{{ $t("vtt.table.compendium.calendar") }}</h2>
          <form @submit.prevent="saveCalendar">
            <input v-model.trim="calendarDraft.name" required />
            <label
              >{{ $t("vtt.table.compendium.monthsJson")
              }}<textarea
                v-model="calendarDraft.monthsText"
                rows="8"
                :disabled="overview.calendar?.structureLocked"
              />
            </label>
            <label
              >{{ $t("vtt.table.compendium.erasJson")
              }}<textarea
                v-model="calendarDraft.erasText"
                rows="8"
                :disabled="overview.calendar?.structureLocked"
              />
            </label>
            <p v-if="overview.calendar?.structureLocked">
              {{ $t("vtt.table.compendium.calendarLocked") }}
            </p>
            <button type="submit">{{ $t("vtt.table.compendium.save") }}</button>
          </form>
        </div>
        <div>
          <h2>{{ $t("vtt.table.compendium.tags") }}</h2>
          <form @submit.prevent="addTag">
            <input v-model.trim="newTagName" required maxlength="80" /><input
              v-model="newTagColor"
              type="color"
            /><button>{{ $t("vtt.table.compendium.add") }}</button>
          </form>
          <p v-for="tag in overview.tags" :key="tag.id">
            <span
              class="compendium-tag-dot"
              :style="{ background: tag.color }"
            />{{ tag.name }}
            <button type="button" @click="deleteTag(tag)">×</button>
          </p>
        </div>
        <div v-if="overview.capabilities?.canManageEditors">
          <h2>{{ $t("vtt.table.compendium.editors") }}</h2>
          <form @submit.prevent="addEditor">
            <input
              v-model.trim="editorIdentity"
              required
              :placeholder="$t('vtt.table.compendium.userIdentity')"
            /><button>{{ $t("vtt.table.compendium.add") }}</button>
          </form>
          <p v-for="editor in overview.editors" :key="editor.userId">
            {{ editor.username }} ({{ editor.email }})
            <button type="button" @click="removeEditor(editor)">×</button>
          </p>
        </div>
        <div v-if="overview.capabilities?.canAssignOwner">
          <h2>{{ $t("vtt.table.compendium.owner") }}</h2>
          <form @submit.prevent="assignOwner">
            <input
              v-model="ownerUserId"
              type="number"
              min="1"
              :placeholder="$t('vtt.table.compendium.ownerId')"
            />
            <button>{{ $t("vtt.table.compendium.assign") }}</button>
          </form>
        </div>
      </section>
    </template>

    <div v-else class="compendium-workspace__body">
      <aside class="compendium-workspace__navigation">
        <nav
          v-if="!compact && overview.departments?.length"
          class="compendium-workspace__departments"
          :aria-label="$t('vtt.table.compendium.departments')"
        >
          <button
            type="button"
            :class="{ active: !filters.department }"
            @click="filterByDepartment('')"
          >
            {{ $t("vtt.table.compendium.allDepartments") }}
          </button>
          <button
            v-for="department in visibleDepartments"
            :key="department.key"
            type="button"
            :class="{ active: filters.department === department.key }"
            :disabled="department.count === 0"
            @click="filterByDepartment(department.key)"
          >
            <span>{{ departmentLabel(department.key) }}</span
            ><small>{{ department.count }}</small>
          </button>
        </nav>
        <div
          v-if="mode === 'npcs'"
          class="compendium-workspace__npc-filters"
          :aria-label="$t('vtt.table.compendium.npcKinds')"
        >
          <button
            type="button"
            :class="{ active: !filters.npcKind }"
            @click="setNpcKind('')"
          >
            <span>{{ $t("vtt.table.compendium.allNpcs") }}</span
            ><small>{{ npcCount("all") }}</small>
          </button>
          <button
            type="button"
            :class="{ active: filters.npcKind === 'named' }"
            @click="setNpcKind('named')"
          >
            <span>{{ $t("vtt.table.compendium.namedNpcs") }}</span
            ><small>{{ npcCount("named") }}</small>
          </button>
          <button
            type="button"
            :class="{ active: filters.npcKind === 'generic' }"
            @click="setNpcKind('generic')"
          >
            <span>{{ $t("vtt.table.compendium.genericNpcs") }}</span
            ><small>{{ npcCount("generic") }}</small>
          </button>
        </div>
        <div
          class="compendium-workspace__filters"
          :class="{ 'compendium-workspace__filters--searching': filters.q }"
        >
          <div class="compendium-workspace__search">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="11" cy="11" r="6.5" />
              <path d="m16 16 4 4" />
            </svg>
            <input
              v-model="filters.q"
              type="search"
              autocomplete="off"
              spellcheck="false"
              :aria-label="$t('vtt.table.compendium.search')"
              :placeholder="$t('vtt.table.compendium.search')"
              @input="queueLoad"
              @keydown.esc="clearSearch"
            />
            <button
              v-if="filters.q"
              type="button"
              class="compendium-workspace__search-clear"
              :aria-label="$t('vtt.table.compendium.clearSearch')"
              @click="clearSearch"
            >
              ×
            </button>
          </div>
          <div class="compendium-workspace__filter-actions">
            <button
              type="button"
              class="compendium-workspace__filter-toggle"
              :class="{ active: filtersOpen || activeFilterCount }"
              :aria-expanded="filtersOpen"
              @click="filtersOpen = !filtersOpen"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6" />
              </svg>
              <span>{{ $t("vtt.table.compendium.filters") }}</span>
              <small v-if="activeFilterCount">{{ activeFilterCount }}</small>
              <span
                class="compendium-workspace__filter-chevron"
                aria-hidden="true"
                >⌄</span
              >
            </button>
            <button
              v-if="activeFilterCount"
              type="button"
              class="compendium-workspace__filter-reset"
              @click="clearAdvancedFilters"
            >
              {{ $t("vtt.table.compendium.clear") }}
            </button>
          </div>
          <div v-if="filtersOpen" class="compendium-workspace__filter-panel">
            <div
              v-if="!editorial && overview.activity"
              class="compendium-workspace__activity-filters"
            >
              <button
                type="button"
                :class="{ active: filters.favorites }"
                @click="toggleActivityFilter('favorites')"
              >
                ☆ {{ $t("vtt.table.compendium.favorites") }} ·
                {{ overview.activity.favorites || 0 }}
              </button>
              <button
                type="button"
                :class="{ active: filters.recent }"
                @click="toggleActivityFilter('recent')"
              >
                ↶ {{ $t("vtt.table.compendium.recent") }} ·
                {{ overview.activity.recent || 0 }}
              </button>
            </div>
            <select
              v-model="filters.type"
              :aria-label="$t('vtt.table.compendium.allTypes')"
              @change="loadEntries"
            >
              <option value="">
                {{ $t("vtt.table.compendium.allTypes") }}
              </option>
              <option
                v-for="type in visibleTypes"
                :key="type.id"
                :value="type.id"
              >
                {{ type.name }}
              </option>
            </select>
            <select
              v-if="overview.categories?.length"
              v-model="filters.category"
              :aria-label="$t('vtt.table.compendium.allCategories')"
              @change="loadEntries"
            >
              <option value="">
                {{ $t("vtt.table.compendium.allCategories") }}
              </option>
              <option
                v-for="category in overview.categories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }} ({{ category.count }})
              </option>
            </select>
            <select
              v-if="overview.sources?.length"
              v-model="filters.source"
              :aria-label="$t('vtt.table.compendium.allSources')"
              @change="loadEntries"
            >
              <option value="">
                {{ $t("vtt.table.compendium.allSources") }}
              </option>
              <option
                v-for="source in overview.sources"
                :key="source.id"
                :value="source.id"
              >
                {{ source.name }} ({{ source.count }})
              </option>
            </select>
            <select
              v-model="filters.tag"
              :aria-label="$t('vtt.table.compendium.allTags')"
              @change="loadEntries"
            >
              <option value="">{{ $t("vtt.table.compendium.allTags") }}</option>
              <option
                v-for="tag in overview.tags"
                :key="tag.id"
                :value="tag.id"
              >
                {{ tag.name }}
              </option>
            </select>
            <select
              v-if="editorial"
              v-model="filters.status"
              :aria-label="$t('vtt.table.compendium.allStatuses')"
              @change="loadEntries"
            >
              <option value="active">
                {{ $t("vtt.table.compendium.active") }}
              </option>
              <option value="archived">
                {{ $t("vtt.table.compendium.archived") }}
              </option>
              <option value="all">
                {{ $t("vtt.table.compendium.allStatuses") }}
              </option>
            </select>
          </div>
        </div>
        <div class="compendium-workspace__list-header">
          <strong>{{ $t("vtt.table.compendium.results") }}</strong>
        </div>
        <button
          v-if="editorial"
          type="button"
          class="compendium-workspace__new"
          @click="createNew"
        >
          + {{ $t("vtt.table.compendium.newEntry") }}
        </button>
        <ol
          ref="entryList"
          class="compendium-workspace__list"
          :class="{
            'compendium-workspace__list--timeline': mode === 'timeline',
          }"
          :aria-busy="loadingMore"
          @keydown.up.prevent="selectRelative(-1)"
          @keydown.down.prevent="selectRelative(1)"
          @scroll.passive="handleListScroll"
          @wheel.passive="handleListScrollIntent"
        >
          <li
            v-for="item in entries"
            :key="item.id"
            :style="{ '--compendium-depth': hierarchyDepth(item) }"
          >
            <button
              type="button"
              :class="{ active: Number(item.id) === Number(selectedId) }"
              :aria-current="
                Number(item.id) === Number(selectedId) ? 'page' : undefined
              "
              @click="select(item)"
            >
              <span class="compendium-workspace__item-title">{{
                item.title
              }}</span>
              <span class="compendium-workspace__item-meta">
                {{
                  item.sourceType
                    ? sourceTypeLabel(item.sourceType)
                    : typeLabel(item.typeId)
                }}
                <span
                  v-if="item.startOrdinal !== null"
                  class="compendium-workspace__timeline-date"
                >
                  · {{ chronologyLabel(item) }}</span
                >
                <span
                  v-if="mode === 'npcs' && item.npcKind"
                  class="compendium-workspace__npc-kind"
                  :class="`compendium-workspace__npc-kind--${item.npcKind}`"
                  >{{ npcKindLabel(item.npcKind) }}</span
                >
                <span
                  v-if="item.versionNumber || item.sourceName"
                  class="compendium-workspace__source-badge"
                  :title="item.sourceName"
                  ><template v-if="item.versionNumber"
                    >v{{ item.versionNumber }}</template
                  ><template v-if="item.versionNumber && item.sourceName">
                    · </template
                  >{{ item.sourceName }}</span
                >
              </span>
            </button>
          </li>
        </ol>
        <p
          v-if="!loading && !entries.length"
          class="compendium-workspace__no-results"
        >
          {{ $t("vtt.table.compendium.noResults") }}
        </p>
      </aside>

      <main class="compendium-workspace__content" :aria-busy="entryLoading">
        <nav
          v-if="!compact && navigationStack.length"
          class="compendium-workspace__history-nav"
        >
          <button
            type="button"
            :disabled="navigationIndex < 1"
            @click="navigateHistory(-1)"
          >
            ←
          </button>
          <button
            type="button"
            :disabled="navigationIndex >= navigationStack.length - 1"
            @click="navigateHistory(1)"
          >
            →
          </button>
          <span>{{ selectedEntry?.title || "" }}</span>
        </nav>
        <button
          v-if="compact && compactReading"
          type="button"
          class="compendium-workspace__back"
          @click="showCompactList"
        >
          ← {{ $t("vtt.table.compendium.backToList") }}
        </button>
        <CompendiumEntryEditor
          v-if="editing"
          :key="`editor-${selectedId || 'new'}`"
          :universe-id="universeId"
          :entry="selectedEntry"
          :history="history"
          :entries="entries"
          :types="overview.types || []"
          :tags="overview.tags || []"
          @saved="entrySaved"
          @published="entryPublished"
          @reload="reloadSelected"
          @cancel="editing = false"
        />
        <CompendiumEntryView
          v-else-if="selectedEntry"
          :entry="selectedEntry"
          :types="overview.types || []"
          :campaign-id="campaignId"
          :universe-id="universeId"
          :scene-id="sceneId"
          :token-x="tokenX"
          :token-y="tokenY"
          :editorial="editorial"
          :show-context-links="compact"
          :allow-campaign-reveal="!managesCharacterBestiary"
          :manage-character-knowledge="managesCharacterBestiary"
          @navigate="openById"
          @edit="editing = true"
          @materialized="$emit('materialized', $event)"
          @changed="reloadChanged"
        />
        <div
          v-else-if="entryLoading"
          class="compendium-workspace__entry-loading"
          aria-hidden="true"
        />
        <div v-else class="compendium-workspace__empty">
          {{ $t("vtt.table.compendium.chooseEntry") }}
        </div>
      </main>

      <aside
        v-if="selectedEntry && !compact"
        class="compendium-workspace__properties"
      >
        <header>
          <span>{{ $t("vtt.table.compendium.properties") }}</span>
          <strong>{{ selectedEntry.title }}</strong>
        </header>
        <dl>
          <dt>{{ $t("vtt.table.compendium.type") }}</dt>
          <dd>{{ typeLabel(selectedEntry.typeId) }}</dd>
          <dt>{{ $t("vtt.table.compendium.visibility") }}</dt>
          <dd>{{ visibilityLabel(selectedEntry.visibility) }}</dd>
          <dt>{{ $t("vtt.table.compendium.version") }}</dt>
          <dd>
            {{
              selectedEntry.versionNumber || $t("vtt.table.compendium.draft")
            }}
          </dd>
          <dt v-if="selectedEntry.startOrdinal !== null">
            {{ $t("vtt.table.compendium.date") }}
          </dt>
          <dd v-if="selectedEntry.startOrdinal !== null">
            {{ chronologyLabel(selectedEntry) }}
          </dd>
          <dt v-if="selectedEntry.parentEntryId">
            {{ $t("vtt.table.compendium.parent") }}
          </dt>
          <dd v-if="selectedEntry.parentEntryId">
            <button
              type="button"
              @click="openById(selectedEntry.parentEntryId)"
            >
              {{ entryTitle(selectedEntry.parentEntryId) }}
            </button>
          </dd>
          <template v-if="selectedEntry.sourceBacked">
            <dt>{{ $t("vtt.table.compendium.source") }}</dt>
            <dd>{{ selectedEntry.source?.name }}</dd>
            <dt>{{ $t("vtt.table.compendium.sourceRevision") }}</dt>
            <dd>{{ selectedEntry.source?.revisionId }}</dd>
            <dt>{{ $t("vtt.table.compendium.verification") }}</dt>
            <dd>{{ selectedEntry.verificationStatus }}</dd>
            <dt>{{ $t("vtt.table.compendium.spoilers") }}</dt>
            <dd>{{ selectedEntry.spoilerLevel }}</dd>
          </template>
        </dl>
        <section v-if="selectedEntry.sections?.length">
          <h3>{{ $t("vtt.table.compendium.contents") }}</h3>
          <button
            v-for="section in selectedEntry.sections"
            :key="section.id"
            type="button"
            class="compendium-workspace__property-link"
            @click="scrollToSection(section.id)"
          >
            {{ section.title }}
          </button>
        </section>
        <section v-if="selectedEntry.categories?.length">
          <h3>{{ $t("vtt.table.compendium.categories") }}</h3>
          <div class="compendium-workspace__property-tags">
            <button
              v-for="category in selectedEntry.categories"
              :key="category.id"
              type="button"
              @click="filterByCategory(category.id)"
            >
              {{ category.name }}
            </button>
          </div>
        </section>
        <section v-if="selectedEntry.tags?.length">
          <h3>{{ $t("vtt.table.compendium.tags") }}</h3>
          <div class="compendium-workspace__property-tags">
            <button
              v-for="tag in selectedEntry.tags"
              :key="tag.id"
              type="button"
              @click="filterByTag(tag.id)"
            >
              <i :style="{ background: tag.color || '#64748b' }" />{{
                tag.name
              }}
            </button>
          </div>
        </section>
        <section v-if="selectedEntry.relations?.length">
          <h3>{{ $t("vtt.table.compendium.relations") }}</h3>
          <button
            v-for="relation in selectedEntry.relations"
            :key="`${relation.targetEntryId}:${relation.audience}`"
            type="button"
            class="compendium-workspace__property-link"
            @click="openById(relation.targetEntryId)"
          >
            <small>{{ relation.label }}</small
            >{{ relation.title }}
          </button>
        </section>
        <section v-if="selectedEntry.backlinks?.length">
          <h3>{{ $t("vtt.table.compendium.backlinks") }}</h3>
          <button
            v-for="link in selectedEntry.backlinks"
            :key="link.sourceEntryId"
            type="button"
            class="compendium-workspace__property-link"
            @click="openById(link.sourceEntryId)"
          >
            {{ link.title }}
          </button>
        </section>
        <section v-if="selectedEntry.wikiLinks?.length">
          <h3>{{ $t("vtt.table.compendium.documentLinks") }}</h3>
          <button
            v-for="link in selectedEntry.wikiLinks.slice(0, 30)"
            :key="`${link.sourceId}:${link.targetEntryId}`"
            type="button"
            class="compendium-workspace__property-link"
            @click="openById(link.targetEntryId)"
          >
            {{ link.title }}
          </button>
        </section>
        <section v-if="selectedEntry.wikiBacklinks?.length">
          <h3>{{ $t("vtt.table.compendium.documentBacklinks") }}</h3>
          <button
            v-for="link in selectedEntry.wikiBacklinks.slice(0, 30)"
            :key="link.sourceEntryId"
            type="button"
            class="compendium-workspace__property-link"
            @click="openById(link.sourceEntryId)"
          >
            {{ link.title }}
          </button>
        </section>
        <section v-if="selectedEntry.corpusAssets?.length">
          <h3>{{ $t("vtt.table.compendium.illustrations") }}</h3>
          <p v-for="asset in selectedEntry.corpusAssets" :key="asset.id">
            {{ asset.filename }} ·
            {{
              asset.available
                ? $t("vtt.table.compendium.available")
                : $t("vtt.table.compendium.notDownloaded")
            }}
            <label v-if="editorial" class="compendium-workspace__asset-upload">
              {{
                asset.available
                  ? $t("vtt.table.compendium.replaceIllustration")
                  : $t("vtt.table.compendium.addIllustration")
              }}
              <input
                type="file"
                accept="image/png,image/jpeg,image/webp,image/gif,application/pdf"
                :disabled="assetBusy === asset.id"
                @change="uploadCorpusAsset(asset, $event)"
              />
            </label>
          </p>
        </section>
        <section v-if="selectedEntry.mechanicalProfiles?.length">
          <h3>{{ $t("vtt.table.compendium.mechanics") }}</h3>
          <p
            v-for="profile in selectedEntry.mechanicalProfiles"
            :key="profile.id"
          >
            {{ profile.kind }} · {{ profile.status }} ·
            {{
              profile.usable
                ? $t("vtt.table.compendium.usable")
                : $t("vtt.table.compendium.notUsable")
            }}
          </p>
        </section>
        <button
          v-if="editorial && selectedEntry.status === 'active'"
          type="button"
          @click="archiveSelected"
        >
          {{ $t("vtt.table.compendium.archive") }}
        </button>
        <button
          v-if="editorial && selectedEntry.status === 'archived'"
          type="button"
          @click="restoreSelected"
        >
          {{ $t("vtt.table.compendium.restore") }}
        </button>
      </aside>
    </div>
  </section>
</template>

<script>
import { defineAsyncComponent } from "vue";
import CompendiumEntryView from "./CompendiumEntryView.vue";
import {
  nextCompendiumPage,
  shouldLoadNextCompendiumPage,
} from "./compendiumPagination";
import {
  COMPENDIUM_PAGE_SIZE,
  compendiumApiClient,
} from "@/lib/compendium/compendiumApiClient";

const CompendiumEntryEditor = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "compendium-editor" */ "./CompendiumEntryEditor.vue"
    ),
);

export default {
  name: "CompendiumWorkspace",
  components: { CompendiumEntryEditor, CompendiumEntryView },
  props: {
    campaignId: { type: [Number, String], default: null },
    universeId: { type: [Number, String], default: null },
    initialEntryId: { type: [Number, String], default: null },
    compact: { type: Boolean, default: false },
    initialMode: {
      type: String,
      default: "articles",
      validator: (value) =>
        [
          "articles",
          "timeline",
          "bestiary",
          "npcs",
          "types",
          "settings",
        ].includes(value),
    },
    sceneId: { type: [Number, String], default: null },
    tokenX: { type: Number, default: 0 },
    tokenY: { type: Number, default: 0 },
    canSeeGmHint: { type: Boolean, default: null },
  },
  emits: ["materialized"],
  data: () => ({
    overview: {},
    entries: [],
    selectedEntry: null,
    selectedId: null,
    compactReading: false,
    history: [],
    loading: false,
    loadingMore: false,
    listScrollActivated: false,
    filtersOpen: false,
    entryLoading: false,
    editing: false,
    mode: "articles",
    filters: {
      q: "",
      type: "",
      tag: "",
      category: "",
      source: "",
      department: "",
      npcKind: "",
      favorites: false,
      recent: false,
      status: "active",
    },
    page: 1,
    hasMore: false,
    errorMessage: "",
    searchTimer: null,
    typeDraft: null,
    calendarDraft: { name: "", monthsText: "[]", erasText: "[]" },
    newTagName: "",
    newTagColor: "#8b5cf6",
    editorIdentity: "",
    ownerUserId: "",
    navigationStack: [],
    navigationIndex: -1,
    assetBusy: null,
    initializeRequestSequence: 0,
    entriesRequestSequence: 0,
    entryRequestSequence: 0,
  }),
  computed: {
    isLoading() {
      return this.loading || this.loadingMore || this.entryLoading;
    },
    editorial() {
      return Boolean(this.universeId);
    },
    canManageSchema() {
      return this.editorial && this.overview.capabilities?.canManageSchema;
    },
    canSeeGm() {
      return this.overview.capabilities?.canSeeGm === true;
    },
    navigationCanSeeGm() {
      return this.canSeeGm || this.editorial || this.canSeeGmHint === true;
    },
    managesCharacterBestiary() {
      return (
        this.mode === "bestiary" && this.canSeeGm && Number(this.campaignId) > 0
      );
    },
    creatureType() {
      return (this.overview.types || []).find(
        (type) => type.code === "creature",
      );
    },
    visibleTypes() {
      if (this.mode === "bestiary" && this.creatureType) {
        return [this.creatureType];
      }
      return (this.overview.types || []).filter(
        (type) => this.canSeeGm || type.code !== "creature",
      );
    },
    visibleDepartments() {
      return (this.overview.departments || []).filter(
        (department) => this.canSeeGm || department.key !== "bestiary",
      );
    },
    activeFilterCount() {
      let count = 0;
      if (
        this.filters.type &&
        !(
          this.mode === "bestiary" &&
          Number(this.filters.type) === Number(this.creatureType?.id)
        )
      ) {
        count += 1;
      }
      if (this.filters.category) count += 1;
      if (this.filters.source) count += 1;
      if (this.filters.tag) count += 1;
      if (this.filters.favorites) count += 1;
      if (this.filters.recent) count += 1;
      if (this.editorial && this.filters.status !== "active") count += 1;
      return count;
    },
  },
  created() {
    this.mode = this.initialMode;
    if (this.mode === "bestiary") this.filters.department = "bestiary";
    if (this.mode === "npcs") this.filters.department = "characters";
  },
  async mounted() {
    await this.initialize();
  },
  beforeUnmount() {
    clearTimeout(this.searchTimer);
    this.initializeRequestSequence += 1;
    this.entriesRequestSequence += 1;
    this.entryRequestSequence += 1;
  },
  methods: {
    async initialize() {
      const sequence = ++this.initializeRequestSequence;
      const entriesSequence = ++this.entriesRequestSequence;
      this.loading = true;
      this.errorMessage = "";
      try {
        const directEntry = Number(this.initialEntryId || 0);
        const listMode = ["articles", "timeline", "bestiary", "npcs"].includes(
          this.mode,
        );
        const earlyEntries =
          listMode && this.mode !== "bestiary" ? this.requestEntries(1) : null;
        const applyInitialEntries = (response) => {
          if (
            sequence !== this.initializeRequestSequence ||
            entriesSequence !== this.entriesRequestSequence
          ) {
            return null;
          }
          this.page = 1;
          this.applyEntriesResponse(response, false);
          if (directEntry > 0) void this.openById(directEntry);
          return response;
        };
        const earlyEntriesTask = earlyEntries
          ? earlyEntries.then(applyInitialEntries)
          : Promise.resolve(null);
        const overviewRequest = this.editorial
          ? compendiumApiClient.worldOverview(this.universeId)
          : compendiumApiClient.campaignOverview(this.campaignId);
        const [overview, earlyResponse] = await Promise.all([
          Promise.resolve(overviewRequest),
          earlyEntriesTask,
        ]);
        if (sequence !== this.initializeRequestSequence) return;
        this.overview = {
          ...overview,
          types: (overview.types || []).map((type) => ({
            ...type,
            name: type.builtin
              ? this.$t(`vtt.table.compendium.builtinTypes.${type.code}`)
              : type.name,
          })),
        };
        if (this.mode === "bestiary") {
          this.filters.type = this.creatureType?.id || "";
          this.filters.department = "bestiary";
        } else if (this.mode === "npcs") {
          this.filters.type = "";
          this.filters.department = "characters";
        }
        this.ownerUserId = this.overview.world?.ownerUserId || "";
        this.resetCalendar();
        if (
          listMode &&
          !earlyResponse &&
          entriesSequence === this.entriesRequestSequence
        ) {
          applyInitialEntries(await this.requestEntries(1));
        }
      } catch (error) {
        if (sequence !== this.initializeRequestSequence) return;
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        if (sequence === this.initializeRequestSequence) {
          this.loading = false;
        }
      }
    },
    refresh() {
      compendiumApiClient.clearCache();
      return this.initialize();
    },
    setMode(mode) {
      this.entryRequestSequence += 1;
      this.entryLoading = false;
      this.mode = mode;
      this.filtersOpen = false;
      this.selectedEntry = null;
      this.selectedId = null;
      this.editing = false;
      this.compactReading = false;
      this.filters.type =
        mode === "bestiary" ? this.creatureType?.id || "" : "";
      if (mode === "bestiary") this.filters.department = "bestiary";
      else if (mode === "npcs") this.filters.department = "characters";
      else if (["bestiary", "characters"].includes(this.filters.department))
        this.filters.department = "";
      if (mode !== "npcs") this.filters.npcKind = "";
      if (["articles", "timeline", "bestiary", "npcs"].includes(mode))
        this.loadEntries();
    },
    queueLoad() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.loadEntries(), 120);
    },
    async loadEntries(append = false) {
      const sequence = ++this.entriesRequestSequence;
      const requestedPage = nextCompendiumPage(this.page, append);
      let loaded = false;
      if (!append) this.listScrollActivated = false;
      this.loading = true;
      try {
        const response = await this.requestEntries(requestedPage);
        if (sequence !== this.entriesRequestSequence) return false;
        this.page = requestedPage;
        this.applyEntriesResponse(response, append);
        loaded = true;
        if (!append && this.selectedId) {
          const selectionExists = this.entries.some(
            (item) => Number(item.id) === Number(this.selectedId),
          );
          if (!selectionExists) {
            this.entryRequestSequence += 1;
            this.entryLoading = false;
            this.selectedId = null;
            this.selectedEntry = null;
            this.history = [];
          }
        }
      } catch (error) {
        if (sequence !== this.entriesRequestSequence) return false;
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        if (sequence === this.entriesRequestSequence) {
          this.loading = false;
          if (loaded && !append) {
            this.$nextTick(() => {
              if (this.$refs.entryList) this.$refs.entryList.scrollTop = 0;
            });
          }
        }
      }
      return loaded;
    },
    requestEntries(page) {
      const query = {
        ...this.filters,
        page,
        limit: COMPENDIUM_PAGE_SIZE,
        ...(this.mode === "timeline" ? { dated: 1, sort: "timeline" } : {}),
      };
      if (this.editorial) {
        return compendiumApiClient.worldEntries(this.universeId, query);
      }
      return this.mode === "timeline"
        ? compendiumApiClient.campaignTimeline(this.campaignId, query)
        : compendiumApiClient.campaignEntries(this.campaignId, query);
    },
    applyEntriesResponse(response, append) {
      this.entries = append
        ? [...this.entries, ...(response.items || [])]
        : response.items || [];
      this.hasMore = response.hasMore === true;
    },
    async loadMore() {
      if (!this.hasMore || this.loading || this.loadingMore) return;
      this.loadingMore = true;
      let loaded = false;
      try {
        loaded = await this.loadEntries(true);
      } finally {
        this.loadingMore = false;
        if (loaded && this.listScrollActivated) {
          this.$nextTick(() => this.loadMoreIfNeeded());
        }
      }
    },
    handleListScroll(event) {
      this.listScrollActivated = true;
      this.loadMoreIfNeeded(event);
    },
    handleListScrollIntent(event) {
      if (Number(event?.deltaY || 0) <= 0) return;
      this.listScrollActivated = true;
      this.loadMoreIfNeeded(event);
    },
    loadMoreIfNeeded(event = null) {
      const list = event?.currentTarget || this.$refs.entryList;
      if (!list || !this.hasMore || this.loading || this.loadingMore) {
        return;
      }
      if (shouldLoadNextCompendiumPage(list)) void this.loadMore();
    },
    select(item) {
      this.openById(item.id);
    },
    async openById(target, options = {}) {
      const entryId = Number(
        target && typeof target === "object" ? target.entryId : target,
      );
      if (!entryId) return;
      const sequence = ++this.entryRequestSequence;
      if (Number(this.selectedEntry?.id || 0) !== entryId) {
        this.selectedEntry = null;
        this.history = [];
      }
      this.selectedId = entryId;
      this.editing = false;
      this.entryLoading = true;
      this.errorMessage = "";
      if (this.compact) this.compactReading = true;
      try {
        const response = this.editorial
          ? await compendiumApiClient.worldEntry(this.universeId, entryId)
          : await compendiumApiClient.campaignEntry(this.campaignId, entryId);
        if (sequence !== this.entryRequestSequence) return;
        this.selectedEntry = response.entry;
        this.history = response.history || [];
        if (!this.editorial) {
          void compendiumApiClient
            .recordRead(this.campaignId, entryId)
            .catch(() => undefined);
        }
        if (!options.fromHistory) {
          this.navigationStack = this.navigationStack.slice(
            0,
            this.navigationIndex + 1,
          );
          if (
            Number(this.navigationStack[this.navigationStack.length - 1]) !==
            entryId
          ) {
            this.navigationStack.push(entryId);
          }
          this.navigationIndex = this.navigationStack.length - 1;
        }
        const anchor =
          target && typeof target === "object" ? target.anchor : null;
        if (anchor) this.$nextTick(() => this.scrollToSection(anchor));
      } catch (error) {
        if (sequence !== this.entryRequestSequence) return;
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        if (sequence === this.entryRequestSequence) {
          this.entryLoading = false;
        }
      }
    },
    async reloadSelected() {
      if (this.selectedId) await this.openById(this.selectedId);
      this.editing = true;
    },
    createNew() {
      this.entryRequestSequence += 1;
      this.entryLoading = false;
      this.selectedId = null;
      this.selectedEntry = null;
      this.history = [];
      this.editing = true;
      this.compactReading = true;
    },
    async entrySaved(entry) {
      this.selectedEntry = entry;
      this.selectedId = entry.id;
      this.editing = true;
      await this.loadEntries();
    },
    async entryPublished(response) {
      this.selectedEntry = response.entry;
      this.editing = false;
      await this.loadEntries();
    },
    async archiveSelected() {
      await compendiumApiClient.archive(
        this.universeId,
        this.selectedEntry.id,
        this.selectedEntry.revision,
      );
      this.selectedEntry = null;
      await this.loadEntries();
    },
    async restoreSelected() {
      const response = await compendiumApiClient.restore(
        this.universeId,
        this.selectedEntry.id,
        this.selectedEntry.revision,
      );
      this.selectedEntry = response.entry;
      await this.loadEntries();
    },
    typeLabel(id) {
      return (
        (this.overview.types || []).find(
          (type) => Number(type.id) === Number(id),
        )?.name || ""
      );
    },
    visibilityLabel(value) {
      return value === "gm_only"
        ? this.$t("vtt.table.compendium.gmOnly")
        : this.$t("vtt.table.compendium.players");
    },
    chronologyLabel(entry) {
      const chronology = entry?.chronology;
      if (!chronology?.start) return String(entry?.startOrdinal ?? "");
      const start = this.chronologyDateLabel(
        chronology.start,
        chronology.precision,
      );
      if (chronology.precision !== "range" || !chronology.end) return start;
      return `${start}–${this.chronologyDateLabel(chronology.end, "year")}`;
    },
    chronologyDateLabel(date, precision) {
      const calendar = this.overview.calendar || {};
      const era = (calendar.eras || []).find(
        (candidate) => Number(candidate.id) === Number(date.eraId),
      );
      const suffix = era?.abbreviation ? ` ${era.abbreviation}` : "";
      const year = `${date.year}${suffix}`;
      if (precision === "year" || precision === "range") return year;
      const month = (calendar.months || []).find(
        (candidate) => Number(candidate.id) === Number(date.monthId),
      );
      if (precision === "month")
        return `${month?.name || date.monthId} ${year}`;
      return `${date.day} ${month?.name || date.monthId}, ${year}`;
    },
    entryTitle(id) {
      return (
        this.entries.find((entry) => Number(entry.id) === Number(id))?.title ||
        `#${id}`
      );
    },
    clearSearch() {
      clearTimeout(this.searchTimer);
      if (!this.filters.q) return;
      this.filters.q = "";
      this.loadEntries();
    },
    clearAdvancedFilters() {
      this.filters.tag = "";
      this.filters.category = "";
      this.filters.source = "";
      this.filters.favorites = false;
      this.filters.recent = false;
      this.filters.status = "active";
      this.filters.type =
        this.mode === "bestiary" ? this.creatureType?.id || "" : "";
      this.loadEntries();
    },
    filterByTag(tagId) {
      this.filters.tag = tagId;
      this.loadEntries();
    },
    filterByCategory(categoryId) {
      this.filters.category = categoryId;
      this.loadEntries();
    },
    filterByDepartment(department) {
      this.filters.department = department;
      this.filters.type = "";
      this.filters.npcKind = "";
      this.mode =
        department === "bestiary"
          ? "bestiary"
          : department === "characters"
            ? "npcs"
            : "articles";
      this.loadEntries();
    },
    setNpcKind(kind) {
      this.filters.npcKind = kind;
      this.loadEntries();
    },
    toggleActivityFilter(kind) {
      this.filters[kind] = !this.filters[kind];
      if (kind === "favorites") this.filters.recent = false;
      if (kind === "recent") this.filters.favorites = false;
      this.loadEntries();
    },
    departmentLabel(key) {
      return this.$t(`vtt.table.compendium.department.${key}`);
    },
    departmentCount(key) {
      return Number(
        (this.overview.departments || []).find(
          (department) => department.key === key,
        )?.count || 0,
      );
    },
    npcCount(kind) {
      return Number(this.overview.npcs?.[kind] || 0);
    },
    npcKindLabel(kind) {
      return this.$t(`vtt.table.compendium.npcKind.${kind}`);
    },
    sourceTypeLabel(type) {
      const path = `vtt.table.compendium.sourceTypes.${type}`;
      const translated = this.$t(path);
      return translated === path ? type : translated;
    },
    async reloadChanged(entryId) {
      await this.openById(entryId, { fromHistory: true });
      await this.initialize();
    },
    async uploadCorpusAsset(asset, event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file || !this.universeId) return;
      this.assetBusy = asset.id;
      this.errorMessage = "";
      try {
        await compendiumApiClient.uploadCorpusAsset(
          this.universeId,
          asset.id,
          file,
        );
        await this.openById(this.selectedId, { fromHistory: true });
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        this.assetBusy = null;
      }
    },
    navigateHistory(offset) {
      const next = this.navigationIndex + offset;
      if (next < 0 || next >= this.navigationStack.length) return;
      this.navigationIndex = next;
      this.openById(this.navigationStack[next], { fromHistory: true });
    },
    scrollToSection(sectionId) {
      const escaped =
        typeof CSS !== "undefined" && CSS.escape
          ? CSS.escape(String(sectionId))
          : String(sectionId).replace(/[^a-zA-Z0-9_-]/gu, "");
      const element = this.$el.querySelector(
        `.compendium-source-document__content #${escaped}`,
      );
      element?.scrollIntoView({ behavior: "smooth", block: "start" });
    },
    selectRelative(offset) {
      if (!this.entries.length) return;
      const current = this.entries.findIndex(
        (entry) => Number(entry.id) === Number(this.selectedId),
      );
      const index = Math.min(
        this.entries.length - 1,
        Math.max(0, (current < 0 ? 0 : current) + offset),
      );
      this.openById(this.entries[index].id);
    },
    showCompactList() {
      this.compactReading = false;
      this.editing = false;
    },
    hierarchyDepth(item) {
      let depth = 0;
      let current = item;
      const seen = new Set();
      while (
        current?.parentEntryId &&
        depth < 8 &&
        !seen.has(current.parentEntryId)
      ) {
        seen.add(current.parentEntryId);
        current = this.entries.find(
          (candidate) => Number(candidate.id) === Number(current.parentEntryId),
        );
        depth += 1;
      }
      return depth;
    },
    newType() {
      this.typeDraft = { name: "", code: "", fieldsText: "[]" };
    },
    editType(type) {
      this.typeDraft = {
        ...type,
        fieldsText: JSON.stringify(type.fields || [], null, 2),
      };
    },
    async saveType() {
      try {
        const payload = {
          name: this.typeDraft.name,
          code: this.typeDraft.code,
          fields: JSON.parse(this.typeDraft.fieldsText || "[]"),
        };
        if (this.typeDraft.id)
          await compendiumApiClient.updateType(
            this.universeId,
            this.typeDraft.id,
            payload,
          );
        else await compendiumApiClient.createType(this.universeId, payload);
        this.typeDraft = null;
        await this.initialize();
        this.mode = "types";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async deleteType(type) {
      if (!window.confirm(this.$t("vtt.table.compendium.deleteConfirm")))
        return;
      try {
        await compendiumApiClient.deleteType(this.universeId, type.id);
        await this.initialize();
        this.mode = "types";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    resetCalendar() {
      const calendar = this.overview.calendar || {};
      this.calendarDraft = {
        name: calendar.name || "",
        revision: calendar.revision,
        monthsText: JSON.stringify(calendar.months || [], null, 2),
        erasText: JSON.stringify(calendar.eras || [], null, 2),
      };
    },
    async saveCalendar() {
      try {
        const response = await compendiumApiClient.updateCalendar(
          this.universeId,
          {
            name: this.calendarDraft.name,
            revision: this.calendarDraft.revision,
            months: JSON.parse(this.calendarDraft.monthsText).map(
              ({ name, days }) => ({ name, days }),
            ),
            eras: JSON.parse(this.calendarDraft.erasText).map(
              ({ name, abbreviation, epochOrdinal, direction }) => ({
                name,
                abbreviation,
                epochOrdinal,
                direction,
              }),
            ),
          },
        );
        this.overview.calendar = response.calendar;
        this.resetCalendar();
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async addTag() {
      try {
        const response = await compendiumApiClient.createTag(this.universeId, {
          name: this.newTagName,
          color: this.newTagColor,
        });
        this.overview.tags = [...(this.overview.tags || []), response.tag].sort(
          (left, right) => left.name.localeCompare(right.name),
        );
        this.newTagName = "";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async deleteTag(tag) {
      try {
        await compendiumApiClient.deleteTag(this.universeId, tag.id);
        this.overview.tags = this.overview.tags.filter(
          (item) => item.id !== tag.id,
        );
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async addEditor() {
      try {
        const response = await compendiumApiClient.addEditor(
          this.universeId,
          this.editorIdentity,
        );
        this.overview.editors = response.editors;
        this.editorIdentity = "";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async removeEditor(editor) {
      try {
        const response = await compendiumApiClient.removeEditor(
          this.universeId,
          editor.userId,
        );
        this.overview.editors = response.editors;
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async assignOwner() {
      try {
        this.overview = await compendiumApiClient.assignOwner(
          this.universeId,
          this.ownerUserId ? Number(this.ownerUserId) : null,
        );
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
  },
};
</script>

<style src="./compendium.css"></style>
