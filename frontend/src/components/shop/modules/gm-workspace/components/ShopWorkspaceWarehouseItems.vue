<!-- Panel GM sklepu: ShopWorkspaceWarehouseItems. -->
<template>
  <div class="stack-instance-layout stack-instance-layout--editing">
    <ItemList
      :items="filteredInventory"
      item-key="ID"
      :selected-keys="warehouseSelection"
      :density="density"
      :label="$t('shop.workspace.items')"
      :empty-label="$t('shop.workspace.empty')"
      @select="toggleSelection(warehouseSelection, $event.ID)"
      @details="editStackInstance"
      ><template #default="{ item }"
        ><span class="item-icon-leading"
          ><ItemIcon
            :item="item"
            :size="density === 'comfortable' ? 34 : 30"
          /><span
            v-if="warehouseSelection.includes(item.ID)"
            class="item-icon-check"
            >✓</span
          ></span
        ><strong>{{ item.PERSONAL_PSEU || item.NAME }}</strong
        ><span class="instance-location"
          ><strong>{{ item.CONTAINER_NAME }}</strong
          ><small>{{ instanceOwnerLabel(item) }}</small></span
        ><span>{{ instanceKindLabel(item) }}</span
        ><span class="stack-instance-price"
          ><CurrencyDisplay
            :brass="item.PERSONAL_COST ?? item.ACTIVE_PRICE ?? item.PRIZE ?? 0"
            :currency-code="displayCurrencyCode(item.CURRENCY)"
            variant="row" /></span></template
    ></ItemList>

    <section
      v-if="!stackInstanceDraft.id"
      class="stack-instance-editor stack-instance-editor--empty"
    >
      <strong>{{ $t("shop.workspace.instanceStack.editorTitle") }}</strong>
      <span>{{ $t("shop.alerts.selectItem") }}</span>
      <button
        v-if="warehouseSelection.length === 1"
        type="button"
        class="primary"
        @click="editSelectedStackInstance"
      >
        {{ $t("actions.edit") }}
      </button>
    </section>

    <form
      v-else
      class="stack-instance-editor"
      @submit.prevent="saveStackInstance"
    >
      <header class="stack-instance-editor__header">
        <div>
          <strong>{{ $t("shop.workspace.instanceStack.editorTitle") }}</strong>
          <small>
            #{{ stackInstanceDraft.id }} ·
            {{ $t("shop.workspace.catalogModes.basedOn") }}
            #{{ stackInstanceDraft.templateId }}
          </small>
        </div>
        <button
          type="button"
          :aria-label="$t('actions.close')"
          @click="closeStackInstanceEditor"
        >
          ×
        </button>
      </header>

      <div class="item-editor__first-row">
        <DenseField :label="$t('shop.workspace.item.name')" required>
          <input v-model.trim="stackInstanceDraft.name" required />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.class')">
          <DomainCombobox
            v-model="stackInstanceDraft.itemClass"
            :options="itemClassOptions"
            tone="class"
          />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.genre')">
          <DomainCombobox
            v-model="stackInstanceDraft.itemGenre"
            :options="itemGenreOptions"
            tone="genre"
          />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.price')" group>
          <SystemPriceInput
            v-model="stackInstanceDraft.price"
            v-model:currency-code="stackInstanceDraft.currencyCode"
            :definitions="currencyOptions"
          />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.charge')">
          <EncumbranceInput
            v-model="stackInstanceDraft.charge"
            :definition="encumbranceDefinition"
          />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.icon')">
          <button
            type="button"
            class="icon-field-button"
            @click="openIconPicker('stackInstance')"
          >
            <ItemIcon
              :item="{ IMG_CLASS: stackInstanceDraft.imgClass }"
              :size="28"
            />
            <span>{{ stackInstanceDraft.imgClass }}</span>
            <small>{{ $t("shop.workspace.item.chooseIcon") }}</small>
          </button>
        </DenseField>
      </div>

      <div class="item-editor__text">
        <DenseField :label="$t('shop.workspace.item.description')">
          <textarea v-model="stackInstanceDraft.description" />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.details')">
          <textarea v-model="stackInstanceDraft.details" />
        </DenseField>
      </div>

      <DenseField :label="$t('shop.workspace.item.targetContainer')">
        <DomainCombobox
          v-model="stackInstanceDraft.containerId"
          :options="warehouseContainerOptions"
          tone="location"
          :include-code="false"
        />
      </DenseField>

      <section class="expanded-attribute-editor">
        <header>
          <strong>{{
            $t("shop.workspace.instanceStack.expandedAttributes")
          }}</strong>
          <small>{{
            $t("shop.workspace.instanceStack.expandedAttributesHint")
          }}</small>
        </header>
        <div class="expanded-attribute-editor__grid">
          <label
            v-for="entry in stackAttributeOptions"
            :key="entry.code"
            :class="{
              active: stackInstanceDraft.attributes.includes(entry.code),
            }"
          >
            <input
              type="checkbox"
              :checked="stackInstanceDraft.attributes.includes(entry.code)"
              @change="toggleStackAttribute(entry.code, $event.target.checked)"
            />
            <span>
              <strong>{{ localizedDomainLabel(entry) }}</strong>
              <code>{{ entry.code }}</code>
            </span>
          </label>
        </div>
      </section>

      <div
        v-if="stackInstanceDraft.itemClass === 'WEAPON'"
        class="item-editor__weapon"
      >
        <DenseField :label="$t('shop.workspace.item.weaponType')">
          <input v-model.trim="stackInstanceDraft.weapon.TYPE" />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.damage')">
          <input v-model.trim="stackInstanceDraft.weapon.DAMAGE" />
        </DenseField>
        <DenseField :label="$t('shop.workspace.item.range')">
          <input v-model.trim="stackInstanceDraft.weapon.RANGE" />
        </DenseField>
      </div>

      <details class="dense-form__advanced">
        <summary>{{ $t("shop.workspace.technical") }}</summary>
        <div class="technical-grid">
          <span>ID</span><code>{{ stackInstanceDraft.id }}</code>
          <span>INV_ID</span><code>{{ stackInstanceDraft.templateId }}</code>
          <span>{{ $t("shop.workspace.item.source") }}</span
          ><code>shop_item_instances</code>
        </div>
      </details>

      <footer class="item-editor__actions">
        <StatusChip
          v-if="formStatus.stackInstance !== 'clean'"
          :label="$t(`shop.workspace.formStatus.${formStatus.stackInstance}`)"
          :tone="formStatus.stackInstance === 'error' ? 'danger' : 'warning'"
        />
        <button type="button" @click="closeStackInstanceEditor">
          {{ $t("actions.cancel") }}
        </button>
        <button
          type="submit"
          class="primary"
          :disabled="!String(stackInstanceDraft.name || '').trim()"
        >
          {{ $t("actions.save") }}
        </button>
      </footer>
    </form>
  </div>
</template>
<script>
import ItemList from "@/components/shop/common/ItemList.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import DenseField from "@/components/shop/common/DenseField.vue";
import DomainCombobox from "@/components/shop/common/DomainCombobox.vue";
import SystemPriceInput from "@/components/shop/common/SystemPriceInput.vue";
import EncumbranceInput from "@/components/shop/common/EncumbranceInput.vue";
import StatusChip from "@/components/shop/common/StatusChip.vue";
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceWarehouseItems",
  components: {
    ItemList,
    ItemIcon,
    DenseField,
    DomainCombobox,
    SystemPriceInput,
    EncumbranceInput,
    StatusChip,
    CurrencyDisplay,
  },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
