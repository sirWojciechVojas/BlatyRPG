<template>
  <section class="compendium-document" @click="handleClick">
    <EditorContent :editor="editor" class="compendium-document__content" />
    <ul v-if="pdfAssets.length" class="compendium-document__assets">
      <li v-for="asset in pdfAssets" :key="asset.id">
        <span>{{ asset.name }}</span>
        <button type="button" @click="openAsset(asset)">
          {{ $t("vtt.table.compendium.openPdf") }}
        </button>
      </li>
    </ul>
    <object
      v-if="activePdfUrl"
      class="compendium-document__pdf"
      :data="activePdfUrl"
      type="application/pdf"
    >
      <a :href="activePdfUrl" :download="activePdfName">{{ activePdfName }}</a>
    </object>
  </section>
</template>

<script>
import { Node, mergeAttributes } from "@tiptap/core";
import Image from "@tiptap/extension-image";
import Link from "@tiptap/extension-link";
import StarterKit from "@tiptap/starter-kit";
import { Editor, EditorContent } from "@tiptap/vue-3";
import { compendiumApiClient } from "@/lib/compendium/compendiumApiClient";

const CompendiumImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      assetId: { default: null },
      alt: { default: "" },
    };
  },
});

const CompendiumMention = Node.create({
  name: "compendiumMention",
  group: "inline",
  inline: true,
  atom: true,
  addAttributes() {
    return { entryId: { default: null }, label: { default: "" } };
  },
  renderHTML({ HTMLAttributes }) {
    return [
      "button",
      mergeAttributes(HTMLAttributes, {
        type: "button",
        class: "compendium-mention",
        "data-entry-id": HTMLAttributes.entryId,
      }),
      `@${HTMLAttributes.label || "…"}`,
    ];
  },
});

const Attachment = Node.create({
  name: "attachment",
  group: "block",
  atom: true,
  addAttributes() {
    return { assetId: { default: null }, name: { default: "" } };
  },
  renderHTML({ HTMLAttributes }) {
    return [
      "div",
      mergeAttributes(HTMLAttributes, { class: "compendium-attachment" }),
      `PDF: ${HTMLAttributes.name || ""}`,
    ];
  },
});

const clone = (value) =>
  JSON.parse(
    JSON.stringify(value || { type: "doc", content: [{ type: "paragraph" }] }),
  );

const hydrate = (node, sources, labels) => {
  if (!node || typeof node !== "object") return node;
  const result = { ...node, attrs: node.attrs ? { ...node.attrs } : undefined };
  if (result.type === "image") {
    result.attrs.src = sources[Number(result.attrs.assetId)] || "";
  }
  if (result.type === "compendiumMention") {
    result.attrs.label = labels[Number(result.attrs.entryId)] || "…";
  }
  if (Array.isArray(result.content)) {
    result.content = result.content.map((child) =>
      hydrate(child, sources, labels),
    );
  }
  return result;
};

export default {
  name: "CompendiumDocument",
  components: { EditorContent },
  props: {
    content: { type: Object, default: () => ({ type: "doc", content: [] }) },
    assets: { type: Array, default: () => [] },
    mentions: { type: Array, default: () => [] },
    campaignId: { type: [Number, String], default: null },
  },
  emits: ["navigate"],
  data: () => ({
    editor: null,
    assetUrls: {},
    loading: false,
    activePdfUrl: "",
    activePdfName: "compendium.pdf",
  }),
  computed: {
    pdfAssets() {
      return this.assets.filter(
        (asset) => asset.mimeType === "application/pdf",
      );
    },
    mentionLabels() {
      return Object.fromEntries(
        this.mentions.map((item) => [Number(item.id), item.title]),
      );
    },
  },
  watch: {
    content: {
      deep: true,
      handler() {
        this.refresh();
      },
    },
    assets: {
      deep: true,
      handler() {
        this.refresh();
      },
    },
    mentions: {
      deep: true,
      handler() {
        this.refresh();
      },
    },
  },
  mounted() {
    this.editor = new Editor({
      editable: false,
      extensions: [
        StarterKit.configure({ heading: { levels: [1, 2, 3, 4] } }),
        Link.configure({
          HTMLAttributes: { target: "_blank", rel: "noopener noreferrer" },
        }),
        CompendiumImage,
        CompendiumMention,
        Attachment,
      ],
      content: clone(this.content),
    });
    this.refresh();
  },
  beforeUnmount() {
    this.editor?.destroy();
    Object.values(this.assetUrls).forEach((url) => URL.revokeObjectURL(url));
  },
  methods: {
    async refresh() {
      if (!this.editor || this.loading) return;
      this.loading = true;
      try {
        const nextUrls = {};
        await Promise.all(
          this.assets
            .filter((asset) => String(asset.mimeType).startsWith("image/"))
            .map(async (asset) => {
              nextUrls[asset.id] =
                this.assetUrls[asset.id] ||
                URL.createObjectURL(
                  await compendiumApiClient.fetchAssetBlob(
                    asset.id,
                    this.campaignId,
                  ),
                );
            }),
        );
        Object.entries(this.assetUrls).forEach(([id, url]) => {
          if (!nextUrls[id]) URL.revokeObjectURL(url);
        });
        this.assetUrls = nextUrls;
      } catch (_error) {
        this.assetUrls = {};
      } finally {
        this.editor.commands.setContent(
          hydrate(clone(this.content), this.assetUrls, this.mentionLabels),
          { emitUpdate: false },
        );
        this.loading = false;
      }
    },
    handleClick(event) {
      const target = event.target.closest?.("[data-entry-id]");
      if (target) this.$emit("navigate", Number(target.dataset.entryId));
    },
    async openAsset(asset) {
      try {
        const url =
          this.assetUrls[asset.id] ||
          URL.createObjectURL(
            await compendiumApiClient.fetchAssetBlob(asset.id, this.campaignId),
          );
        if (!this.assetUrls[asset.id])
          this.assetUrls = { ...this.assetUrls, [asset.id]: url };
        this.activePdfUrl = url;
        this.activePdfName = asset.name || "compendium.pdf";
      } catch (_error) {
        this.activePdfUrl = "";
      }
    },
  },
};
</script>
