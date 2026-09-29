<template>
  <section v-if="character && draft" class="character-sheet">
    <header class="character-sheet-header">
      <AuthenticatedImage
        :src="portrait"
        :alt="character.name"
        draggable="false"
      />
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
      <div class="character-sheet-top-grid">
        <fieldset
          class="character-sheet-section--attributes"
          :disabled="!canEdit || formSaving"
        >
          <legend>{{ $t("characters.sections.attributes") }}</legend>
          <div
            v-if="usesWfrpAttributeTable"
            class="character-attribute-table-scroll"
          >
            <table class="character-attribute-table">
              <tbody v-for="group in attributeGroups" :key="group.id">
                <tr class="character-attribute-table__group">
                  <th :colspan="group.keys.length" scope="rowgroup">
                    {{ $t(group.labelKey) }}
                  </th>
                </tr>
                <tr class="character-attribute-table__labels">
                  <th v-for="key in group.keys" :key="key" scope="col">
                    <label :for="attributeInputId(key)">
                      {{ attributeLabel(key) }}
                    </label>
                  </th>
                </tr>
                <tr class="character-attribute-table__values">
                  <td v-for="key in group.keys" :key="key">
                    <input
                      :id="attributeInputId(key)"
                      v-model.number="draft.data.attributes.actual[key]"
                      type="number"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            v-if="genericAttributeKeys.length"
            class="character-attribute-grid character-attribute-extras"
          >
            <label v-for="key in genericAttributeKeys" :key="key">
              <span>{{ key.toUpperCase() }}</span>
              <input
                v-model.number="draft.data.attributes.actual[key]"
                type="number"
              />
            </label>
          </div>
          <p v-else-if="!attributeKeys.length" class="character-muted">
            {{ $t("characters.empty.attributes") }}
          </p>
        </fieldset>

        <CharacterWalletEditor
          ref="walletEditor"
          :campaign-id="effectiveCampaignId"
          :character-id="character.id"
          :disabled="!canEdit || saving"
        />
      </div>

      <CharacterDevelopmentEditor
        ref="developmentEditor"
        :campaign-id="effectiveCampaignId"
        :character-id="character.id"
        :system-id="character.systemId"
        :skills="draft.data.attributes.skills"
        :talents="draft.data.attributes.talents"
        :disabled="!canEdit || saving"
        @update:skills="draft.data.attributes.skills = $event"
        @update:talents="draft.data.attributes.talents = $event"
        @profession-changed="setProfessionId"
      />

      <fieldset
        class="character-sheet-section--identity"
        :disabled="!canEdit || formSaving"
      >
        <legend>{{ $t("characters.sections.identity") }}</legend>
        <div class="character-field-grid">
          <label
            v-for="key in detailKeys"
            :key="key"
            :class="{ 'character-field--history': key === 'history' }"
          >
            <span>{{ labelFor(key) }}</span>
            <textarea
              v-if="isLongField(key, draft.data.details[key])"
              v-model="draft.data.details[key]"
              rows="4"
            />
            <select
              v-else-if="hasClosedDictionary(key)"
              v-model="draft.data.details[key]"
            >
              <option value="">—</option>
              <option
                v-for="option in dictionaryOptions(key)"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
            <input
              v-else
              v-model="draft.data.details[key]"
              :type="inputType(draft.data.details[key])"
              :list="
                hasSuggestedDictionary(key) ? detailDatalistId(key) : undefined
              "
            />
            <datalist
              v-if="hasSuggestedDictionary(key)"
              :id="detailDatalistId(key)"
            >
              <option
                v-for="option in dictionaryOptions(key)"
                :key="option"
                :value="option"
              />
            </datalist>
          </label>
          <label>
            <span>{{ $t("characters.fields.avatar") }}</span>
            <input v-model.trim="draft.avatarUrl" type="text" maxlength="255" />
          </label>
        </div>
      </fieldset>

      <details class="character-json-editor">
        <summary>{{ $t("characters.sections.advanced") }}</summary>
        <p>{{ $t("characters.advanced.description") }}</p>
        <textarea
          v-model="jsonText"
          :disabled="!canEdit || formSaving"
          rows="14"
          spellcheck="false"
        />
        <p v-if="jsonError" class="character-sheet-error" role="alert">
          {{ jsonError }}
        </p>
        <div class="character-inline-actions">
          <button
            type="button"
            :disabled="!canEdit || formSaving"
            @click="applyJson"
          >
            {{ $t("characters.actions.applyJson") }}
          </button>
          <button
            type="button"
            :disabled="!canEdit || formSaving"
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
          :disabled="formSaving"
          @click="$emit('delete')"
        >
          {{ $t("characters.actions.delete") }}
        </button>
        <button type="button" :disabled="formSaving" @click="reset">
          {{ $t("characters.actions.discard") }}
        </button>
        <button
          class="character-primary-action"
          type="submit"
          :disabled="formSaving"
        >
          {{
            formSaving
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
