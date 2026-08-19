import { computed } from "vue";
import {
  useCollectionIndex,
  useInstanceIndexes,
} from "@/components/shop/modules/gm-workspace/composables/useShopWorkspaceIndexes";
import {
  currencyDefinitionsForSystem,
  resolveDisplayCurrencyCode,
} from "@/lib/trade/currency";
import { resolveItemMechanics } from "@/lib/trade/itemMechanics";
import { createShopApiConfig, shopApiClient } from "@/lib/trade/shopApiClient";
export const installWorkspaceGroup2 = (deps) => {
  const activeShopId = computed(() => deps.shopState.value.activeShopId);
  const activeShop = computed(
    () => deps.shopById.value.get(Number(activeShopId.value)) || null,
  );
  const apiStatus = computed(() => deps.shopState.value.apiStatus);
  const formStatus = computed(() => deps.shopState.value.formStatus);
  const actors = computed(() => deps.shopState.value.actors || []);
  const actorOptions = computed(() =>
    actors.value.filter((entry) => entry.ownerCode),
  );
  const worldProfiles = computed(
    () => deps.shopState.value.worldProfiles || [],
  );
  const typeOptions = computed(() => {
    const entries = deps.shopState.value.shopTypes?.length
      ? deps.shopState.value.shopTypes
      : (deps.shopState.value.catalogNodes || []).filter(
          (entry) => entry.level === "type",
        );
    return entries.map((entry) => ({
      ...entry,
      databaseId: entry.databaseId ?? entry.id,
      id: String(entry.slug || entry.id || ""),
      labelPl: entry.labelPl || entry.namePl || entry.name || entry.slug || "",
      labelEn:
        entry.labelEn ||
        entry.nameEn ||
        entry.name ||
        entry.slug ||
        entry.id ||
        "",
      descriptionPl: entry.descriptionPl || entry.description || "",
      descriptionEn: entry.descriptionEn || entry.description || "",
      category: entry.category || "",
    }));
  });
  const templateItems = computed(
    () => deps.shopState.value.templateItems || [],
  );
  const templateById = useCollectionIndex(templateItems, "ID");
  const itemDictionaries = computed(
    () => deps.shopState.value.itemDictionaries || {},
  );
  const templateInheritedMechanics = computed(() =>
    resolveItemMechanics({
      dictionaries: itemDictionaries.value,
      itemClass: deps.templateDraft.ITEM_CLASS,
      itemGenre: deps.templateDraft.ITEM_GENRE,
      mode: "INHERIT",
    }),
  );
  const currencyContext = computed(
    () => deps.shopState.value.currencyDefinitions || {},
  );
  const encumbranceDefinition = computed(
    () =>
      deps.shopState.value.mechanics?.encumbrance || {
        presets: [],
      },
  );
  const currencyOptions = computed(() => {
    const definitions = currencyContext.value.currencies || [];
    return definitions.length
      ? definitions
      : currencyDefinitionsForSystem(
          deps.shopState.value.context?.systemCode || "wfrp2ed",
        );
  });
  const defaultCurrencyCode = computed(
    () =>
      currencyContext.value.defaultCurrencyCode ||
      currencyOptions.value[0]?.code ||
      "generic",
  );
  const displayCurrencyCode = (currencyCode) =>
    resolveDisplayCurrencyCode(currencyCode, defaultCurrencyCode.value);
  const itemClassOptions = computed(() => {
    const entries = itemDictionaries.value.classes || [];
    if (entries.length) return entries;
    return deps.itemClasses.map((code) => ({
      code,
      labelPl: deps.t(
        `modals.fieldEdit.fields.itemClass.options.${code}.label`,
      ),
      labelEn: code,
      appliesTo: [],
    }));
  });
  const itemGenreOptions = computed(() => {
    const entries = itemDictionaries.value.genres || [];
    if (entries.length) return entries;
    const codes = new Set(["UTILITY"]);
    templateItems.value.forEach((item) => codes.add(item.ITEM_GENRE));
    return Array.from(codes)
      .filter(Boolean)
      .map((code) => ({
        code,
        labelPl: code,
        labelEn: code,
        appliesTo: [],
      }));
  });
  const itemAttributeOptions = computed(
    () => itemDictionaries.value.attributes || [],
  );
  const catalogModeTitle = computed(() => {
    deps.locale.value;
    return deps.t(`shop.workspace.catalogModes.${deps.catalogMode.value}Title`);
  });
  const catalogModeDescription = computed(() => {
    deps.locale.value;
    return deps.t(
      `shop.workspace.catalogModes.${deps.catalogMode.value}Description`,
    );
  });
  const selectedInstanceTemplate = computed(() =>
    templateById.value.get(Number(deps.instanceDraft.templateId)),
  );
  const selectedCatalogKeys = computed(() => {
    const id =
      deps.catalogMode.value === "instances"
        ? deps.instanceDraft.templateId
        : deps.templateDraft.ID;
    return Number.isFinite(Number(id)) ? [Number(id)] : [];
  });
  const attributesForClass = (itemClass, selected = []) => {
    const selectedCodes = new Set((selected || []).map(String));
    return itemAttributeOptions.value.filter(
      (entry) =>
        !selectedCodes.has(entry.code) &&
        (!entry.appliesTo?.length || entry.appliesTo.includes(itemClass)),
    );
  };
  const availableTemplateAttributes = computed(() =>
    attributesForClass(
      deps.templateDraft.ITEM_CLASS,
      deps.templateDraft.ATTRIBUTES,
    ),
  );
  const availableInstanceAttributes = computed(() =>
    attributesForClass(
      deps.instanceDraft.itemClass,
      deps.instanceDraft.attributes,
    ),
  );
  const stackAttributeOptions = computed(() => {
    const selected = new Set(deps.stackInstanceDraft.attributes || []);
    return itemAttributeOptions.value.filter(
      (entry) =>
        selected.has(entry.code) ||
        !entry.appliesTo?.length ||
        entry.appliesTo.includes(deps.stackInstanceDraft.itemClass),
    );
  });
  const canCreateInstance = computed(
    () =>
      Number(deps.instanceDraft.templateId) > 0 &&
      Number(deps.instanceDraft.containerId) > 0 &&
      Boolean(String(deps.instanceDraft.name || "").trim()),
  );
  const selectedIconCode = computed(() =>
    deps.iconPickerTarget.value === "stackInstance"
      ? deps.stackInstanceDraft.imgClass
      : deps.iconPickerTarget.value === "instance"
        ? deps.instanceDraft.imgClass
        : deps.templateDraft.IMG_CLASS,
  );
  const dictionaryGroups = computed(() => [
    {
      key: "icon_categories",
      title: deps.t("shop.workspace.dictionaries.iconCategories"),
      description: deps.t(
        "shop.workspace.dictionaries.iconCategoriesDescription",
      ),
      entries: itemDictionaries.value.icon_categories || [],
      canAdd: true,
      canArchive: false,
      appliesToEditable: true,
      relationKind: "category",
      appliesToLabel: deps.t("shop.workspace.dictionaries.relationship"),
    },
    {
      key: "icon_subcategories",
      title: deps.t("shop.workspace.dictionaries.iconSubcategories"),
      description: deps.t(
        "shop.workspace.dictionaries.iconSubcategoriesDescription",
      ),
      entries: itemDictionaries.value.icon_subcategories || [],
      canAdd: true,
      canArchive: false,
      appliesToEditable: true,
      relationKind: "subcategory",
      appliesToLabel: deps.t("shop.workspace.dictionaries.parentCategory"),
    },
    {
      key: "classes",
      title: deps.t("shop.workspace.dictionaries.classes"),
      description: deps.t("shop.workspace.dictionaries.classesDescription"),
      entries: itemClassOptions.value,
      canAdd: true,
      canArchive: true,
      appliesToEditable: true,
      relationKind: "codes",
      relationOptionsGroup: "icon_categories",
      appliesToLabel: deps.t("shop.workspace.dictionaries.iconCategories"),
    },
    {
      key: "genres",
      title: deps.t("shop.workspace.dictionaries.genres"),
      description: deps.t("shop.workspace.dictionaries.genresDescription"),
      entries: itemGenreOptions.value,
      canAdd: true,
      canArchive: true,
      appliesToEditable: true,
      relationKind: "codes",
      relationOptionsGroup: "classes",
      appliesToLabel: deps.t("shop.workspace.dictionaries.classes"),
    },
    {
      key: "attributes",
      title: deps.t("shop.workspace.dictionaries.attributes"),
      description: deps.t("shop.workspace.dictionaries.attributesDescription"),
      entries: itemAttributeOptions.value,
      canAdd: true,
      canArchive: true,
      appliesToEditable: true,
      relationKind: "codes",
      relationOptionsGroup: "classes",
      appliesToLabel: deps.t("shop.workspace.dictionaries.classes"),
    },
  ]);
  const allItemInstances = computed(
    () => deps.shopState.value.allItemInstances || [],
  );
  const instanceIndexes = useInstanceIndexes(allItemInstances);
  const trashItems = computed(() => deps.shopState.value.trashItems || []);
  const archivedTemplateItems = computed(
    () => deps.shopState.value.archivedTemplateItems || [],
  );
  const apiStatusLabel = computed(() =>
    apiStatus.value === "loading"
      ? deps.t("ui.loading")
      : deps.t("status.error"),
  );
  const automaticTags = computed(() =>
    [
      deps.profileDraft.typeId && `typ:${deps.profileDraft.typeId}`,
      deps.profileDraft.worldProfileId &&
        `profil:${deps.profileDraft.worldProfileId}`,
      deps.profileDraft.locationType && `lok:${deps.profileDraft.locationType}`,
    ].filter(Boolean),
  );
  const activeOffer = computed(
    () => activeShop.value?.items || deps.shopState.value.shopItems || [],
  );
  const pricingEditorContext = {
    get shopEditorForm() {
      return deps.profileDraft;
    },
    get activeShopProfile() {
      return (
        deps.shopState.value.shopProfiles?.[Number(activeShopId.value)] || {}
      );
    },
    get currencyDefinitions() {
      return currencyContext.value;
    },
    get templateItems() {
      return templateItems.value;
    },
    get shopItems() {
      return activeOffer.value;
    },
    get catalogNodes() {
      return deps.shopState.value.catalogNodes || [];
    },
    get worldProfiles() {
      return worldProfiles.value;
    },
    handleShopEditorFieldUpdate({ field, value }) {
      deps.profileDraft[field] = value;
      deps.markShopDirty();
    },
    async requestPricingPreview(payload) {
      const shopId = Number(activeShopId.value);
      if (!Number.isInteger(shopId) || shopId <= 0) return null;
      return shopApiClient.previewShopPricing(
        createShopApiConfig({
          campaignId: Number(
            deps.shopState.value.campaignId ||
              deps.shopState.value.context?.campaignId ||
              1,
          ),
          ownerCode: deps.profileDraft.ownerCode || "BG1",
        }),
        shopId,
        payload,
      );
    },
  };
  Object.assign(deps, {
    activeShopId,
    activeShop,
    apiStatus,
    formStatus,
    actors,
    actorOptions,
    worldProfiles,
    typeOptions,
    templateItems,
    templateById,
    itemDictionaries,
    templateInheritedMechanics,
    currencyContext,
    encumbranceDefinition,
    currencyOptions,
    defaultCurrencyCode,
    displayCurrencyCode,
    itemClassOptions,
    itemGenreOptions,
    itemAttributeOptions,
    catalogModeTitle,
    catalogModeDescription,
    selectedInstanceTemplate,
    selectedCatalogKeys,
    attributesForClass,
    availableTemplateAttributes,
    availableInstanceAttributes,
    stackAttributeOptions,
    canCreateInstance,
    selectedIconCode,
    dictionaryGroups,
    allItemInstances,
    instanceIndexes,
    trashItems,
    archivedTemplateItems,
    apiStatusLabel,
    automaticTags,
    activeOffer,
    pricingEditorContext,
  });
};
