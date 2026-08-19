<!-- Panel GM sklepu: tworzenie odrębnego sklepu wraz z domyślnym profilem. -->
<template>
  <div class="shop-create-dialog" @click.self="requestClose">
    <form
      class="shop-create-dialog__panel"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      novalidate
      @submit.prevent="submit"
      @keydown.esc.prevent="requestClose"
    >
      <header>
        <div>
          <p>{{ $t("shop.workspace.createShop.eyebrow") }}</p>
          <h2 :id="titleId">{{ $t("shop.workspace.createShop.title") }}</h2>
          <small>{{ $t("shop.workspace.createShop.description") }}</small>
        </div>
        <button
          type="button"
          class="shop-create-dialog__close"
          :aria-label="$t('shop.workspace.createShop.close')"
          :disabled="busy"
          @click="requestClose"
        >
          ×
        </button>
      </header>

      <div class="shop-create-dialog__fields">
        <DenseField
          :label="$t('shop.workspace.createShop.type')"
          :tooltip="$t('shop.workspace.createShop.typeHelp')"
          :error="typeError"
          required
          wide
        >
          <select
            ref="typeInput"
            v-model="form.typeId"
            class="form-select form-select-sm gm-combobox gm-combobox--type"
            @change="handleTypeChange"
          >
            <option value="">
              {{ $t("shop.workspace.createShop.chooseType") }}
            </option>
            <optgroup
              v-for="group in typeGroups"
              :key="group.label"
              :label="group.label"
            >
              <option
                v-for="type in group.options"
                :key="type.id"
                :value="type.id"
              >
                {{ typeLabel(type) }}
              </option>
            </optgroup>
          </select>
        </DenseField>
        <DenseField
          :label="$t('shop.workspace.createShop.name')"
          :hint="$t('shop.workspace.createShop.nameHint')"
          :tooltip="$t('shop.workspace.createShop.nameHelp')"
          :error="nameError"
          group
          required
          wide
        >
          <div class="shop-create-dialog__name-control">
            <input
              v-model.trim="form.name"
              type="text"
              maxlength="255"
              autocomplete="off"
              @input="generationError = ''"
            />
            <button
              type="button"
              :disabled="!form.typeId || busy"
              @click="rollShopName"
            >
              <span aria-hidden="true">↻</span>
              {{ $t("shop.workspace.createShop.rollName") }}
            </button>
            <ShopHelpTooltip
              :label="$t('shop.workspace.createShop.rollName')"
              :text="$t('shop.workspace.createShop.rollNameHelp')"
              align="right"
            />
          </div>
        </DenseField>
        <DenseField
          :label="$t('shop.workspace.createShop.owner')"
          :tooltip="$t('shop.workspace.createShop.ownerHelp')"
          required
        >
          <select
            v-model="form.ownerCode"
            class="form-select form-select-sm gm-combobox gm-combobox--actor"
            @change="syncOwnerName"
          >
            <option
              v-for="actor in actorOptions"
              :key="actor.ownerCode"
              :value="actor.ownerCode"
            >
              {{ actor.ownerCode }} — {{ actor.name }}
            </option>
            <option v-if="!hasNpcOwner" value="NPC">
              NPC — {{ $t("shop.workspace.createShop.independentOwner") }}
            </option>
          </select>
        </DenseField>
        <DenseField
          :label="$t('shop.workspace.createShop.ownerName')"
          :hint="$t('shop.workspace.createShop.ownerNameHint')"
          :tooltip="$t('shop.workspace.createShop.ownerNameHelp')"
        >
          <input
            v-model.trim="form.ownerName"
            type="text"
            maxlength="255"
            autocomplete="off"
          />
        </DenseField>
      </div>

      <p v-if="error" class="shop-create-dialog__error" role="alert">
        {{ error }}
      </p>
      <footer>
        <span>{{ $t("shop.workspace.createShop.resultHint") }}</span>
        <button type="button" :disabled="busy" @click="requestClose">
          {{ $t("actions.cancel") }}
        </button>
        <button type="submit" class="primary" :disabled="busy">
          {{
            busy
              ? $t("shop.workspace.createShop.creating")
              : $t("shop.workspace.createShop.submit")
          }}
        </button>
      </footer>
    </form>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { useI18n } from "vue-i18n";
