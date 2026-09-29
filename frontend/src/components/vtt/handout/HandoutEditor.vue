<template>
  <section class="handout-editor">
    <div
      class="handout-editor__toolbar"
      role="toolbar"
      :aria-label="$t('vtt.table.handouts.editorToolbar')"
    >
      <button
        type="button"
        :class="{ active: editor?.isActive('bold') }"
        @click="command('toggleBold')"
      >
        <strong>B</strong>
      </button>
      <button
        type="button"
        :class="{ active: editor?.isActive('italic') }"
        @click="command('toggleItalic')"
      >
        <em>I</em>
      </button>
      <button
        type="button"
        :class="{ active: editor?.isActive('heading', { level: 2 }) }"
        @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        H2
      </button>
      <button
        type="button"
        :class="{ active: editor?.isActive('bulletList') }"
        @click="command('toggleBulletList')"
      >
        • {{ $t("vtt.table.handouts.list") }}
      </button>
      <button
        type="button"
        :class="{ active: editor?.isActive('orderedList') }"
        @click="command('toggleOrderedList')"
      >
        1. {{ $t("vtt.table.handouts.list") }}
      </button>
      <button
        type="button"
        :class="{ active: editor?.isActive('blockquote') }"
        @click="command('toggleBlockquote')"
      >
        {{ $t("vtt.table.handouts.quote") }}
      </button>
      <button type="button" @click="addLink">
        {{ $t("vtt.table.handouts.link") }}
      </button>
      <label class="handout-editor__upload">
        {{ $t("vtt.table.handouts.addImage") }}
        <input
          type="file"
          accept="image/png,image/jpeg,image/webp,image/gif"
          @change="uploadImage"
        />
      </label>
      <label class="handout-editor__upload">
        {{ $t("vtt.table.handouts.addPdf") }}
        <input
          type="file"
          accept="application/pdf"
          @change="uploadAttachment"
        />
      </label>
      <template v-if="allowMentions && mentionTargets.length">
        <select
          v-model="selectedMention"
          :aria-label="$t('vtt.table.handouts.mention')"
        >
          <option value="">{{ $t("vtt.table.handouts.mention") }}</option>
          <option
            v-for="target in mentionTargets"
            :key="`${target.type}:${target.id}`"
            :value="`${target.type}:${target.id}`"
          >
            @{{ target.label }}
          </option>
        </select>
        <button
          type="button"
          :disabled="!selectedMention"
          @click="insertMention"
        >
          @
        </button>
      </template>
    </div>
    <p v-if="uploading" class="handout-editor__status">
      {{ $t("vtt.table.handouts.uploading") }}
    </p>
    <EditorContent :editor="editor" class="handout-editor__content" />
  </section>
</template>

<script>
import { Node, mergeAttributes } from "@tiptap/core";
import Image from "@tiptap/extension-image";
import Link from "@tiptap/extension-link";
import StarterKit from "@tiptap/starter-kit";
import { Editor, EditorContent } from "@tiptap/vue-3";

const EMPTY_DOCUMENT = Object.freeze({
  type: "doc",
  content: [{ type: "paragraph" }],
});

const HandoutImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      assetId: {
        default: null,
        parseHTML: (element) =>
          Number(element.getAttribute("data-asset-id")) || null,
        renderHTML: (attributes) =>
          attributes.assetId
            ? { "data-asset-id": String(attributes.assetId) }
            : {},
      },
      alt: { default: "" },
    };
  },
});

const HandoutMention = Node.create({
  name: "mention",
  group: "inline",
  inline: true,
  atom: true,
  selectable: false,
  addAttributes() {
    return {
      targetType: { default: null },
      targetId: { default: null },
      label: { default: "" },
    };
  },
  parseHTML() {
    return [{ tag: "span[data-handout-mention]" }];
  },
  renderHTML({ HTMLAttributes }) {
    return [
      "span",
      mergeAttributes(HTMLAttributes, {
        "data-handout-mention": "true",
        class: "handout-mention",
      }),
      `@${HTMLAttributes.label || ""}`,
    ];
  },
});

const HandoutAttachment = Node.create({
  name: "attachment",
  group: "block",
  atom: true,
  selectable: true,
  addAttributes() {
    return { assetId: { default: null }, name: { default: "" } };
  },
  parseHTML() {
    return [{ tag: "div[data-handout-attachment]" }];
  },
  renderHTML({ HTMLAttributes }) {
    return [
      "div",
      mergeAttributes(HTMLAttributes, {
        "data-handout-attachment": "true",
        class: "handout-attachment",
      }),
      "PDF: " + (HTMLAttributes.name || ""),
    ];
  },
});

const cloneDocument = (document) => {
  if (!document || typeof document !== "object") return { ...EMPTY_DOCUMENT };
  return JSON.parse(JSON.stringify(document));
};

