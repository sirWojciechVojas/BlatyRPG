# Central media system

`media_assets` in MySQL is the authoritative registry for storage identity,
ownership, campaign scope, visibility and file metadata. Provider URLs are
generated only by `MediaService`; no delivery or upload URL is persisted.

## Upload flow

1. The authenticated client sends file metadata to `POST /api/media/uploads`.
2. The backend checks ownership/campaign access, chooses a provider and creates
   a pending `media_assets` record.
3. The client follows the returned provider-neutral `upload` instruction and
   uploads the bytes directly.
4. The client sends the provider response to
   `POST /api/media/uploads/{id}/complete`.
5. The backend verifies the response (including an R2 `HEAD` check) and marks
   the registry record as ready.

Use `GET /api/media/{id}?variant=thumbnail` to resolve a current delivery URL.
The supported image variants are `avatar-sm`, `avatar-md`, `token`,
`portrait-card`, `portrait-large`, `thumbnail` and `preview`.

Cloudinary handles normal images, portraits, tokens, textures and map assets.
R2 handles audio, PDFs, exports, archives, documents, binary files and maps
larger than 25 MiB. Configure R2 CORS to allow `PUT` from the application
origin and expose the `ETag` response header.

R2 uses two buckets and persists the selected bucket in
`media_assets.provider_container`:

- `R2_PUBLIC_BUCKET` (`blatyrpg-media-public`) for `public` assets;
- `R2_PRIVATE_BUCKET` (`blatyrpg-media-private`) for `campaign` and `private`
  assets.

Both buckets may remain non-public. In that configuration the backend returns
presigned delivery URLs. `R2_PUBLIC_BASE_URL` should only be configured after a
custom CDN domain is attached to the public bucket. `R2_BUCKET` remains a
backward-compatible single-bucket fallback.

Visibility is enforced as follows:

- `public`: resolvable without a session;
- `campaign`: active campaign member, owner or administrator;
- `private`: owner or administrator.

Application GUI assets remain bundled under `frontend/src/assets` and do not
belong in `media_assets`.

`character_asset_sets` remain global. Their rows reference `media_assets.id`,
and only `public` media can be attached because the set itself has no campaign
or owner scope.

## Asset Library administration

`/api/admin/media-assets` and `/api/admin/media-collections` require an
administrator session. The library reads actual domain foreign keys, so an
asset with an active module relation cannot be permanently removed. Collection
membership is organizational only and never grants access.

Replacing an asset uploads and verifies a temporary registry record, swaps its
storage identity into the original row, preserves the original `id` and all
relations, and then purges the old provider object. A failed purge stays in
`delete_failed` and can be retried from the library. Changing visibility uses
the same verified copy-and-swap process.

Module endpoints continue to authorize their own domain object before asking
`MediaService` for a trusted, expiring delivery URL. They read legacy
`storage_key` values only as a temporary fallback.

## Legacy rollout

Apply the schema migration, deploy the dual-read code, configure the relevant
provider credentials, then inspect and migrate local data in controlled
batches:

```sh
php spark media:migrate-legacy --dry-run --batch 100
php spark media:migrate-legacy --module handouts --batch 100
php spark media:migrate-legacy --verify --batch 100
php spark media:migrate-legacy --verify --purge-local --batch 100
```

The command is deploy-only: it is not exposed in the GUI. It skips external
URLs and corpus rows without downloaded bytes, uploads local files through
`MediaService`, verifies provider objects, links the existing domain row, and
only removes a local source in the explicitly requested verified purge step.
Verification checkpoints are stored in provider metadata so repeated batches
resume after already verified rows.
