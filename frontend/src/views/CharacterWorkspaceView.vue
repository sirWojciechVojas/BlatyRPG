<template>
  <div class="character-workspace">
    <header class="character-workspace-topbar">
      <router-link :to="{ name: 'home' }" class="character-back-link">
        ← {{ $t("characters.actions.dashboard") }}
      </router-link>
      <div class="character-workspace-heading">
        <p class="character-eyebrow">
          {{ $t("characters.workspace.eyebrow") }}
        </p>
        <h1>{{ $t("characters.workspace.title") }}</h1>
      </div>
      <div class="character-workspace-actions">
        <button
          v-if="canCreate"
          type="button"
          :disabled="saving || creating || deleting"
          @click="openCreate"
        >
          {{ $t("characters.actions.new") }}
        </button>
        <button type="button" :disabled="loading" @click="loadCharacters">
          {{ $t("characters.actions.refresh") }}
        </button>
      </div>
    </header>

    <p v-if="notice" class="character-workspace-notice" role="status">
      {{ notice }}
    </p>
    <p v-if="loadError" class="character-workspace-error" role="alert">
      {{ loadError }}
      <button type="button" @click="loadCharacters">
        {{ $t("characters.actions.retry") }}
      </button>
    </p>

    <main class="character-workspace-grid">
      <CharacterList
        :characters="characters"
        :selected-id="selectedId"
        :loading="loading"
        @select="selectCharacter"
      />
      <div
        v-if="loadingSheet"
        class="character-sheet-loading"
        aria-live="polite"
      >
        {{ $t("characters.loading.sheet") }}
      </div>
      <CharacterSheetEditor
        v-else
        :character="selectedCharacter"
        :saving="saving || deleting"
        :error="saveError"
        @save="saveCharacter"
        @delete="deleteCharacter"
      />
    </main>
    <CharacterCreateDialog
      v-if="showingCreate"
      :games="games"
      :busy="creating"
      :error="createError"
      @close="showingCreate = false"
      @create="createCharacter"
    />
  </div>
</template>

<script src="./options/CharacterWorkspaceView.options.js"></script>
<style src="./styles/CharacterWorkspaceView.css"></style>
