<template>
  <section class="handout-document">
    <EditorContent :editor="editor" class="handout-document__content" />
    <ul v-if="pdfAssets.length" class="handout-document__assets">
      <li v-for="asset in pdfAssets" :key="asset.id">
        <span>{{ asset.name }}</span>
        <button type="button" @click="openAsset(asset)">
          {{ $t("vtt.table.handouts.openPdf") }}
        </button>
      </li>
    </ul>
    <section v-if="activePdfUrl" class="handout-document__pdf-preview">
      <object :data="activePdfUrl" type="application/pdf">
        <a :href="activePdfUrl" :download="activePdfName">
          {{ $t("vtt.table.handouts.downloadPdf") }}
        </a>
      </object>
    </section>
  </section>
</template>

<script>
import { Node, mergeAttributes } from "@tiptap/core";
import Image from "@tiptap/extension-image";
import Link from "@tiptap/extension-link";
import StarterKit from "@tiptap/starter-kit";
import { Editor, EditorContent } from "@tiptap/vue-3";
import { handoutApiClient } from "@/lib/handouts/handoutApiClient";

const HandoutImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      assetId: { default: null },
      alt: { default: "" },
    };
  },
});

const HandoutMention = Node.create({
  name: "mention",
  group: "inline",
  inline: true,
  atom: true,
  addAttributes() {
    return {
      targetType: { default: null },
      targetId: { default: null },
      label: { default: "" },
    };
  },
  renderHTML({ HTMLAttributes }) {
    return [
      "span",
      mergeAttributes(HTMLAttributes, { class: "handout-mention" }),
      `@${HTMLAttributes.label || ""}`,
    ];
  },
});

const HandoutAttachment = Node.create({
  name: "attachment",
  group: "block",
  atom: true,
  addAttributes() {
    return { assetId: { default: null }, name: { default: "" } };
  },
  renderHTML({ HTMLAttributes }) {
    return [
      "div",
      mergeAttributes(HTMLAttributes, { class: "handout-attachment" }),
      "PDF: " + (HTMLAttributes.name || ""),
    ];
  },
});

const copy = (value) =>
  JSON.parse(JSON.stringify(value || { type: "doc", content: [] }));

const replaceImageSources = (node, sources) => {
  if (!node || typeof node !== "object") return node;
  const result = { ...node };
  if (result.type === "image") {
    const assetId = Number(result.attrs?.assetId);
    result.attrs = { ...(result.attrs || {}), src: sources[assetId] || "" };
  }
  if (Array.isArray(result.content))
    result.content = result.content.map((child) =>
      replaceImageSources(child, sources),
    );
  return result;
};

export default {
  name: "HandoutDocument",
  components: { EditorContent },
  props: {
    content: { type: Object, default: () => ({ type: "doc", content: [] }) },
    assets: { type: Array, default: () => [] },
  },
  data: () => ({
    editor: null,
    assetUrls: {},
    loading: false,
    activePdfUrl: "",
    activePdfName: "handout.pdf",
  }),
  computed: {
    pdfAssets() {
      return this.assets.filter(
        (asset) => asset.mimeType === "application/pdf",
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
  },
  mounted() {
    this.editor = new Editor({
      editable: false,
      extensions: [
        StarterKit.configure({ heading: { levels: [1, 2, 3, 4] } }),
        Link.configure({
          HTMLAttributes: { target: "_blank", rel: "noopener noreferrer" },
        }),
        HandoutImage,
        HandoutMention,
        HandoutAttachment,
      ],
      content: copy(this.content),
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
        const imageAssets = this.assets.filter((asset) =>
          String(asset.mimeType || "").startsWith("image/"),
        );
        const nextUrls = {};
        await Promise.all(
          imageAssets.map(async (asset) => {
            if (this.assetUrls[asset.id]) {
              nextUrls[asset.id] = this.assetUrls[asset.id];
              return;
            }
            const blob = await handoutApiClient.fetchAssetBlob(asset.id);
            nextUrls[asset.id] = URL.createObjectURL(blob);
          }),
        );
        Object.entries(this.assetUrls).forEach(([assetId, url]) => {
          if (!nextUrls[assetId]) URL.revokeObjectURL(url);
        });
        this.assetUrls = nextUrls;
        this.editor.commands.setContent(
          replaceImageSources(copy(this.content), this.assetUrls),
          { emitUpdate: false },
        );
      } catch (_error) {
        // A denied asset stays invisible; the document itself remains available.
        this.editor.commands.setContent(copy(this.content), {
          emitUpdate: false,
        });
      } finally {
        this.loading = false;
      }
    },
    async openAsset(asset) {
      try {
        let url = this.assetUrls[asset.id];
        if (!url) {
          const blob = await handoutApiClient.fetchAssetBlob(asset.id);
          url = URL.createObjectURL(blob);
          this.assetUrls = { ...this.assetUrls, [asset.id]: url };
        }
        this.activePdfUrl = url;
        this.activePdfName = asset.name || "handout.pdf";
      } catch (_error) {
        // The API has already enforced access; no URL is exposed on failure.
      }
    },
  },
};
</script>
