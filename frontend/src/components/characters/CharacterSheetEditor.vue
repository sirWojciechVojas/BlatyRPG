<template>
  <section v-if="character && draft" class="character-sheet">
    <header class="character-sheet-header">
      <img :src="portrait" :alt="character.name" />
      <div class="character-sheet-title">
        <p class="character-eyebrow">{{ $t("characters.sheet.eyebrow") }}</p>
        <input
          v-model.trim="draft.name"
          :aria-label="$t('characters.fields.name')"
          :disabled="!canEdit"
          maxlength="150"
        />
        <div class="character-sheet-badges">
          <span>#{{ character.id }}</span>
          <span>{{ character.primaryCurrencyCode || "—" }}</span>
          <span
            >{{ $t("characters.fields.balance") }}: {{ character.brass }}</span
          >
          <span v-if="!canEdit">{{ $t("characters.sheet.readOnly") }}</span>
        </div>
      </div>
    </header>

    <p v-if="error" class="character-sheet-error" role="alert">{{ error }}</p>

    <form class="character-sheet-form" @submit.prevent="save">
      <fieldset :disabled="!canEdit || saving">
        <legend>{{ $t("characters.sections.identity") }}</legend>
        <div class="character-field-grid">
          <label v-for="key in detailKeys" :key="key">
            <span>{{ labelFor(key) }}</span>
            <textarea
              v-if="isLongField(key, draft.data.details[key])"
              v-model="draft.data.details[key]"
              rows="4"
            />
            <input
              v-else
              v-model="draft.data.details[key]"
              :type="inputType(draft.data.details[key])"
            />
          </label>
          <label>
            <span>{{ $t("characters.fields.avatar") }}</span>
            <input v-model.trim="draft.avatarUrl" type="text" maxlength="255" />
          </label>
        </div>
      </fieldset>

      <fieldset :disabled="!canEdit || saving">
        <legend>{{ $t("characters.sections.attributes") }}</legend>
        <div v-if="attributeKeys.length" class="character-attribute-grid">
          <label v-for="key in attributeKeys" :key="key">
            <span>{{ key.toUpperCase() }}</span>
            <input
              v-model.number="draft.data.attributes.actual[key]"
              type="number"
            />
          </label>
        </div>
        <p v-else class="character-muted">
          {{ $t("characters.empty.attributes") }}
        </p>
      </fieldset>

      <div class="character-text-columns">
        <fieldset :disabled="!canEdit || saving">
          <legend>{{ $t("characters.sections.skills") }}</legend>
          <textarea v-model="skillsText" rows="8" />
          <small>{{ $t("characters.fields.onePerLine") }}</small>
        </fieldset>
        <fieldset :disabled="!canEdit || saving">
          <legend>{{ $t("characters.sections.talents") }}</legend>
          <textarea v-model="talentsText" rows="8" />
          <small>{{ $t("characters.fields.onePerLine") }}</small>
        </fieldset>
      </div>

      <details class="character-json-editor">
        <summary>{{ $t("characters.sections.advanced") }}</summary>
        <p>{{ $t("characters.advanced.description") }}</p>
        <textarea
          v-model="jsonText"
          :disabled="!canEdit || saving"
          rows="14"
          spellcheck="false"
        />
        <p v-if="jsonError" class="character-sheet-error" role="alert">
          {{ jsonError }}
        </p>
        <div class="character-inline-actions">
          <button
            type="button"
            :disabled="!canEdit || saving"
            @click="applyJson"
          >
            {{ $t("characters.actions.applyJson") }}
          </button>
          <button
            type="button"
            :disabled="!canEdit || saving"
            @click="refreshJson"
          >
            {{ $t("characters.actions.refreshJson") }}
          </button>
        </div>
      </details>

      <footer v-if="canEdit" class="character-sheet-actions">
        <button
          v-if="canDelete"
          class="character-danger-action"
          type="button"
          :disabled="saving"
          @click="$emit('delete')"
        >
          {{ $t("characters.actions.delete") }}
        </button>
        <button type="button" :disabled="saving" @click="reset">
          {{ $t("characters.actions.discard") }}
        </button>
        <button
          class="character-primary-action"
          type="submit"
          :disabled="saving"
        >
          {{
            saving
              ? $t("characters.loading.saving")
              : $t("characters.actions.save")
          }}
        </button>
      </footer>
    </form>
  </section>
  <section v-else class="character-sheet-placeholder">
    <p>{{ $t("characters.empty.selection") }}</p>
  </section>
</template>

<script src="./options/CharacterSheetEditor.options.js"></script>
<style src="./styles/CharacterSheetEditor.css"></style>
