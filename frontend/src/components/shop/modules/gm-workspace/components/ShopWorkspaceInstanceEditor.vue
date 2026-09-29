<!-- Panel GM sklepu: ShopWorkspaceInstanceEditor. -->
<template>
  <form class="item-editor" @submit.prevent="createInstance">
    <div class="item-editor__context">
      <strong>{{ $t("shop.workspace.catalogModes.instanceEditor") }}</strong>
      <span v-if="selectedInstanceTemplate">
        {{ $t("shop.workspace.catalogModes.basedOn") }}:
        {{ selectedInstanceTemplate.NAME }} #{{ selectedInstanceTemplate.ID }}
      </span>
      <span v-else>{{ $t("shop.workspace.catalogModes.selectTemplate") }}</span>
    </div>
    <div class="item-editor__first-row">
      <DenseField :label="$t('shop.workspace.item.name')" required
        ><input v-model.trim="instanceDraft.name" required
      /></DenseField>
      <DenseField :label="$t('shop.workspace.item.class')">
        <DomainCombobox
          v-model="instanceDraft.itemClass"
          :options="itemClassOptions"
          tone="class"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.genre')">
        <DomainCombobox
          v-model="instanceDraft.itemGenre"
          :options="itemGenreOptions"
          tone="genre"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.price')" group>
        <SystemPriceInput
          v-model="instanceDraft.price"
          v-model:currency-code="instanceDraft.currencyCode"
          :definitions="currencyOptions"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.charge')">
        <EncumbranceInput
          v-model="instanceDraft.charge"
          :definition="encumbranceDefinition"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.icon')">
        <button
          type="button"
          class="icon-field-button"
          @click="openIconPicker('instance')"
        >
          <ItemIcon :item="{ IMG_CLASS: instanceDraft.imgClass }" :size="28" />
          <span>{{ instanceDraft.imgClass }}</span>
          <small>{{ $t("shop.workspace.item.chooseIcon") }}</small>
        </button>
      </DenseField>
    </div>
    <div class="item-editor__text">
      <DenseField :label="$t('shop.workspace.item.description')">
        <textarea v-model="instanceDraft.description" />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.details')">
        <textarea v-model="instanceDraft.details" />
      </DenseField>
    </div>
    <div class="instance-target-row">
      <DenseField :label="$t('shop.workspace.item.targetContainer')" required>
        <DomainCombobox
          v-model="instanceDraft.containerId"
          :options="warehouseContainerOptions"
          :placeholder="$t('shop.workspace.instanceStack.defaultTarget')"
          tone="location"
          :include-code="false"
          required
        />
      </DenseField>
    </div>
    <div class="item-editor__text">
      <DenseField label="Spożycie instancji">
        <select v-model="instanceDraft.consumptionMode">
          <option value="inherit">Dziedzicz z szablonu</option>
          <option value="disabled">Wyłącz spożycie</option>
          <option value="override">Nadpisz profilem z katalogu</option>
        </select>
        <ConsumptionProfilePicker
          v-if="instanceDraft.consumptionMode === 'override'"
          v-model="instanceDraft.consumptionProfileId"
          empty-label="Wybierz profil"
        />
        <small v-if="selectedInstanceTemplate?.CONSUMPTION_PROFILE_ID">
          Profil szablonu: {{ selectedInstanceTemplate.CONSUMPTION_PROFILE_ID }}
        </small>
        <small>
          Aktualny profil:
          {{
            instanceDraft.consumptionMode === "override"
              ? instanceDraft.consumptionProfileId || "—"
              : instanceDraft.consumptionMode === "disabled"
                ? "wyłączony"
                : selectedInstanceTemplate?.CONSUMPTION_PROFILE_ID || "—"
          }}
        </small>
      </DenseField>
      <DenseField label="Identyfikacja">
        <select v-model="instanceDraft.consumptionIdentification">
          <option value="unknown">Nieznany</option>
          <option value="suspected">Podejrzewany</option>
          <option value="identified">Rozpoznany</option>
          <option value="examined">Zbadany</option>
        </select>
      </DenseField>
      <DenseField label="Porcje">
        <input
          v-model.number="instanceDraft.consumptionPortions"
          min="1"
          type="number"
        />
      </DenseField>
    </div>
    <div class="item-attribute-editor">
      <span>{{ $t("shop.workspace.item.attributes") }}</span>
      <DomainCombobox
        v-model="instanceAttributeToAdd"
        :options="availableInstanceAttributes"
        :placeholder="$t('shop.workspace.item.selectAttribute')"
        tone="attribute"
      />
      <button
        type="button"
        :disabled="!instanceAttributeToAdd"
        @click="addInstanceAttribute"
      >
        {{ $t("actions.add") }}
      </button>
      <div class="item-attribute-editor__chips">
        <button
          v-for="code in instanceDraft.attributes"
          :key="code"
          type="button"
          :title="$t('shop.workspace.item.removeAttribute')"
          @click="removeAttribute(instanceDraft, code)"
        >
          {{ domainLabel("attributes", code) }} <code>{{ code }}</code> ×
        </button>
      </div>
    </div>
    <footer class="item-editor__actions">
      <StatusChip
        v-if="formStatus.instance !== 'clean'"
        :label="$t(`shop.workspace.formStatus.${formStatus.instance}`)"
        :tone="formStatus.instance === 'error' ? 'danger' : 'warning'"
      />
      <button type="submit" class="primary" :disabled="!canCreateInstance">
        {{ $t("shop.workspace.catalogModes.createInstance") }}
      </button>
    </footer>
  </form>
</template>
<script>
import DenseField from "@/components/shop/common/DenseField.vue";
import DomainCombobox from "@/components/shop/common/DomainCombobox.vue";
import SystemPriceInput from "@/components/shop/common/SystemPriceInput.vue";
import EncumbranceInput from "@/components/shop/common/EncumbranceInput.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import StatusChip from "@/components/shop/common/StatusChip.vue";
import ConsumptionProfilePicker from "@/components/shop/common/ConsumptionProfilePicker.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceInstanceEditor",
  components: {
    DenseField,
    DomainCombobox,
    SystemPriceInput,
    EncumbranceInput,
    ItemIcon,
    StatusChip,
    ConsumptionProfilePicker,
  },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
