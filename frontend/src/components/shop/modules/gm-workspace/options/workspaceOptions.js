export const tabs = [
  {
    id: "templates",
    label: "shop.modules.templatesTitle",
    title: "shop.modules.templates",
    target: "catalog",
  },
  {
    id: "inventory",
    label: "shop.modules.defaultStackTitle",
    title: "shop.modules.defaultStack",
    target: "warehouse",
    warehouseTab: "items",
  },
  {
    id: "trash",
    label: "shop.modules.trashTitle",
    title: "shop.modules.trash",
    target: "warehouse",
    warehouseTab: "archive",
  },
  {
    id: "shopEditor",
    label: "shop.modules.shopEditorTitle",
    title: "shop.modules.shopEditor",
    target: "shops",
    shopSubtab: "profile",
  },
  {
    id: "assortment",
    label: "shop.modules.assortmentTitle",
    title: "shop.modules.assortment",
    target: "shops",
    shopSubtab: "offer",
  },
  {
    id: "quickTransfer",
    label: "shop.modules.quickTransferTitle",
    title: "shop.modules.quickTransfer",
    target: "transfer",
  },
  {
    id: "transactionLedger",
    label: "shop.modules.transactionLedger",
    target: "history",
  },
];

export const catalogModes = [
  { id: "templates", label: "shop.workspace.catalogModes.templates" },
  { id: "instances", label: "shop.workspace.catalogModes.instances" },
  { id: "dictionaries", label: "shop.workspace.catalogModes.dictionaries" },
];

export const instanceLocationFilters = [
  { id: "unassigned", label: "shop.workspace.instanceStack.unassigned" },
  { id: "all", label: "shop.workspace.instanceStack.all" },
  { id: "character", label: "shop.workspace.instanceStack.characters" },
  { id: "shop", label: "shop.workspace.instanceStack.shops" },
  { id: "trash", label: "shop.workspace.instanceStack.trash" },
];

export const shopSubtabs = [
  { id: "profile", label: "shop.workspace.subtabs.profile" },
  { id: "offer", label: "shop.workspace.subtabs.offer" },
  { id: "prices", label: "shop.workspace.subtabs.prices" },
];

export const legalOptions = ["legal", "licensed", "mixed", "grey", "illegal"];
export const wealthOptions = [
  "nedzny",
  "biedny",
  "standard",
  "bogaty",
  "elitarny",
  "luksusowy",
];
export const reputationOptions = [
  "fatalna",
  "zla",
  "podejrzana",
  "neutralna",
  "dobra",
  "znakomita",
];
export const seasonOptions = [
  "caloroczny",
  "sezonowy",
  "wiosna",
  "lato",
  "jesien",
  "zima",
  "jarmark",
];

export const itemClasses = [
  "ALCHEMY",
  "ANIMAL",
  "ARMAMENT",
  "ARMOR",
  "CLOTH",
  "CUTLERY",
  "FOOD",
  "FORAGE",
  "GADGET",
  "JEWELLERY",
  "MAGIC",
  "MISC",
  "POTION",
  "POWDER",
  "STATIONERY",
  "TOOL",
  "WEAPON",
];
