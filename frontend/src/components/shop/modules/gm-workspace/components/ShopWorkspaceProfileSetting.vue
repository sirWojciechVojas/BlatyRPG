<!-- Panel GM sklepu: osadza sklep w realiach miejsca, prawa i lokalnej gospodarki. -->
<template>
  <section class="profile-card" aria-labelledby="profile-setting-title">
    <header class="profile-card__header">
      <span class="profile-section-number">2</span>
      <div>
        <h3 id="profile-setting-title">
          {{ $t("shop.workspace.profile.setting.title") }}
        </h3>
        <p>{{ $t("shop.workspace.profile.setting.description") }}</p>
      </div>
      <button
        type="button"
        class="profile-section-reset"
        @click="resetProfileSection('setting')"
      >
        {{ $t("shop.workspace.profile.resetSection") }}
      </button>
    </header>

    <div class="profile-card__grid">
      <DenseField
        :label="$t('shop.workspace.fields.worldProfile')"
        :hint="$t('shop.workspace.profile.setting.worldHint')"
        :tooltip="
          $t('shop.workspace.profile.help.worldProfileId', {
            value: selectedWorldLabel,
          })
        "
        :error="profileFieldError('worldProfileId')"
        required
      >
        <select
          v-model="profileDraft.worldProfileId"
          class="form-select form-select-sm gm-combobox gm-combobox--world"
          @change="markShopDirty"
        >
          <option
            v-for="profile in worldProfiles"
            :key="profile.id"
            :value="profile.id"
          >
            {{ localizedRecordLabel(profile, profile.id) }}
          </option>
        </select>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.location')"
        :hint="profileGuidance('locationType', profileDraft.locationType)"
        :tooltip="
          $t('shop.workspace.profile.help.locationType', {
            value: selectedLocationLabel,
          })
        "
        :error="profileFieldError('locationType')"
        required
      >
        <select
          v-model="profileDraft.locationType"
          class="form-select form-select-sm gm-combobox gm-combobox--location"
          @change="markShopDirty"
        >
          <option
            v-for="place in profileLocationChoices"
            :key="place.id"
            :value="place.id"
          >
            {{ place.label }}
          </option>
        </select>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.legality')"
        :hint="profileGuidance('legalStatus', profileDraft.legalStatus)"
        :tooltip="
          $t('shop.workspace.profile.help.legalStatus', {
            value: $t(
              `shop.workspace.options.legalStatus.${profileDraft.legalStatus}`,
            ),
          })
        "
        :error="profileFieldError('legalStatus')"
        required
      >
        <select
          v-model="profileDraft.legalStatus"
          class="form-select form-select-sm gm-combobox gm-combobox--legal"
          @change="markShopDirty"
        >
          <option v-for="value in legalOptions" :key="value" :value="value">
            {{ $t(`shop.workspace.options.legalStatus.${value}`) }}
          </option>
        </select>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.wealth')"
        :hint="profileGuidance('wealthTier', profileDraft.wealthTier)"
        :tooltip="
          $t('shop.workspace.profile.help.wealthTier', {
            value: $t(
              `shop.workspace.options.wealth.${profileDraft.wealthTier}`,
            ),
          })
        "
        :error="profileFieldError('wealthTier')"
        required
      >
        <select
          v-model="profileDraft.wealthTier"
          class="form-select form-select-sm gm-combobox gm-combobox--wealth"
          @change="markShopDirty"
        >
          <option v-for="value in wealthOptions" :key="value" :value="value">
            {{ $t(`shop.workspace.options.wealth.${value}`) }}
          </option>
        </select>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.reputation')"
        :hint="profileGuidance('reputation', profileDraft.reputation)"
        :tooltip="
          $t('shop.workspace.profile.help.reputation', {
            value: $t(
              `shop.workspace.options.reputation.${profileDraft.reputation}`,
            ),
          })
        "
        :error="profileFieldError('reputation')"
        required
      >
        <select
          v-model="profileDraft.reputation"
          class="form-select form-select-sm gm-combobox gm-combobox--reputation"
          @change="markShopDirty"
        >
          <option
            v-for="value in reputationOptions"
            :key="value"
            :value="value"
          >
            {{ $t(`shop.workspace.options.reputation.${value}`) }}
          </option>
        </select>
      </DenseField>

      <DenseField
        :label="$t('shop.workspace.fields.seasonality')"
        :hint="profileGuidance('seasonality', profileDraft.seasonality)"
        :tooltip="
          $t('shop.workspace.profile.help.seasonality', {
            value: $t(
              `shop.workspace.options.seasonality.${profileDraft.seasonality}`,
            ),
          })
        "
        :error="profileFieldError('seasonality')"
        required
      >
        <select
          v-model="profileDraft.seasonality"
          class="form-select form-select-sm gm-combobox gm-combobox--season"
          @change="markShopDirty"
        >
          <option v-for="value in seasonOptions" :key="value" :value="value">
            {{ $t(`shop.workspace.options.seasonality.${value}`) }}
          </option>
        </select>
      </DenseField>
    </div>
  </section>
</template>

<script>
import DenseField from "@/components/shop/common/DenseField.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceProfileSetting",
  components: { DenseField },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
