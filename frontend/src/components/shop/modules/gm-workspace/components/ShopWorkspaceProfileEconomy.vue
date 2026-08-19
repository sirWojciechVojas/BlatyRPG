<!-- Panel GM sklepu: ustawia ryzyko obrotu monetą i prezentuje tagi profilu. -->
<template>
  <section class="profile-card" aria-labelledby="profile-economy-title">
    <header class="profile-card__header">
      <span class="profile-section-number">3</span>
      <div>
        <h3 id="profile-economy-title">
          {{ $t("shop.workspace.profile.economy.title") }}
        </h3>
        <p>{{ $t("shop.workspace.profile.economy.description") }}</p>
      </div>
      <button
        type="button"
        class="profile-section-reset"
        @click="resetProfileSection('economy')"
      >
        {{ $t("shop.workspace.profile.resetSection") }}
      </button>
    </header>

    <div class="profile-risk">
      <div class="profile-risk__heading">
        <div>
          <strong>
            {{ $t("shop.workspace.fields.counterfeitRisk") }}
            <ShopHelpTooltip
              :label="$t('shop.workspace.fields.counterfeitRisk')"
              :text="
                $t('shop.workspace.profile.help.counterfeitRisk', {
                  value: profileDraft.counterfeitRisk,
                })
              "
            />
          </strong>
          <p>{{ $t("shop.workspace.profile.economy.riskHint") }}</p>
        </div>
        <output
          class="profile-risk__value"
          :class="`profile-risk__value--${profileRiskTone}`"
        >
          {{ profileDraft.counterfeitRisk }}%
        </output>
      </div>
      <input
        v-model.number="profileDraft.counterfeitRisk"
        type="range"
        min="0"
        max="100"
        step="1"
        :aria-label="$t('shop.workspace.fields.counterfeitRisk')"
        @input="markShopDirty"
      />
      <div class="profile-risk__scale" aria-hidden="true">
        <span>{{ $t("shop.workspace.profile.economy.riskLow") }}</span>
        <span>{{ $t("shop.workspace.profile.economy.riskHigh") }}</span>
      </div>
      <button
        type="button"
        class="profile-risk__recommendation"
        @click="applyRecommendedCounterfeitRisk"
      >
        {{
          $t("shop.workspace.profile.economy.recommended", {
            value: recommendedCounterfeitRisk,
          })
        }}
      </button>
    </div>

    <div class="profile-card__grid profile-market-grid">
      <DenseField
        :label="$t('shop.workspace.profile.market.demand')"
        :tooltip="
          $t('shop.workspace.profile.help.demandLevel', {
            value: $t(
              `shop.workspace.profile.market.demandLevels.${profileDraft.marketSettings.demandLevel}`,
            ),
          })
        "
      >
        <select
          v-model="profileDraft.marketSettings.demandLevel"
          @change="markShopDirty"
        >
          <option
            v-for="value in ['very_low', 'low', 'normal', 'high', 'extreme']"
            :key="value"
            :value="value"
          >
            {{ $t(`shop.workspace.profile.market.demandLevels.${value}`) }}
          </option>
        </select>
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.market.availabilityBias')"
        :tooltip="
          $t('shop.workspace.profile.help.availabilityBias', {
            value: profileDraft.marketSettings.availabilityBias,
          })
        "
      >
        <input
          v-model.number="profileDraft.marketSettings.availabilityBias"
          type="number"
          min="-50"
          max="50"
          @input="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.market.buybackBudget')"
        :tooltip="
          $t('shop.workspace.profile.help.buybackBudget', {
            value:
              profileDraft.marketSettings.buybackBudget ??
              $t('shop.workspace.profile.market.auto'),
          })
        "
      >
        <input
          v-model.number="profileDraft.marketSettings.buybackBudget"
          type="number"
          min="0"
          :placeholder="$t('shop.workspace.profile.market.auto')"
          @input="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.market.maxBuybackItemValue')"
        :tooltip="
          $t('shop.workspace.profile.help.maxBuybackItemValue', {
            value:
              profileDraft.marketSettings.maxBuybackItemValue ??
              $t('shop.workspace.profile.market.auto'),
          })
        "
      >
        <input
          v-model.number="profileDraft.marketSettings.maxBuybackItemValue"
          type="number"
          min="0"
          :placeholder="$t('shop.workspace.profile.market.auto')"
          @input="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.market.expensiveStockLimit')"
        :tooltip="
          $t('shop.workspace.profile.help.expensiveStockLimit', {
            value:
              profileDraft.marketSettings.expensiveStockLimit ??
              $t('shop.workspace.profile.market.auto'),
          })
        "
      >
        <input
          v-model.number="profileDraft.marketSettings.expensiveStockLimit"
          type="number"
          min="0"
          :placeholder="$t('shop.workspace.profile.market.auto')"
          @input="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.market.localCategories')"
        :tooltip="
          $t('shop.workspace.profile.help.localCategories', {
            value: marketCategoryList('localCategories') || '—',
          })
        "
      >
        <input
          :value="marketCategoryList('localCategories')"
          type="text"
          :placeholder="$t('shop.workspace.profile.market.categoryPlaceholder')"
          @input="
            updateMarketCategoryList('localCategories', $event.target.value)
          "
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.market.importedCategories')"
        :tooltip="
          $t('shop.workspace.profile.help.importedCategories', {
            value: marketCategoryList('importedCategories') || '—',
          })
        "
      >
        <input
          :value="marketCategoryList('importedCategories')"
          type="text"
          :placeholder="$t('shop.workspace.profile.market.categoryPlaceholder')"
          @input="
            updateMarketCategoryList('importedCategories', $event.target.value)
          "
        />
      </DenseField>
    </div>

    <details class="profile-compact-panel profile-reputation-panel">
      <summary>
        <span>
          <b>
            {{ $t("shop.workspace.profile.market.actorReputation") }}
            <ShopHelpTooltip
              :label="$t('shop.workspace.profile.market.actorReputation')"
              :text="
                $t('shop.workspace.profile.help.actorReputation', {
                  count: Object.keys(
                    profileDraft.marketSettings.reputationByActor || {},
                  ).length,
                })
              "
            />
          </b>
          <small>{{
            $t("shop.workspace.profile.help.actorReputation", {
              count: Object.keys(
                profileDraft.marketSettings.reputationByActor || {},
              ).length,
            })
          }}</small>
        </span>
        <span>{{
          Object.keys(profileDraft.marketSettings.reputationByActor || {})
            .length
        }}</span>
      </summary>
      <div class="profile-actor-reputation-grid">
        <label v-for="actor in actorOptions" :key="actor.ownerCode">
          <span>{{ actor.name || actor.ownerCode }}</span>
          <select
            :value="
              profileDraft.marketSettings.reputationByActor?.[
                actor.ownerCode
              ] || ''
            "
            @change="
              updateActorReputation(actor.ownerCode, $event.target.value)
            "
          >
            <option value="">
              {{ $t("shop.workspace.profile.market.useBaseReputation") }}
            </option>
            <option
              v-for="value in [
                'fatalna',
                'zla',
                'podejrzana',
                'neutralna',
                'dobra',
                'znakomita',
              ]"
              :key="value"
              :value="value"
            >
              {{ $t(`shop.workspace.options.reputation.${value}`) }}
            </option>
          </select>
        </label>
      </div>
    </details>

    <div class="profile-tags">
      <div>
        <strong>
          {{ $t("shop.workspace.fields.autoTags") }}
          <ShopHelpTooltip
            :label="$t('shop.workspace.fields.autoTags')"
            :text="$t('shop.workspace.profile.help.autoTags')"
          />
        </strong>
        <p>{{ $t("shop.workspace.profile.economy.tagsHint") }}</p>
      </div>
      <div class="profile-tags__list">
        <span v-for="tag in automaticTags" :key="tag">{{ tag }}</span>
        <small v-if="!automaticTags.length">—</small>
      </div>
    </div>
  </section>
</template>

<script>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import DenseField from "@/components/shop/common/DenseField.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceProfileEconomy",
  components: { DenseField, ShopHelpTooltip },
  setup() {
    const context = useShopWorkspaceContext();
    const updateActorReputation = (actorCode, reputation) => {
      const settings = context.profileDraft.marketSettings;
      const relations =
        settings.reputationByActor || (settings.reputationByActor = {});
      if (reputation) relations[actorCode] = reputation;
      else delete relations[actorCode];
      context.markShopDirty();
    };
    return { ...context, updateActorReputation };
  },
};
</script>
