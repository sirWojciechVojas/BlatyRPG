<!-- Panel GM sklepu: prowadzi przez tworzenie i edycję historycznie spójnego profilu sklepu. -->
<template>
  <form class="profile-editor" novalidate @submit.prevent="saveProfile">
    <header class="profile-editor__hero">
      <div>
        <p class="profile-editor__eyebrow">
          {{ $t("shop.workspace.profile.eyebrow") }}
        </p>
        <h2>{{ $t("shop.workspace.profile.title") }}</h2>
        <p>{{ $t("shop.workspace.profile.description") }}</p>
      </div>
      <div
        v-if="formStatus.shop === 'dirty'"
        class="profile-editor__hero-actions"
      >
        <button type="button" @click="discardProfileChanges">
          {{ $t("shop.workspace.profile.discard") }}
        </button>
      </div>
    </header>

    <div class="profile-editor__layout">
      <div class="profile-editor__main">
        <nav
          class="profile-editor__tabs"
          role="tablist"
          :aria-label="$t('shop.workspace.profile.tabs.label')"
        >
          <button
            v-for="tab in profileTabs"
            :key="tab"
            type="button"
            role="tab"
            :class="{ active: activeProfilePanel === tab }"
            :aria-selected="activeProfilePanel === tab"
            :aria-controls="`profile-panel-${tab}`"
            @click="activeProfilePanel = tab"
          >
            {{ $t(`shop.workspace.profile.tabs.${tab}`) }}
          </button>
        </nav>
        <div
          :id="`profile-panel-${activeProfilePanel}`"
          class="profile-editor__tab-panel"
          role="tabpanel"
        >
          <section
            v-if="activeProfilePanel === 'archetype'"
            class="profile-archetypes"
            aria-labelledby="profile-archetypes-title"
          >
            <div class="profile-archetypes__heading">
              <div>
                <span class="profile-section-number">0</span>
                <h3 id="profile-archetypes-title">
                  {{ $t("shop.workspace.profile.archetypes.title") }}
                </h3>
              </div>
              <p>{{ $t("shop.workspace.profile.archetypes.hint") }}</p>
            </div>
            <div class="profile-archetypes__grid">
              <button
                v-for="archetype in shopProfileArchetypes"
                :key="archetype.id"
                type="button"
                class="profile-archetype"
                :class="{
                  active: selectedProfileArchetype === archetype.id,
                }"
                @click="applyProfileArchetype(archetype)"
              >
                <span class="profile-archetype__icon" aria-hidden="true">
                  {{ archetype.icon }}
                </span>
                <span>
                  <strong>{{
                    $t(`shop.workspace.profile.archetypes.${archetype.id}.name`)
                  }}</strong>
                  <small>{{
                    $t(
                      `shop.workspace.profile.archetypes.${archetype.id}.description`,
                    )
                  }}</small>
                </span>
              </button>
            </div>
          </section>
          <ShopWorkspaceProfileIdentity
            v-else-if="activeProfilePanel === 'identity'"
          />
          <ShopWorkspaceProfileSetting
            v-else-if="activeProfilePanel === 'setting'"
          />
          <ShopWorkspaceProfileEconomy
            v-else-if="activeProfilePanel === 'economy'"
          />
          <ShopWorkspaceMarketEvents
            v-else-if="activeProfilePanel === 'events'"
          />
          <ShopWorkspaceProfileTools v-else />
        </div>
      </div>
      <aside class="profile-editor__rail">
        <nav
          class="profile-editor__tabs profile-editor__tabs--rail"
          role="tablist"
          :aria-label="$t('shop.workspace.profile.tabs.previewLabel')"
        >
          <button
            v-for="tab in previewTabs"
            :key="tab"
            type="button"
            role="tab"
            :class="{ active: activePreviewPanel === tab }"
            :aria-selected="activePreviewPanel === tab"
            @click="activePreviewPanel = tab"
          >
            {{ $t(`shop.workspace.profile.tabs.${tab}`) }}
            <span v-if="tab === 'summary' && profileWarnings.length">
              {{ profileWarnings.length }}
            </span>
          </button>
        </nav>
        <ShopWorkspaceProfileSummary v-if="activePreviewPanel === 'summary'" />
        <ShopPricingPreviewHost
          v-else
          :form="profileDraft"
          :currency-options="pricingCurrencyOptions"
          :currency-definitions="currencyContext"
          :preview-template-id="pricingPreviewTemplateId"
          :preview-template-options="pricingPreviewTemplateOptions"
          :price-preview="pricingPricePreview"
          :preview-quantity="pricingPreviewQuantity"
          :preview-condition="pricingPreviewCondition"
          :preview-reputation="pricingPreviewReputation"
          :preview-mode="pricingPreviewMode"
          :preview-temporary-modifier="pricingPreviewTemporaryModifier"
          :preview-quick-mode="pricingPreviewQuickMode"
          :preview-loading="pricingPreviewLoading"
          :preview-error="pricingPreviewError"
          @update-preview-template="updatePricingPreviewTemplate"
          @update-preview-input="updatePricingPreviewInput"
        />
      </aside>
    </div>
  </form>
</template>

<script>
import { ref } from "vue";
import ShopWorkspaceProfileEconomy from "./ShopWorkspaceProfileEconomy.vue";
import ShopWorkspaceProfileIdentity from "./ShopWorkspaceProfileIdentity.vue";
import ShopWorkspaceProfileSetting from "./ShopWorkspaceProfileSetting.vue";
import ShopWorkspaceProfileSummary from "./ShopWorkspaceProfileSummary.vue";
import ShopWorkspaceMarketEvents from "./ShopWorkspaceMarketEvents.vue";
import ShopWorkspaceProfileTools from "./ShopWorkspaceProfileTools.vue";
import ShopPricingPreviewHost from "@/components/shop/modules/shop-editor/components/pricing/ShopPricingPreviewHost.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceProfile",
  components: {
    ShopWorkspaceProfileEconomy,
    ShopWorkspaceProfileIdentity,
    ShopWorkspaceProfileSetting,
    ShopWorkspaceProfileSummary,
    ShopWorkspaceMarketEvents,
    ShopWorkspaceProfileTools,
    ShopPricingPreviewHost,
  },
  setup() {
    return {
      ...useShopWorkspaceContext(),
      activeProfilePanel: ref("identity"),
      activePreviewPanel: ref("simulator"),
      profileTabs: [
        "identity",
        "setting",
        "economy",
        "events",
        "archetype",
        "tools",
      ],
      previewTabs: ["simulator", "summary"],
    };
  },
};
</script>

<style src="../styles/ShopWorkspaceProfile.css"></style>
<style src="../styles/ShopWorkspaceProfile.details.css"></style>
<style src="../styles/ShopWorkspaceProfileOperations.css"></style>
<style src="../styles/ShopWorkspaceProfile.tabs.css"></style>
