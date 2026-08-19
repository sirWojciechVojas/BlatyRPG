<!-- Responsibility: WeaponStatsDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showWeaponStatsDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.weaponStats.ariaLabel')"
    @click.self="ctx.closeWeaponStatsDialog"
  >
    <div class="weapon-stats-dialog">
      <div class="item-detail-titlebar weapon-stats-dialog__topbar">
        <div class="item-detail-title-text">
          {{ topBarTitle }}
        </div>
        <button
          type="button"
          class="weapon-stats-dialog__close-x"
          :aria-label="$t('common.actions.close')"
          @click="ctx.closeWeaponStatsDialog"
        >
          &times;
        </button>
      </div>
      <div class="img-dialog-purpose">
        {{ $t("modals.weaponStats.purpose") }}
      </div>
      <div class="weapon-stats-dialog__titlebar">
        <div class="item-detail-title-text">
          {{ selectedWeaponTitle }}
        </div>
      </div>

      <div class="weapon-stats-dialog__body">
        <div class="weapon-stats-dialog__selector">
          <div
            class="img-grid-title d-flex align-items-center justify-content-between"
          >
            {{ $t("modals.weaponStats.selectLabel") }}
            <button
              type="button"
              class="btn btn-outline-light btn-sm"
              @click="ctx.createWeaponStats"
            >
              {{ $t("common.actions.add") }}
            </button>
          </div>
          <div class="weapon-stats-dialog__list">
            <button
              v-for="option in weaponOptions"
              :key="`weapon-option-${option.value}`"
              type="button"
              class="weapon-stats-dialog__weapon-btn"
              :class="{ active: option.value === selectedWeaponId }"
              @click="selectWeapon(option.value)"
            >
              <span class="weapon-stats-dialog__weapon-name">
                {{ option.label }}
              </span>
              <span
                v-if="option.subtitle"
                class="weapon-stats-dialog__weapon-subtitle"
              >
                {{ option.subtitle }}
              </span>
            </button>
          </div>
          <div v-if="!weaponOptions.length" class="weapon-stats-dialog__hint">
            {{ $t("modals.weaponStats.noOptions") }}
          </div>
        </div>

        <div class="weapon-stats-dialog__fields">
          <div class="img-grid-title">
            {{ $t("modals.weaponStats.fieldsTitle") }}
          </div>
          <div class="weapon-stats-dialog__grid">
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.name")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('NAME')"
                @input="updateField('NAME', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.type")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('TYPE')"
                @input="updateField('TYPE', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.handed")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('HANDED')"
                @input="updateField('HANDED', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.category")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('CATEGORY')"
                @input="updateField('CATEGORY', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.damage")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('DAMAGE')"
                @input="updateField('DAMAGE', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.dice")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('DICE')"
                @input="updateField('DICE', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.modifier")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('MODIFIER')"
                @input="updateField('MODIFIER', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.load")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('LOAD')"
                @input="updateField('LOAD', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.range")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('RANGE')"
                @input="updateField('RANGE', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.reload")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('RELOAD')"
                @input="updateField('RELOAD', $event)"
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.featuresId")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('FEATURES_ID')"
                readonly
              />
            </div>
            <div class="weapon-stats-dialog__field">
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.occuChance")
              }}</label>
              <input
                type="text"
                class="form-control-sm trade-input"
                :value="draftValue('OCCU_CHANCE')"
                @input="updateField('OCCU_CHANCE', $event)"
              />
            </div>
            <div
              class="weapon-stats-dialog__field weapon-stats-dialog__field--wide"
            >
              <label class="trade-label">{{
                $t("modals.weaponStats.fields.qualities")
              }}</label>
              <div class="weapon-stats-dialog__feature-picker">
                <button
                  v-for="feature in featureOptions"
                  :key="`weapon-feature-${feature.id}`"
                  type="button"
                  class="weapon-stats-dialog__feature-chip"
                  :class="{ active: isFeatureActive(feature.id) }"
                  @click="toggleFeature(feature.id)"
                >
                  {{ feature.name }} [{{ feature.id }}]
                </button>
              </div>
              <div class="weapon-stats-dialog__feature-details">
                <div class="weapon-stats-dialog__feature-title">
                  {{
                    selectedFeatureDetails?.name ||
                    $t("modals.weaponStats.featureSelectHint")
                  }}
                </div>
                <div
                  v-if="selectedFeatureDetails"
                  class="weapon-stats-dialog__feature-desc"
                >
                  {{ selectedFeatureDetails.description }}
                </div>
                <div
                  v-if="selectedFeatureDetails"
                  class="weapon-stats-dialog__feature-mechanics"
                >
                  {{ selectedFeatureDetails.mechanics }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="img-dialog-footer">
        <button
          type="button"
          class="btn btn-outline-danger"
          @click="ctx.removeWeaponStats"
        >
          {{ $t("common.actions.delete") }}
        </button>
        <button
          type="button"
          class="btn btn-outline-light"
          @click="ctx.closeWeaponStatsDialog"
        >
          {{ $t("common.actions.cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-success"
          @click="ctx.confirmWeaponStats"
        >
          {{ $t("common.actions.save") }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useWeaponStatsDialog } from "@/components/shop/common/dialogs/useWeaponStatsDialog";

const {
  ctx,
  draftValue,
  featureOptions,
  isFeatureActive,
  selectWeapon,
  selectedFeatureDetails,
  selectedWeaponId,
  selectedWeaponTitle,
  toggleFeature,
  topBarTitle,
  updateField,
  weaponOptions,
} = useWeaponStatsDialog();
</script>
