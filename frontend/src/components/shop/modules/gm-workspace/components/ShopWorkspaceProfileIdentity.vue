<!-- Panel GM sklepu: edycja tożsamości, właściciela i dostępności profilu sklepu. -->
<template>
  <section class="profile-card" aria-labelledby="profile-identity-title">
    <header class="profile-card__header">
      <span class="profile-section-number">1</span>
      <div>
        <h3 id="profile-identity-title">
          {{ $t("shop.workspace.profile.identity.title") }}
        </h3>
        <p>{{ $t("shop.workspace.profile.identity.description") }}</p>
      </div>
      <button
        type="button"
        class="profile-section-reset"
        @click="resetProfileSection('identity')"
      >
        {{ $t("shop.workspace.profile.resetSection") }}
      </button>
    </header>

    <div class="profile-card__grid profile-card__grid--identity">
      <DenseField
        :label="$t('shop.workspace.fields.name')"
        :hint="$t('shop.workspace.profile.identity.nameHint')"
        :tooltip="$t('shop.workspace.profile.help.signboardName')"
        :error="profileFieldError('signboardName')"
        group
        required
        wide
      >
        <div class="profile-signboard-control">
          <input
            v-model.trim="profileDraft.signboardName"
            type="text"
            maxlength="255"
            autocomplete="off"
            @input="markShopDirty"
          />
          <button
            type="button"
            :disabled="!profileDraft.typeId"
            @click="rollProfileSignboard"
          >
            <span aria-hidden="true">↻</span>
            {{ $t("shop.workspace.profile.randomName.action") }}
          </button>
          <ShopHelpTooltip
            :label="$t('shop.workspace.profile.randomName.action')"
            :text="$t('shop.workspace.profile.randomName.help')"
            align="right"
          />
        </div>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.type')"
        :hint="$t('shop.workspace.profile.identity.typeHint')"
        :tooltip="
          $t('shop.workspace.profile.help.typeId', { value: selectedTypeLabel })
        "
        :error="profileFieldError('typeId')"
        required
        wide
        group
      >
        <select
          v-model="profileDraft.typeId"
          class="form-select form-select-sm gm-combobox gm-combobox--type"
          :aria-label="$t('shop.workspace.fields.type')"
          @change="markShopDirty"
        >
          <option value="">
            {{ $t("shop.workspace.profile.chooseType") }}
          </option>
          <optgroup
            v-for="group in profileTypeGroups"
            :key="group.label"
            :label="group.label"
          >
            <option
              v-for="type in group.options"
              :key="type.id"
              :value="type.id"
            >
              {{ localizedRecordLabel(type, type.id) }}
            </option>
          </optgroup>
        </select>
        <div class="profile-type-advisor">
          <div class="profile-type-advisor__heading">
            <strong>{{
              $t("shop.workspace.profile.typeAdvisor.title")
            }}</strong>
            <small>{{ $t("shop.workspace.profile.typeAdvisor.hint") }}</small>
          </div>
          <div class="profile-type-advisor__choices">
            <button
              v-for="type in profileSuggestedTypes"
              :key="`suggested-${type.id}`"
              type="button"
              :class="{ active: profileDraft.typeId === type.id }"
              :aria-pressed="profileDraft.typeId === type.id"
              :aria-label="`${localizedRecordLabel(type, type.id)}: ${
                type.descriptionPl || type.descriptionEn || ''
              }`"
              @click="selectProfileType(type.id)"
            >
              <span aria-hidden="true">{{ type.suggestionIcon || "•" }}</span>
              {{ localizedRecordLabel(type, type.id) }}
            </button>
          </div>
          <p
            v-if="selectedTypeDescription"
            class="profile-type-advisor__description"
          >
            <strong>{{ selectedTypeLabel }}:</strong>
            {{ selectedTypeDescription }}
          </p>
        </div>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.owner')"
        :hint="$t('shop.workspace.profile.identity.ownerHint')"
        :tooltip="$t('shop.workspace.profile.help.ownerCode')"
        :error="profileFieldError('ownerCode')"
        required
      >
        <select
          v-model="profileDraft.ownerCode"
          class="form-select form-select-sm gm-combobox gm-combobox--actor"
          @change="markShopDirty"
        >
          <option
            v-for="actor in actorOptions"
            :key="actor.ownerCode"
            :value="actor.ownerCode"
          >
            {{ actor.ownerCode }} — {{ actor.name }}
          </option>
          <option
            v-if="!actorOptions.some((actor) => actor.ownerCode === 'NPC')"
            value="NPC"
          >
            NPC — {{ $t("shop.workspace.profile.identity.independentNpc") }}
          </option>
        </select>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.profile.identity.ownerName')"
        :hint="$t('shop.workspace.profile.identity.ownerNameHint')"
        :tooltip="$t('shop.workspace.profile.help.ownerName')"
      >
        <input
          v-model.trim="profileDraft.ownerName"
          type="text"
          maxlength="255"
          @input="markShopDirty"
        />
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.activity')"
        :hint="$t('shop.workspace.profile.identity.activityHint')"
        :tooltip="$t('shop.workspace.profile.help.activity')"
        group
      >
        <label class="profile-activity" :class="{ active: activeDraft }">
          <input v-model="activeDraft" type="checkbox" @change="toggleActive" />
          <span aria-hidden="true"></span>
          <strong>
            {{
              activeDraft
                ? $t("shop.workspace.active")
                : $t("shop.workspace.inactive")
            }}
          </strong>
        </label>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.aliases')"
        :hint="$t('shop.workspace.profile.identity.aliasesHint')"
        :tooltip="$t('shop.workspace.profile.help.aliases')"
        wide
      >
        <textarea
          v-model.trim="profileDraft.signboardAltNamesText"
          rows="2"
          @input="markShopDirty"
        ></textarea>
      </DenseField>
    </div>
  </section>
</template>

<script>
import DenseField from "@/components/shop/common/DenseField.vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceProfileIdentity",
  components: { DenseField, ShopHelpTooltip },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
