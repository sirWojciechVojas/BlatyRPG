<!-- Panel GM sklepu: ShopWorkspaceTemplateEditor. -->
<template>
  <form class="item-editor" @submit.prevent="saveTemplate">
    <div class="item-editor__context">
      <strong>{{ $t("shop.workspace.catalogModes.templateEditor") }}</strong>
      <span>{{ $t("shop.workspace.catalogModes.templateHint") }}</span>
    </div>
    <div class="item-editor__first-row">
      <DenseField :label="$t('shop.workspace.item.name')" required
        ><input
          v-model.trim="templateDraft.NAME"
          required
          @input="refreshTemplateIcon"
      /></DenseField>
      <DenseField :label="$t('shop.workspace.item.class')">
        <DomainCombobox
          v-model="templateDraft.ITEM_CLASS"
          :options="itemClassOptions"
          tone="class"
          @update:model-value="refreshTemplateIcon"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.genre')">
        <DomainCombobox
          v-model="templateDraft.ITEM_GENRE"
          :options="itemGenreOptions"
          tone="genre"
          @update:model-value="refreshTemplateIcon"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.price')" group>
        <SystemPriceInput
          v-model="templateDraft.PRIZE"
          v-model:currency-code="templateDraft.CURRENCY"
          :definitions="currencyOptions"
          @update:model-value="markTemplateDirty"
          @update:currency-code="markTemplateDirty"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.charge')">
        <EncumbranceInput
          v-model="templateDraft.CHARGE"
          :definition="encumbranceDefinition"
          @update:model-value="markTemplateDirty"
        />
      </DenseField>
      <DenseField :label="$t('shop.workspace.item.icon')">
        <button
          type="button"
          class="icon-field-button"
          @click="openIconPicker('template')"
        >
          <ItemIcon :item="templateDraft" :size="28" />
          <span>{{ templateDraft.IMG_CLASS }}</span>
          <small>{{ $t("shop.workspace.item.chooseIcon") }}</small>
        </button>
      </DenseField>
    </div>
    <div class="item-editor__text">
      <DenseField :label="$t('shop.workspace.item.description')">
        <textarea
          v-model="templateDraft.DESCRIPTION"
          @input="refreshTemplateIcon"
        /></DenseField
      ><DenseField :label="$t('shop.workspace.item.details')">
        <textarea v-model="templateDraft.DETAILS" @input="markTemplateDirty" />
      </DenseField>
    </div>
    <div class="item-attribute-editor">
      <span>{{ $t("shop.workspace.item.attributes") }}</span>
      <DomainCombobox
        v-model="templateAttributeToAdd"
        :options="availableTemplateAttributes"
        :placeholder="$t('shop.workspace.item.selectAttribute')"
        tone="attribute"
      />
      <button
        type="button"
        :disabled="!templateAttributeToAdd"
        @click="addTemplateAttribute"
      >
        {{ $t("actions.add") }}
      </button>
      <div class="item-attribute-editor__chips">
        <button
          v-for="code in templateDraft.ATTRIBUTES"
          :key="code"
          type="button"
          :title="$t('shop.workspace.item.removeAttribute')"
          @click="removeAttribute(templateDraft, code, markTemplateDirty)"
        >
          {{ domainLabel("attributes", code) }} <code>{{ code }}</code> ×
        </button>
      </div>
    </div>
    <ItemMechanicsEditor
      v-model="templateDraft.MECHANICS"
      v-model:mode="templateDraft.MECHANICS_MODE"
      class="item-editor__mechanics"
      :inherited="templateInheritedMechanics"
      allow-mode
      @change="markTemplateDirty"
      @update:mode="markTemplateDirty"
    >
      <template v-if="templateDraft.ITEM_CLASS === 'WEAPON'" #context>
        <div class="item-mechanics-weapon">
          <div>
            <strong>{{ $t("shop.workspace.mechanics.weaponContext") }}</strong>
            <small>{{
              $t("shop.workspace.mechanics.weaponContextHint")
            }}</small>
          </div>
          <DenseField :label="$t('shop.workspace.item.weaponType')">
            <input
              v-model="templateDraft.WEAPON.TYPE"
              :placeholder="$t('shop.workspace.mechanics.weaponTypeHint')"
              @input="markTemplateDirty"
            />
          </DenseField>
          <DenseField :label="$t('shop.workspace.item.damage')">
            <input
              v-model="templateDraft.WEAPON.DAMAGE"
              placeholder="1d10 + SB"
              @input="markTemplateDirty"
            />
          </DenseField>
          <DenseField :label="$t('shop.workspace.item.range')">
            <input
              v-model="templateDraft.WEAPON.RANGE"
              :placeholder="$t('shop.workspace.mechanics.rangeHint')"
              @input="markTemplateDirty"
            />
          </DenseField>
        </div>
      </template>
    </ItemMechanicsEditor>
    <details class="dense-form__advanced">
      <summary>{{ $t("shop.workspace.technical") }}</summary>
      <div class="technical-grid">
        <span>ID</span><code>{{ templateDraft.ID || "—" }}</code
        ><span>INV_ID</span><code>{{ templateDraft.INV_ID || "—" }}</code
        ><span>{{ $t("shop.workspace.item.source") }}</span
        ><code>shop_templates</code>
      </div>
    </details>
    <footer class="item-editor__actions">
      <StatusChip
        v-if="formStatus.template !== 'clean'"
        :label="$t(`shop.workspace.formStatus.${formStatus.template}`)"
        tone="warning"
      /><button
        v-if="templateDraft.ID"
        type="button"
        @click="duplicateTemplate"
      >
        {{ $t("shop.workspace.duplicate") }}</button
      ><button
        type="button"
        class="personalize"
        :disabled="!String(templateDraft.NAME || '').trim()"
        @click="personalizeTemplate"
      >
        {{ $t("actions.personalize") }}</button
      ><button
        v-if="templateDraft.ID"
        type="button"
        class="danger"
        @click="archiveTemplate"
      >
        {{ $t("shop.workspace.archive") }}</button
      ><button type="submit" class="primary">
        {{ $t("actions.save") }}
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
import ItemMechanicsEditor from "@/components/shop/common/ItemMechanicsEditor.vue";
import StatusChip from "@/components/shop/common/StatusChip.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspaceTemplateEditor",
  components: {
    DenseField,
    DomainCombobox,
    SystemPriceInput,
    EncumbranceInput,
    ItemIcon,
    ItemMechanicsEditor,
    StatusChip,
  },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
