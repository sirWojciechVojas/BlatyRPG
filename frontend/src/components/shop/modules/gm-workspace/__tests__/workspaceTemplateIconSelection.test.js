import { reactive, ref } from "vue";
import { describe, expect, it, vi } from "vitest";
import { installWorkspaceGroup7 } from "@/components/shop/modules/gm-workspace/composables/groups/group7";

const emptyTemplate = () => ({
  ID: null,
  NAME: "",
  IMG_CLASS: "v0001",
  WEAPON: {},
});

const createDeps = () => {
  const templateDraft = reactive({
    ID: 7,
    NAME: "Eliksir leczenia",
    ITEM_CLASS: "ALCHEMY",
    ITEM_GENRE: "POTION",
    IMG_CLASS: "v1074",
    WEAPON: {},
  });
  const store = {
    commit: vi.fn(),
    dispatch: vi.fn((_action, payload) => Promise.resolve({ ...payload })),
  };
  const deps = {
    templateDraft,
    templateIconManuallySelected: ref(false),
    iconPickerTarget: ref("template"),
    iconPickerOpen: ref(false),
    emptyTemplate,
    displayCurrencyCode: (code) => code || "generic",
    store,
    markTemplateDirty: vi.fn(),
  };
  installWorkspaceGroup7(deps);
  return { deps, store, templateDraft };
};

describe("workspace template icon selection", () => {
  it("keeps a picker choice through template saving and rehydration", async () => {
    const { deps, store, templateDraft } = createDeps();

    deps.setSelectedIcon("v1089");
    expect(templateDraft.IMG_CLASS).toBe("v1089");
    expect(deps.templateIconManuallySelected.value).toBe(true);

    const saved = await deps.saveTemplate();

    expect(store.dispatch).toHaveBeenCalledWith(
      "shop/saveTemplateRecord",
      expect.objectContaining({ IMG_CLASS: "v1089" }),
    );
    expect(saved.IMG_CLASS).toBe("v1089");
    expect(templateDraft.IMG_CLASS).toBe("v1089");
    expect(deps.templateIconManuallySelected.value).toBe(true);
  });

  it("treats a persisted valid icon as explicit when reopening a template", () => {
    const { deps } = createDeps();

    deps.editTemplate({
      ID: 7,
      NAME: "Eliksir leczenia",
      IMG_CLASS: "v1089",
      WEAPON: {},
    });
    deps.refreshTemplateIcon();

    expect(deps.templateDraft.IMG_CLASS).toBe("v1089");
  });
});
