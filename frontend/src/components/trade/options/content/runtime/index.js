import ShopView from "@/components/shop/views/ShopView.vue";
import TemplateEditor from "@/components/shop/views/TemplateEditor.vue";
import DefaultStackView from "@/components/shop/views/DefaultStackView.vue";
import TrashBinView from "@/components/shop/views/TrashBinView.vue";
import ShopEditor from "@/components/shop/views/ShopEditor.vue";
import AssortmentManager from "@/components/shop/views/AssortmentManager.vue";
import QuickTransferPreview from "@/components/shop/views/QuickTransferPreview.vue";
import FlankActionButtons from "@/components/shop/common/FlankActionButtons.vue";
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";
import TradeModalDialogs from "@/components/trade/TradeModalDialogs.vue";
import FieldEditDialog from "@/components/shop/common/dialogs/FieldEditDialog.vue";
import ItemDetailDialog from "@/components/shop/common/dialogs/ItemDetailDialog.vue";
import SuggestionDetailDialog from "@/components/shop/common/dialogs/SuggestionDetailDialog.vue";
import AssortmentMergeDialog from "@/components/shop/common/dialogs/AssortmentMergeDialog.vue";
import IconClassDialog from "@/components/shop/common/dialogs/IconClassDialog.vue";
import ShopActivationDialog from "@/components/shop/common/dialogs/ShopActivationDialog.vue";
import OwnerOptionDialog from "@/components/shop/common/dialogs/OwnerOptionDialog.vue";
import ViewSettingsDialog from "@/components/shop/common/dialogs/ViewSettingsDialog.vue";
import WeaponStatsDialog from "@/components/shop/common/dialogs/WeaponStatsDialog.vue";
import { tradeModalContextKey } from "@/components/shop/shopContext";
import iconTaxonomy from "@/data/trade/iconTaxonomy.json";
import {
  inventoryIconClasses,
  inventoryIconMetadataMap,
} from "@/data/trade/inventoryIconMetadata";
import { markRaw } from "vue";
import { createContentRuntimePart1 } from "./part1";

const runtime = {
  ShopView,
  TemplateEditor,
  DefaultStackView,
  TrashBinView,
  ShopEditor,
  AssortmentManager,
  QuickTransferPreview,
  FlankActionButtons,
  CurrencyDisplay,
  TradeModalDialogs,
  FieldEditDialog,
  ItemDetailDialog,
  SuggestionDetailDialog,
  AssortmentMergeDialog,
  IconClassDialog,
  ShopActivationDialog,
  OwnerOptionDialog,
  ViewSettingsDialog,
  WeaponStatsDialog,
  tradeModalContextKey,
  iconTaxonomy,
  inventoryIconClasses,
  inventoryIconMetadataMap,
  markRaw,
};
Object.assign(runtime, createContentRuntimePart1(runtime));

export default runtime;