import DenseField from "@/components/shop/common/DenseField.vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import {
  drawShopSignboard,
  localizedShopTypeLabel,
} from "@/lib/shopSignboardService";

const props = defineProps({
  actorOptions: { type: Array, default: () => [] },
  typeOptions: { type: Array, default: () => [] },
  existingNames: { type: Array, default: () => [] },
  initialOwnerCode: { type: String, default: "NPC" },
  initialOwnerName: { type: String, default: "" },
  busy: { type: Boolean, default: false },
  error: { type: String, default: "" },
});
const emit = defineEmits(["close", "create"]);
const { t, locale } = useI18n();
const titleId = `shop-create-title-${Math.random().toString(36).slice(2, 9)}`;
const typeInput = ref(null);
const validationVisible = ref(false);
const typeValidationVisible = ref(false);
const generatedName = ref("");
const generationError = ref("");
const requestedOwnerCode = String(props.initialOwnerCode || "").toUpperCase();
const initialOwnerCode = props.actorOptions.some(
  (actor) => actor.ownerCode === requestedOwnerCode,
)
  ? requestedOwnerCode
  : props.actorOptions[0]?.ownerCode || "NPC";
const form = reactive({
  typeId: "",
  name: "",
  ownerCode: initialOwnerCode,
  ownerName: props.initialOwnerName || "",
});
const hasNpcOwner = computed(() =>
  props.actorOptions.some((actor) => actor.ownerCode === "NPC"),
);
const nameError = computed(
  () =>
    generationError.value ||
    (validationVisible.value && !form.name.trim()
      ? t("shop.workspace.createShop.nameRequired")
      : ""),
);
const typeError = computed(() =>
  (validationVisible.value || typeValidationVisible.value) && !form.typeId
    ? t("shop.workspace.createShop.typeRequired")
    : "",
);
const typeGroups = computed(() => {
  const groups = new Map();
  props.typeOptions.forEach((type) => {
    const label = type.category || t("shop.workspace.createShop.otherTypes");
    if (!groups.has(label)) groups.set(label, []);
    groups.get(label).push(type);
  });
  return Array.from(groups, ([label, options]) => ({ label, options }));
});

function typeLabel(type) {
  return localizedShopTypeLabel(type, locale.value);
}

function requestClose() {
  if (!props.busy) emit("close");
}
function syncOwnerName() {
  const actor = props.actorOptions.find(
    (entry) => entry.ownerCode === form.ownerCode,
  );
  if (actor) form.ownerName = actor.name || "";
}
function rollShopName() {
  typeValidationVisible.value = true;
  generationError.value = "";
  if (!form.typeId) return;
  try {
    const result = drawShopSignboard({
      typeId: form.typeId,
      typeOptions: props.typeOptions,
      locale: locale.value,
      profile: { ownerCode: form.ownerCode },
      ownerName: form.ownerName,
      existingNames: props.existingNames,
    });
    if (!result?.signboardName) throw new Error("empty signboard name");
    form.name = result.signboardName;
    generatedName.value = result.signboardName;
    typeValidationVisible.value = false;
  } catch (_error) {
    generationError.value = t("shop.workspace.createShop.rollNameError");
  }
}
function handleTypeChange() {
  typeValidationVisible.value = false;
  if (!form.name.trim() || form.name === generatedName.value) rollShopName();
}
function submit() {
  validationVisible.value = true;
  if (!form.typeId || !form.name.trim() || props.busy) return;
  emit("create", {
    typeId: form.typeId,
    name: form.name.trim(),
    ownerCode: form.ownerCode || "NPC",
    ownerName: form.ownerName.trim(),
  });
}

onMounted(async () => {
  await nextTick();
  typeInput.value?.focus();
});
</script>

<style src="../styles/ShopWorkspaceCreateShopDialog.css"></style>
