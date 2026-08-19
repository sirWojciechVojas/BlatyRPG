<template>
  <section class="profile-compact-panel profile-tab-section">
    <header class="profile-tab-section__header">
      <div>
        <b>
          {{ $t("shop.workspace.profile.tools.title") }}
          <ShopHelpTooltip
            :label="$t('shop.workspace.profile.tools.title')"
            :text="$t('shop.workspace.profile.help.profileTools')"
          />
        </b>
        <small>{{ $t("shop.workspace.profile.tools.description") }}</small>
      </div>
    </header>
    <div class="profile-tools-grid">
      <section>
        <b>
          {{ $t("shop.workspace.profile.tools.presets") }}
          <ShopHelpTooltip
            :label="$t('shop.workspace.profile.tools.presets')"
            :text="$t('shop.workspace.profile.help.profilePresets')"
          />
        </b>
        <div class="profile-tools-inline">
          <input
            v-model.trim="profilePresetName"
            :placeholder="$t('shop.workspace.profile.tools.presetName')"
            maxlength="80"
            @keyup.enter="saveCustomProfilePreset"
          />
          <button type="button" @click="saveCustomProfilePreset">
            {{ $t("actions.save") }}
          </button>
        </div>
        <div class="profile-preset-list">
          <span v-for="preset in customProfilePresets" :key="preset.id">
            <button type="button" @click="applyCustomProfilePreset(preset)">
              {{ preset.name }}
            </button>
            <button type="button" @click="removeCustomProfilePreset(preset.id)">
              ×
            </button>
          </span>
        </div>
      </section>
      <section>
        <b>
          {{ $t("shop.workspace.profile.tools.operations") }}
          <ShopHelpTooltip
            :label="$t('shop.workspace.profile.tools.operations')"
            :text="$t('shop.workspace.profile.help.profileOperations')"
          />
        </b>
        <div class="profile-tools-buttons">
          <button type="button" @click="duplicateProfile">
            {{ $t("shop.workspace.profile.tools.duplicate") }}
          </button>
          <button type="button" @click="exportProfileJson">
            {{ $t("shop.workspace.profile.tools.export") }}
          </button>
          <label class="profile-file-button">
            {{ $t("shop.workspace.profile.tools.import") }}
            <input
              type="file"
              accept="application/json,.json"
              @change="importProfileJson($event.target.files[0])"
            />
          </label>
          <button type="button" @click="loadProfileHistory">
            {{ $t("shop.workspace.profile.tools.history") }}
          </button>
        </div>
        <small v-if="operationMessage" role="status">
          {{ operationMessage }}
        </small>
      </section>
      <section v-if="profileHistoryLoading || profileHistory.length">
        <b>
          {{ $t("shop.workspace.profile.tools.history") }}
          <ShopHelpTooltip
            :label="$t('shop.workspace.profile.tools.history')"
            :text="$t('shop.workspace.profile.help.profileHistory')"
          />
        </b>
        <select
          :value="selectedProfileRevision?.id || ''"
          @change="selectProfileRevision($event.target.value)"
        >
          <option value="">
            {{ $t("shop.workspace.profile.tools.chooseRevision") }}
          </option>
          <option
            v-for="revision in profileHistory"
            :key="revision.id"
            :value="revision.id"
          >
            {{ revision.createdAt }} · {{ revision.summary.name }} ·
            {{ revision.summary.policyId || "general" }}
          </option>
        </select>
        <button
          v-if="selectedProfileRevision"
          type="button"
          class="profile-mini-action"
          @click="applyProfileRevision"
        >
          {{ $t("shop.workspace.profile.tools.useRevision") }}
        </button>
      </section>
    </div>
  </section>
</template>

<script>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceProfileTools",
  components: { ShopHelpTooltip },
  setup() {
    const context = useShopWorkspaceContext();
    const selectProfileRevision = (id) => {
      const revision = context.profileHistory.value.find(
        (entry) => String(entry.id) === String(id),
      );
      if (revision) context.inspectProfileRevision(revision);
    };
    return { ...context, selectProfileRevision };
  },
};
</script>