const addPreviewSources = (document, previewUrls) => {
  const cloned = cloneDocument(document);
  const visit = (node) => {
    if (!node || typeof node !== "object") return;
    if (node.type === "image") {
      const assetId = Number(node.attrs?.assetId);
      if (previewUrls[assetId]) {
        node.attrs = { ...(node.attrs || {}), src: previewUrls[assetId] };
      }
    }
    if (Array.isArray(node.content)) node.content.forEach(visit);
  };
  visit(cloned);
  return cloned;
};

export default {
  name: "HandoutEditor",
  components: { EditorContent },
  props: {
    modelValue: { type: Object, default: () => cloneDocument(EMPTY_DOCUMENT) },
    mentionTargets: { type: Array, default: () => [] },
    allowMentions: { type: Boolean, default: false },
  },
  emits: ["update:modelValue", "upload-image", "error"],
  data: () => ({
    editor: null,
    selectedMention: "",
    uploading: false,
    previewUrls: {},
  }),
  watch: {
    modelValue(value) {
      if (!this.editor) return;
      const current = JSON.stringify(this.editor.getJSON());
      const document = addPreviewSources(
        value || EMPTY_DOCUMENT,
        this.previewUrls,
      );
      const incoming = JSON.stringify(document);
      if (current !== incoming)
        this.editor.commands.setContent(document, {
          emitUpdate: false,
        });
    },
  },
  mounted() {
    this.editor = new Editor({
      extensions: [
        StarterKit.configure({ heading: { levels: [1, 2, 3, 4] } }),
        Link.configure({
          protocols: ["https"],
          openOnClick: false,
          autolink: false,
          linkOnPaste: false,
        }),
        HandoutImage.configure({ allowBase64: false }),
        HandoutMention,
        HandoutAttachment,
      ],
      content: cloneDocument(this.modelValue),
      onUpdate: ({ editor }) =>
        this.$emit("update:modelValue", editor.getJSON()),
    });
  },
  beforeUnmount() {
    this.editor?.destroy();
    Object.values(this.previewUrls).forEach((url) => URL.revokeObjectURL(url));
  },
  methods: {
    command(name) {
      this.editor?.chain().focus()[name]().run();
    },
    addLink() {
      if (!this.editor) return;
      const href = String(
        window.prompt(this.$t("vtt.table.handouts.linkPrompt"), "https://") ||
          "",
      ).trim();
      if (!href) return;
      if (!/^https:\/\/[^\s]+$/iu.test(href)) {
        this.$emit("error", this.$t("vtt.table.handouts.invalidLink"));
        return;
      }
      this.editor
        .chain()
        .focus()
        .extendMarkRange("link")
        .setLink({ href })
        .run();
    },
    async uploadImage(event) {
      await this.uploadAsset(event);
    },
    async uploadAttachment(event) {
      await this.uploadAsset(event);
    },
    async uploadAsset(event) {
      const [file] = event.target.files || [];
      event.target.value = "";
      if (!file) return;
      this.uploading = true;
      try {
        const asset = await new Promise((resolve, reject) => {
          this.$emit("upload-image", { file, resolve, reject });
        });
        if (String(asset?.mimeType || "") === "application/pdf") {
          this.insertAttachment(asset);
        } else {
          this.insertAssetImage(asset);
        }
      } catch (error) {
        this.$emit(
          "error",
          error?.message || this.$t("vtt.table.handouts.uploadFailed"),
        );
      } finally {
        this.uploading = false;
      }
    },
    insertAssetImage(asset) {
      if (!asset?.id || !this.editor) return;
      if (asset.previewUrl) {
        this.previewUrls = {
          ...this.previewUrls,
          [Number(asset.id)]: asset.previewUrl,
        };
      }
      this.editor
        .chain()
        .focus()
        .setImage({
          assetId: Number(asset.id),
          alt: asset.name || "",
          src: asset.previewUrl || "",
        })
        .run();
    },
    insertAttachment(asset) {
      if (!asset?.id || !this.editor) return;
      this.editor
        .chain()
        .focus()
        .insertContent({
          type: "attachment",
          attrs: { assetId: Number(asset.id), name: asset.name || "PDF" },
        })
        .run();
    },
    insertMention() {
      const [type, id] = this.selectedMention.split(":");
      const target = this.mentionTargets.find(
        (item) => String(item.type) === type && String(item.id) === id,
      );
      if (!target || !this.editor) return;
      this.editor
        .chain()
        .focus()
        .insertContent({
          type: "mention",
          attrs: {
            targetType: target.type,
            targetId: Number(target.id),
            label: target.label,
          },
        })
        .run();
      this.selectedMention = "";
    },
  },
};
</script>
