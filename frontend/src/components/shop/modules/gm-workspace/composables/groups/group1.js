import { useStore } from "vuex";
import { useRouter, useRoute } from "vue-router";
import i18n from "@/i18n";
import { computed, ref, reactive } from "vue";
import { useDeferredValue } from "@/components/shop/modules/gm-workspace/composables/useDeferredValue";
import { useCollectionIndex } from "@/components/shop/modules/gm-workspace/composables/useShopWorkspaceIndexes";
import { exposeMutable } from "../workspaceDependencies";
import {
  emptyInstance,
  emptyStackInstance,
  emptyTemplate,
} from "../workspaceDrafts";
import {
  catalogModes,
  instanceLocationFilters,
  itemClasses,
  legalOptions,
  reputationOptions,
  seasonOptions,
  shopSubtabs,
  tabs,
  wealthOptions,
} from "../../options/workspaceOptions";
import {
  lateMedievalTypeSuggestions,
  profileLocationOptions,
} from "../../options/profileTypeOptions";
import { shopProfileArchetypes } from "../../options/profileArchetypes";
export const installWorkspaceGroup1 = (deps) => {
  const store = useStore();
  const router = useRouter();
  const route = useRoute();
  const t = (key, values = {}) => i18n.global.t(key, values);
  const te = (key) => i18n.global.te(key);
  const locale = computed(() =>
    typeof i18n.global.locale === "string"
      ? i18n.global.locale
      : i18n.global.locale.value,
  );
  const activeTab = ref("shops");
  const activeModule = ref("shopEditor");
  const shopSubtab = ref("profile");
  const warehouseTab = ref("items");
  const density = ref("compact");
  const offerQuery = ref("");
  const offerType = ref("");
  const offerSort = ref("name");
  const offerTargetId = ref(null);
  const offerSelectionBusy = ref(false);
  const suggestionsOpen = ref(true);
  const suggestionOperations = reactive({});
  const addingAllSuggestions = ref(false);
  const catalogQuery = ref("");
  const catalogType = ref("");
  const catalogMode = ref("templates");
  const iconPickerOpen = ref(false);
  const iconPickerTarget = ref("template");
  const templateIconManuallySelected = ref(false);
  const templateAttributeToAdd = ref("");
  const instanceAttributeToAdd = ref("");
  const warehouseQuery = ref("");
  const instanceLocationFilter = ref("unassigned");
  const warehouseTargetId = ref(null);
  const offerSelection = reactive([]);
  const warehouseSelection = reactive([]);
  const transferSourceId = ref(null);
  const transferTargetId = ref(null);
  const transferQuery = ref("");
  const deferredOfferQuery = useDeferredValue(offerQuery);
  const deferredCatalogQuery = useDeferredValue(catalogQuery);
  const deferredWarehouseQuery = useDeferredValue(warehouseQuery);
  const deferredTransferQuery = useDeferredValue(transferQuery);
  const transferSelection = reactive([]);
  const transferSaving = ref(false);
  const stockPreview = ref([]);
  const detailItem = ref(null);
  const duplicateMode = ref("");
  const confirmDeleteShop = ref(false);
  const activeDraft = ref(true);
  const profileDraft = reactive({});
  const templateDraft = reactive(emptyTemplate());
  const instanceDraft = reactive(emptyInstance());
  const stackInstanceDraft = reactive(emptyStackInstance());
  const dictionaryDraftState = reactive({});
  const workspaceReady = ref(false);
  const workspaceError = ref("");
  const ledgerItems = ref([]);
  const selectedLedgerEntry = ref(null);
  const ledgerPagination = reactive({
    page: 1,
    pageSize: 50,
    total: 0,
    pageCount: 1,
    hasPreviousPage: false,
    hasNextPage: false,
  });
  const ledgerFilters = reactive({
    item: "",
    transactionType: "",
  });
  let hydratingInstance = false;
  let hydratingStackInstance = false;
  const shopState = computed(() => store.state.shop);
  const shops = computed(() => shopState.value.shops || []);
  const shopById = useCollectionIndex(shops, "id");
  Object.assign(deps, {
    emptyTemplate,
    emptyInstance,
    emptyStackInstance,
    tabs,
    catalogModes,
    instanceLocationFilters,
    shopSubtabs,
    legalOptions,
    lateMedievalTypeSuggestions,
    wealthOptions,
    reputationOptions,
    seasonOptions,
    profileLocationOptions,
    shopProfileArchetypes,
    itemClasses,
    store,
    router,
    route,
    t,
    te,
    locale,
    activeTab,
    activeModule,
    shopSubtab,
    warehouseTab,
    density,
    offerQuery,
    offerType,
    offerSort,
    offerTargetId,
    offerSelectionBusy,
    suggestionsOpen,
    suggestionOperations,
    addingAllSuggestions,
    catalogQuery,
    catalogType,
    catalogMode,
    iconPickerOpen,
    iconPickerTarget,
    templateIconManuallySelected,
    templateAttributeToAdd,
    instanceAttributeToAdd,
    warehouseQuery,
    instanceLocationFilter,
    warehouseTargetId,
    offerSelection,
    warehouseSelection,
    transferSourceId,
    transferTargetId,
    transferQuery,
    deferredOfferQuery,
    deferredCatalogQuery,
    deferredWarehouseQuery,
    deferredTransferQuery,
    transferSelection,
    transferSaving,
    stockPreview,
    detailItem,
    duplicateMode,
    confirmDeleteShop,
    activeDraft,
    profileDraft,
    templateDraft,
    instanceDraft,
    stackInstanceDraft,
    dictionaryDraftState,
    workspaceReady,
    workspaceError,
    ledgerItems,
    selectedLedgerEntry,
    ledgerPagination,
    ledgerFilters,
    hydratingInstance,
    hydratingStackInstance,
    shopState,
    shops,
    shopById,
  });
  exposeMutable(deps, {
    hydratingInstance: {
      get: () => hydratingInstance,
      set: (value) => {
        hydratingInstance = value;
      },
    },
    hydratingStackInstance: {
      get: () => hydratingStackInstance,
      set: (value) => {
        hydratingStackInstance = value;
      },
    },
  });
};
