import { readFileSync, readdirSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const componentsPath = resolve(
  process.cwd(),
  "src/components/shop/modules/gm-workspace/components",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/shop/modules/gm-workspace/styles",
);

const compositions = {
  "ShopWorkspaceShops.vue": [
    "ShopWorkspaceOffer",
    "ShopWorkspacePricing",
    "ShopWorkspaceProfile",
  ],
  "ShopWorkspaceCatalog.vue": [
    "ShopWorkspaceCatalogBrowser",
    "ShopWorkspaceDictionaries",
    "ShopWorkspaceInstanceEditor",
    "ShopWorkspaceTemplateEditor",
  ],
  "ShopWorkspaceWarehouse.vue": [
    "ShopWorkspaceArchive",
    "ShopWorkspaceWarehouseItems",
    "ShopWorkspaceWarehouseToolbar",
  ],
};

describe("Shop GM workspace composition", () => {
  it("keeps the catalog list and editor in a 5/7 split layout", () => {
    const catalogSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceCatalog.vue"),
      "utf8",
    );
    const layoutSource = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.4.css"),
      "utf8",
    );

    expect(catalogSource).toContain("catalog-workspace");
    expect(layoutSource).toContain(
      "grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);",
    );
  });

  it("exposes every icon metadata term in the tabbed dictionary editor", () => {
    const dictionarySource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceDictionaries.vue"),
      "utf8",
    );
    const workspaceSource = readFileSync(
      resolve(
        process.cwd(),
        "src/components/shop/modules/gm-workspace/composables/groups/group2.js",
      ),
      "utf8",
    );
    const dictionaryStyles = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.5.css"),
      "utf8",
    );
    const dictionaryLayout = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.4.css"),
      "utf8",
    );

    expect(dictionarySource).toContain("dictionary-group-tabs");
    expect(dictionarySource).toContain("dictionary-new-entry");
    expect(dictionarySource).toContain("parentCategory");
    expect(dictionarySource).toContain("codeUnlocked");
    expect(dictionarySource).toContain("<colgroup>");
    expect(dictionarySource).toContain("dictionary-multi-select");
    expect(dictionarySource).toContain('type="checkbox"');
    expect(dictionarySource).toContain("ItemMechanicsEditor");
    expect(dictionarySource).toContain("mechanicsSupported");
    expect(workspaceSource).toContain('key: "icon_categories"');
    expect(workspaceSource).toContain('key: "icon_subcategories"');
    expect(workspaceSource).toContain('key: "classes"');
    expect(workspaceSource).toContain('key: "genres"');
    expect(workspaceSource).toContain('key: "attributes"');
    expect(dictionaryStyles).toContain("table-layout: fixed;");
    expect(dictionaryStyles).toContain("width: 25%;");
    expect(dictionaryStyles).toContain("scrollbar-gutter: stable;");
    expect(dictionaryStyles).toContain("color-scheme: dark;");
    expect(dictionaryStyles).toContain(
      "grid-template-columns: repeat(3, minmax(0, 1fr));",
    );
    expect(dictionaryLayout).toContain("gap: 0;");
    expect(dictionaryLayout).toContain("padding: 0;");
  });

  it("uses the empty template area for inheritable item mechanics", () => {
    const templateSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceTemplateEditor.vue"),
      "utf8",
    );
    const mechanicsSource = readFileSync(
      resolve(
        process.cwd(),
        "src/components/shop/common/ItemMechanicsEditor.vue",
      ),
      "utf8",
    );
    const mechanicsStyles = readFileSync(
      resolve(
        process.cwd(),
        "src/components/shop/common/ItemMechanicsEditor.css",
      ),
      "utf8",
    );

    expect(templateSource).toContain("ItemMechanicsEditor");
    expect(templateSource).toContain("templateDraft.MECHANICS");
    expect(templateSource).toContain("templateInheritedMechanics");
    expect(templateSource).toContain("item-mechanics-weapon");
    expect(mechanicsSource).toContain("item-mechanics-editor__effects");
    expect(mechanicsSource).toContain("handlerKey");
    expect(mechanicsSource).toContain("overrideInherited");
    expect(mechanicsStyles).toContain("color-scheme: dark;");
    expect(mechanicsStyles).toContain("select option");
  });

  it("keeps catalog columns and split currency units aligned", () => {
    const catalogSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceCatalogBrowser.vue"),
      "utf8",
    );
    const listStyles = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.2.css"),
      "utf8",
    );

    expect(catalogSource).toContain(
      ':currency-code="displayCurrencyCode(item.CURRENCY)"',
    );
    expect(listStyles).toContain(".catalog-list-panel .item-list__row");
    expect(listStyles).toContain("14rem 2rem;");
    expect(listStyles).toContain(
      "grid-template-columns: repeat(3, minmax(0, 1fr));",
    );
  });

  it("uses aligned offer columns and exposes bulk selection actions", () => {
    const offerSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceOffer.vue"),
      "utf8",
    );
    const offerStyles = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.3.css"),
      "utf8",
    );

    expect(offerSource).toContain("offer-table-head");
    expect(offerSource).toContain("offer-classification");
    expect(offerSource).toContain("offer-quantity");
    expect(offerSource).toContain("offer-charge");
    expect(offerSource).toContain("toggleAllOfferItems");
    expect(offerSource).toContain("moveOfferSelectionToTrash");
    expect(offerStyles).toContain(
      ".offer-workspace__instances .item-list__row",
    );
    expect(offerStyles).toContain("4rem 14rem 4rem 4rem 2rem;");
  });

  it("keeps the default stack list and editor in a 5/7 split layout", () => {
    const warehouseSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceWarehouseItems.vue"),
      "utf8",
    );
    const layoutSource = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.5.css"),
      "utf8",
    );

    expect(warehouseSource).toContain(
      "stack-instance-layout stack-instance-layout--editing",
    );
    expect(warehouseSource).toContain("stack-instance-editor--empty");
    expect(warehouseSource).toContain("CurrencyDisplay");
    expect(warehouseSource).toContain("stack-instance-price");
    expect(warehouseSource).toContain(
      "item.PERSONAL_COST ?? item.ACTIVE_PRICE ?? item.PRIZE ?? 0",
    );
    expect(layoutSource).toContain(".shop-workspace__body--warehouse");
    expect(layoutSource).toContain("flex: 1 1 auto;");
    expect(layoutSource).toContain(".stack-instance-layout .item-list__row");
    expect(layoutSource).toContain(".stack-instance-price");
    expect(layoutSource).toContain(
      "grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);",
    );
  });

  it("keeps the price dropdown outside an implicit form label", () => {
    const denseFieldSource = readFileSync(
      resolve(process.cwd(), "src/components/shop/common/DenseField.vue"),
      "utf8",
    );
    const priceInputSource = readFileSync(
      resolve(process.cwd(), "src/components/shop/common/SystemPriceInput.vue"),
      "utf8",
    );
    const editorFiles = [
      "ShopWorkspaceTemplateEditor.vue",
      "ShopWorkspaceInstanceEditor.vue",
      "ShopWorkspaceWarehouseItems.vue",
    ];

    expect(denseFieldSource).toContain(":is=\"group ? 'div' : 'label'\"");
    expect(priceInputSource).toContain('v-model="selectedCurrencyCode"');
    expect(priceInputSource).toContain(
      'document.addEventListener("pointerdown", closeOnOutsidePointer)',
    );
    editorFiles.forEach((fileName) => {
      const source = readFileSync(resolve(componentsPath, fileName), "utf8");
      expect(source, fileName).toMatch(
        /<DenseField :label="\$t\('shop\.workspace\.item\.price'\)" group>/,
      );
    });
  });

  it("uses valid descendant selectors in global workspace styles", () => {
    const styleFiles = readdirSync(stylesPath).filter((fileName) =>
      fileName.endsWith(".css"),
    );

    styleFiles.forEach((fileName) => {
      const source = readFileSync(resolve(stylesPath, fileName), "utf8");
      expect(source, fileName).not.toContain(":deep(");
    });
  });

  it("uses tabbed profile and pricing panels without nested workspace scrolling", () => {
    const profileSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceProfile.vue"),
      "utf8",
    );
    const pricingSource = readFileSync(
      resolve(
        process.cwd(),
        "src/components/shop/modules/shop-editor/components/ShopEditorPricingSection.vue",
      ),
      "utf8",
    );
    const baseStyle = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.1.css"),
      "utf8",
    );

    expect(profileSource).toContain('role="tablist"');
    expect(pricingSource).toContain("activePricingPanel");
    expect(pricingSource).not.toContain("shop-editor-pricing__fold");
    expect(baseStyle).toMatch(
      /\.shop-workspace__body--shops\s*\{[^}]*overflow:\s*hidden;/s,
    );
    expect(baseStyle).toMatch(/\.shop-workspace\s*\{[^}]*position:\s*fixed;/s);
  });

  it("keeps shop controls beside the existing subtabs without an extra toolbar", () => {
    const headerSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceHeader.vue"),
      "utf8",
    );
    const shopsSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceShops.vue"),
      "utf8",
    );
    const profileSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceProfile.vue"),
      "utf8",
    );
    const baseStyle = readFileSync(
      resolve(stylesPath, "ShopGmWorkspace.1.css"),
      "utf8",
    );

    expect(headerSource).not.toContain("shop-workspace__shop-picker");
    expect(shopsSource).toContain("shop-workspace__subnav-actions");
    expect(shopsSource).toContain("ShopWorkspaceCreateShopDialog");
    expect(headerSource).not.toContain("CompactToolbar");
    expect(headerSource).not.toContain("shop-workspace__sticky");
    expect(profileSource).not.toContain("createNewShop");
    expect(baseStyle).toContain("height: calc(100dvh - 5.45rem);");
  });

  it("does not request pricing for a missing shop or a signboard-only edit", () => {
    const workspaceSource = readFileSync(
      resolve(
        process.cwd(),
        "src/components/shop/modules/gm-workspace/composables/groups/group2.js",
      ),
      "utf8",
    );
    const previewSource = readFileSync(
      resolve(
        process.cwd(),
        "src/components/shop/composables/shop-editor-view-model/part1.segment2.js",
      ),
      "utf8",
    );

    expect(workspaceSource).toContain(
      "if (!Number.isInteger(shopId) || shopId <= 0) return null;",
    );
    expect(previewSource).toContain("previewPricingSignature");
    expect(previewSource).not.toContain(
      "() => JSON.stringify(previewProfile.value)",
    );
  });

  it("keeps both signboard roll buttons outside implicit labels", () => {
    const createDialogSource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceCreateShopDialog.vue"),
      "utf8",
    );
    const identitySource = readFileSync(
      resolve(componentsPath, "ShopWorkspaceProfileIdentity.vue"),
      "utf8",
    );

    expect(createDialogSource).toMatch(
      /:error="nameError"\s+group\s+required[\s\S]*@click="rollShopName"/,
    );
    expect(identitySource).toMatch(
      /:error="profileFieldError\('signboardName'\)"\s+group\s+required[\s\S]*@click="rollProfileSignboard"/,
    );
  });

  it("derives the HMR websocket host and port from the browser location", () => {
    const configSource = readFileSync(
      resolve(process.cwd(), "vue.config.js"),
      "utf8",
    );

    expect(configSource).toContain("auto://0.0.0.0:0");
    expect(configSource).not.toContain(
      'process.env.WDS_SOCKET_HOST || "localhost"',
    );
  });

  it.each(Object.entries(compositions))(
    "registers all content rendered by %s",
    (fileName, componentNames) => {
      const source = readFileSync(resolve(componentsPath, fileName), "utf8");

      componentNames.forEach((componentName) => {
        expect(source).toContain(`import ${componentName} from`);
        expect(source).toMatch(
          new RegExp(`components:[\\s\\S]*\\b${componentName}\\b`),
        );
      });
    },
  );
});
