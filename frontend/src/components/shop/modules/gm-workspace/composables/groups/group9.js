import { shopApiClient, createShopApiConfig } from "@/lib/trade/shopApiClient";
import { watch, onMounted, onBeforeUnmount } from "vue";
import { onBeforeRouteLeave } from "vue-router";
import { setIconMetadataOverrides } from "@/lib/trade/iconMetadataRegistry";
import {
  COC_CURRENCY_DEFINITIONS,
  GENERIC_CURRENCY_DEFINITION,
  WFRP_CURRENCY_DEFINITIONS,
  decomposeCurrencyAmount,
  localizedCurrencyLabel,
} from "@/lib/trade/currency";

const LEDGER_CURRENCIES = [
  ...WFRP_CURRENCY_DEFINITIONS,
  ...COC_CURRENCY_DEFINITIONS,
  GENERIC_CURRENCY_DEFINITION,
];
export const installWorkspaceGroup9 = (deps) => {
  function ledgerCurrencyDefinition(currencyCode) {
    const code = String(currencyCode || "").toLowerCase();
    return LEDGER_CURRENCIES.find((definition) => definition.code === code);
  }
  function ledgerCurrencyLabel(currencyCode) {
    const code = String(currencyCode || "").toLowerCase();
    if (code === "brass")
      return deps.locale.value.startsWith("pl") ? "Mosiądz" : "Brass";
    if (code === "mixed")
      return deps.locale.value.startsWith("pl") ? "Mieszane" : "Mixed";
    const definition = ledgerCurrencyDefinition(code);
    return definition
      ? localizedCurrencyLabel(definition, deps.locale.value)
      : currencyCode || "—";
  }
  function formatLedgerAmount(value, currencyCode) {
    const numeric = Number(value || 0);
    const prefix = numeric < 0 ? "−" : "";
    const code = String(currencyCode || "").toLowerCase();
    if (code === "brass") return `${numeric} br`;
    const definition = ledgerCurrencyDefinition(code);
    if (!definition || code === "mixed") return String(numeric);
    const amounts = decomposeCurrencyAmount(Math.abs(numeric), definition);
    const entries = definition.units.map((unit) => ({
      amount: amounts[unit.code] || 0,
      symbol: deps.locale.value.startsWith("pl")
        ? unit.symbolPl || unit.symbolEn
        : unit.symbolEn || unit.symbolPl,
    }));
    const nonZero = entries.filter((item) => item.amount > 0);
    const formatted = (nonZero.length ? nonZero : entries.slice(-1))
      .map((item) => `${item.amount} ${item.symbol || ""}`.trim())
      .join(" ");
    return `${prefix}${formatted}`;
  }
  function formatLedgerTotal(entry) {
    const value =
      Number(entry?.finalPrice || 0) *
      Math.max(1, Number(entry?.quantity || 0));
    return formatLedgerAmount(value, entry?.currency);
  }
  function ledgerLineItems(entry) {
    const names = String(entry?.itemName || "")
      .split(/\s*,\s*/u)
      .map((name) => name.trim())
      .filter(Boolean);
    const snapshotItems = Array.isArray(entry?.conditionsSnapshot?.items)
      ? entry.conditionsSnapshot.items
      : [];
    const responseItems = Array.isArray(entry?.response?.items)
      ? entry.response.items
      : [];
    const sourceItems = snapshotItems.length ? snapshotItems : responseItems;
    const lines = sourceItems.map((item, index) => {
      const templateId = Number(item?.templateId || item?.template_id || 0);
      const template = deps.templateById.value.get(templateId) || {};
      const name =
        names[index] ||
        template.NAME ||
        template.name ||
        (templateId
          ? `#${templateId}`
          : deps.t("shop.workspace.ledgerDrawer.item"));
      const quantity = Math.max(0, Number(item?.quantity || 0));
      const unitPrice = Number(item?.unitPrice ?? item?.unit_price ?? 0);
      const currency = String(item?.currency || entry?.currency || "generic");
      return {
        ...template,
        ...item,
        ID: templateId || template.ID,
        NAME: name,
        name,
        templateId,
        quantity,
        unitPrice,
        basePrice: Number(item?.basePrice ?? template.PRIZE ?? 0),
        currency,
        lineTotal: unitPrice * Math.max(1, quantity),
      };
    });

    names.slice(lines.length).forEach((name, index) => {
      const isOnlyItem = names.length === 1;
      lines.push({
        NAME: name,
        name,
        templateId: index === 0 ? Number(entry?.itemTemplateId || 0) : 0,
        quantity: isOnlyItem ? Math.max(0, Number(entry?.quantity || 0)) : null,
        unitPrice: isOnlyItem ? Number(entry?.finalPrice || 0) : null,
        basePrice: isOnlyItem ? Number(entry?.basePrice || 0) : null,
        currency: String(entry?.currency || "generic"),
        lineTotal: isOnlyItem
          ? Number(entry?.finalPrice || 0) *
            Math.max(1, Number(entry?.quantity || 0))
          : null,
      });
    });

    if (!lines.length) {
      const name =
        entry?.itemName || deps.t("shop.workspace.ledgerDrawer.noItemName");
      lines.push({
        NAME: name,
        name,
        templateId: Number(entry?.itemTemplateId || 0),
        quantity: Math.max(0, Number(entry?.quantity || 0)),
        unitPrice: Number(entry?.finalPrice || 0),
        basePrice: Number(entry?.basePrice || 0),
        currency: String(entry?.currency || "generic"),
        lineTotal:
          Number(entry?.finalPrice || 0) *
          Math.max(1, Number(entry?.quantity || 0)),
      });
    }
    return lines;
  }
  function ledgerPrimaryItem(entry) {
    return ledgerLineItems(entry)[0] || {};
  }
  function ledgerAdditionalItemCount(entry) {
    return Math.max(0, ledgerLineItems(entry).length - 1);
  }
  function openLedgerEntry(entry) {
    deps.selectedLedgerEntry.value = entry || null;
  }
  function closeLedgerEntry() {
    deps.selectedLedgerEntry.value = null;
  }
  function formatLedgerJson(value) {
    return JSON.stringify(value || {}, null, 2);
  }
  function ledgerStatusLabel(status) {
    const key = `shop.workspace.ledgerStatus.${String(status || "").toLowerCase()}`;
    return deps.te(key) ? deps.t(key) : status || "—";
  }
  function ledgerStatusIcon(status) {
    return (
      {
        executed: "●",
        failed: "!",
        reversed: "↶",
        redone: "↷",
        corrected: "◆",
      }[String(status || "").toLowerCase()] || "·"
    );
  }
  function ledgerStatusClass(status) {
    return `history-table__status--${String(status || "unknown").toLowerCase()}`;
  }
  function exportShopJson() {
    const payload = {
      schemaVersion: 1,
      exportedAt: new Date().toISOString(),
      campaign: deps.shopState.value.context,
      shop: deps.activeShop.value,
      profile: deps.profileDraft,
      offer: deps.activeOffer.value,
    };
    deps.download(
      `shop-${deps.activeShopId.value}.json`,
      "application/json",
      JSON.stringify(payload, null, 2),
    );
  }
  async function loadLedger(page = 1) {
    const response = await shopApiClient.listTradeLedger(
      createShopApiConfig({
        campaignId: deps.shopState.value.campaignId,
        ownerCode: deps.profileDraft.ownerCode,
      }),
      {
        ...deps.ledgerFilters,
        page,
        pageSize: deps.ledgerPagination.pageSize,
      },
    );
    deps.ledgerItems.value = response?.items || [];
    deps.selectedLedgerEntry.value = null;
    Object.assign(deps.ledgerPagination, response?.pagination || {});
  }
  async function exportLedgerCsv() {
    const config = createShopApiConfig({
      campaignId: deps.shopState.value.campaignId,
      ownerCode: deps.profileDraft.ownerCode,
    });
    const first = await shopApiClient.listTradeLedger(config, {
      ...deps.ledgerFilters,
      page: 1,
      pageSize: 200,
    });
    const allItems = [...(first?.items || [])];
    const pageCount = Number(first?.pagination?.pageCount || 1);
    for (let page = 2; page <= pageCount; page += 1) {
      const response = await shopApiClient.listTradeLedger(config, {
        ...deps.ledgerFilters,
        page,
        pageSize: 200,
      });
      allItems.push(...(response?.items || []));
    }
    const quote = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;
    const rows = [
      [
        "id",
        "date",
        "shop",
        "actor",
        "type",
        "status",
        "seller",
        "buyer",
        "item",
        "quantity",
        "total",
        "currency",
      ],
      ...allItems.map((entry) => [
        entry.id,
        entry.createdAt,
        entry.shopName || entry.shopId,
        entry.actorName || entry.actorId,
        entry.transactionType,
        entry.status,
        entry.sellerName || entry.sellerId,
        entry.buyerName || entry.buyerId,
        entry.itemName,
        entry.quantity,
        Number(entry.finalPrice || 0) *
          Math.max(1, Number(entry.quantity || 0)),
        entry.currency,
      ]),
    ];
    deps.download(
      "shop-ledger.csv",
      "text/csv;charset=utf-8",
      `\uFEFF${rows.map((row) => row.map(quote).join(",")).join("\n")}`,
    );
  }
  function formatDate(value) {
    return value
      ? new Intl.DateTimeFormat(deps.locale.value, {
          dateStyle: "short",
          timeStyle: "short",
        }).format(new Date(value))
      : "—";
  }
  function beforeUnload(event) {
    if (Object.values(deps.formStatus.value).includes("dirty")) {
      event.preventDefault();
      event.returnValue = "";
    }
  }
  watch(deps.activeShopId, deps.hydrateProfile);
  watch(deps.transferSourceId, (value, previous) => {
    if (String(value) !== String(previous)) deps.clearTransferSelection();
    if (Number(value) === Number(deps.transferTargetId.value)) {
      deps.transferTargetId.value = null;
    }
  });
  watch(
    deps.instanceDraft,
    () => {
      if (!deps.hydratingInstance && deps.catalogMode.value === "instances") {
        deps.store.commit("shop/setFormStatus", {
          scope: "instance",
          status: "dirty",
        });
      }
    },
    {
      deep: true,
      flush: "post",
    },
  );
  watch(
    deps.stackInstanceDraft,
    () => {
      if (!deps.hydratingStackInstance && deps.stackInstanceDraft.id) {
        deps.store.commit("shop/setFormStatus", {
          scope: "stackInstance",
          status: "dirty",
        });
      }
    },
    {
      deep: true,
      flush: "post",
    },
  );
  watch(deps.activeTab, (value) => {
    if (value === "history") loadLedger(1);
    if (value !== "history") closeLedgerEntry();
    if (value === "transfer") initializeTransferContainers();
  });
  function initializeTransferContainers() {
    if (!deps.transferSourceId.value) {
      deps.transferSourceId.value =
        deps.defaultStackContainerId.value ||
        deps.warehouseContainerOptions.value[0]?.value;
    }
    if (!deps.transferTargetId.value) {
      const activeShopContainer = (
        deps.shopState.value.containerState?.containers || []
      ).find(
        (entry) =>
          String(entry.container_type || "").toUpperCase() === "SHOP" &&
          Number(entry.shop_id) === Number(deps.activeShopId.value),
      );
      deps.transferTargetId.value =
        activeShopContainer &&
        Number(activeShopContainer.id) !== Number(deps.transferSourceId.value)
          ? Number(activeShopContainer.id)
          : deps.transferTargetOptions.value[0]?.value || null;
    }
  }
  async function initializeWorkspace() {
    deps.workspaceError.value = "";
    const result = await deps.store.dispatch("shop/loadTradingData", {
      campaignId: Number(deps.route.params.campaignId),
      ownerCode: deps.store.state.shop.shopEditorState?.ownerCode || "BG1",
      viewMode: "management",
    });
    if (!result?.ok) {
      deps.workspaceError.value = deps.t("shop.tradeModal.loadError");
      return;
    }
    if (!deps.store.state.shop.permissions?.isGm) {
      deps.router.replace({
        name: "forbidden",
      });
      return;
    }
    deps.store.commit("shop/setIsGM", true);
    try {
      const response = await shopApiClient.getIconMetadata(
        createShopApiConfig({ campaignId: deps.shopState.value.campaignId }),
      );
      setIconMetadataOverrides(response?.metadata || []);
    } catch (error) {
      setIconMetadataOverrides([]);
    }
    deps.hydrateProfile();
    initializeTransferContainers();
    deps.workspaceReady.value = true;
  }
  onMounted(async () => {
    await initializeWorkspace();
    window.addEventListener("beforeunload", beforeUnload);
  });
  watch(
    () => deps.route.params.campaignId,
    async (nextCampaignId, previousCampaignId) => {
      if (Number(nextCampaignId) === Number(previousCampaignId)) return;
      deps.workspaceReady.value = false;
      await initializeWorkspace();
    },
  );
  onBeforeUnmount(() => {
    window.removeEventListener("beforeunload", beforeUnload);
    deps.store.commit("shop/setIsGM", false);
  });
  onBeforeRouteLeave(
    () =>
      !Object.values(deps.formStatus.value).includes("dirty") ||
      window.confirm(deps.t("shop.workspace.unsavedQuestion")),
  );
  Object.assign(deps, {
    exportShopJson,
    loadLedger,
    exportLedgerCsv,
    formatDate,
    formatLedgerTotal,
    formatLedgerAmount,
    ledgerCurrencyLabel,
    ledgerLineItems,
    ledgerPrimaryItem,
    ledgerAdditionalItemCount,
    openLedgerEntry,
    closeLedgerEntry,
    formatLedgerJson,
    ledgerStatusLabel,
    ledgerStatusIcon,
    ledgerStatusClass,
    beforeUnload,
    initializeTransferContainers,
    initializeWorkspace,
  });
};
