<!-- Panel GM sklepu: podsumowuje profil, jego spójność oraz operacje zapisu. -->
<template>
  <aside class="profile-summary" aria-labelledby="profile-summary-title">
    <div class="profile-signboard">
      <span>{{ $t("shop.workspace.profile.preview.signboard") }}</span>
      <strong>{{
        profileDraft.signboardName ||
        $t("shop.workspace.profile.preview.unnamed")
      }}</strong>
      <small>{{ selectedTypeLabel }}</small>
    </div>

    <section class="profile-summary__section">
      <div class="profile-summary__title-row">
        <h3 id="profile-summary-title">
          {{ $t("shop.workspace.profile.preview.title") }}
        </h3>
        <span>{{ profileCompletion }}%</span>
      </div>
      <div
        class="profile-completion"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="profileCompletion"
      >
        <span :style="{ width: `${profileCompletion}%` }"></span>
      </div>
      <p>{{ $t("shop.workspace.profile.preview.completionHint") }}</p>
    </section>

    <dl class="profile-summary__facts">
      <div>
        <dt>{{ $t("shop.workspace.fields.location") }}</dt>
        <dd>{{ selectedLocationLabel }}</dd>
      </div>
      <div>
        <dt>{{ $t("shop.workspace.fields.worldProfile") }}</dt>
        <dd>{{ selectedWorldLabel }}</dd>
      </div>
      <div>
        <dt>{{ $t("shop.workspace.fields.legality") }}</dt>
        <dd>
          {{
            $t(`shop.workspace.options.legalStatus.${profileDraft.legalStatus}`)
          }}
        </dd>
      </div>
      <div>
        <dt>{{ $t("shop.workspace.fields.wealth") }}</dt>
        <dd>
          {{ $t(`shop.workspace.options.wealth.${profileDraft.wealthTier}`) }}
        </dd>
      </div>
      <div>
        <dt>{{ $t("shop.workspace.fields.reputation") }}</dt>
        <dd>
          {{
            $t(`shop.workspace.options.reputation.${profileDraft.reputation}`)
          }}
        </dd>
      </div>
    </dl>

    <section v-if="marketImpactSummary.length" class="profile-summary__section">
      <h3>{{ $t("shop.workspace.profile.preview.marketImpact") }}</h3>
      <ul class="profile-impact-list">
        <li v-for="impact in marketImpactSummary" :key="impact.key">
          <span>{{ impact.label }}</span>
          <b>{{ impact.value > 0 ? "+" : "" }}{{ impact.value.toFixed(1) }}%</b>
          <small v-if="impact.limited">{{
            $t("shop.workspace.profile.preview.buybackLimited")
          }}</small>
        </li>
      </ul>
    </section>

    <section
      class="profile-summary__section profile-summary__section--warnings"
    >
      <h3>{{ $t("shop.workspace.profile.preview.logicCheck") }}</h3>
      <ul v-if="profileWarnings.length">
        <li
          v-for="warning in profileWarnings"
          :key="warning.key"
          :class="`is-${warning.tone}`"
        >
          <span aria-hidden="true">{{
            warning.tone === "required" ? "!" : "•"
          }}</span>
          {{ $t(`shop.workspace.profile.warnings.${warning.key}`) }}
        </li>
      </ul>
      <p v-else class="profile-summary__success">
        <span aria-hidden="true">✓</span>
        {{ $t("shop.workspace.profile.preview.consistent") }}
      </p>
    </section>

    <div class="profile-summary__actions">
      <button
        type="submit"
        class="primary"
        :disabled="formStatus.shop === 'saving'"
      >
        {{
          formStatus.shop === "saving"
            ? $t("shop.workspace.formStatus.saving")
            : $t("actions.save")
        }}
      </button>
      <button
        type="button"
        :disabled="formStatus.shop === 'saving'"
        @click="saveProfileAndGenerateOffer"
      >
        {{ $t("shop.workspace.profile.preview.saveAndSuggest") }}
      </button>
      <small>{{ $t("shop.workspace.profile.preview.saveHint") }}</small>
    </div>
  </aside>
</template>

<script>
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceProfileSummary",
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
