# Shop access contract

The shop module consumes an authenticated context; it does not implement login.

## Production context

Send `Authorization: Bearer <JWT>`. The token must contain:

- `sub`: numeric user ID;
- `role`: `user`, `gm`, or `admin`.

Campaign access is resolved server-side:

- `admin` may access every campaign;
- `gm` may manage only campaigns where `campaigns.game_master_id = sub`;
- `user` may access only owner codes assigned through `shop_owner_claims`.

Character ownership must be created or updated by the future authentication/character module in `shop_owner_claims` (`campaign_id`, `user_id`, `character_id`, `owner_code`). The frontend never grants production permissions.

A user may have several claim rows and therefore switch among several characters. The GM may select any campaign character as the current shop actor without losing GM permissions.

## Temporary development selector

When `CI_ENVIRONMENT` is not `production` and `SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS=true`, the docked selector sends:

- `X-Shop-Access-Mode: gm|player`;
- `X-Shop-View-Mode: character` when GM is shopping as a selected character;
- `X-Shop-Owner-Code` for player mode;
- `X-Shop-Character-Id` when a character is selected.

These headers are ignored in production. With no selected development mode, protected campaign endpoints return `403`; anonymous access is never promoted to administrator.

Changing the development account/role or character refreshes the registered Vuex shop module in place. It does not reload the document, remount the trade modal, or change the modal's open state.

The selected character is the source of both shop identity and purse state:

- the portrait is read from `characters.avatar` (`avatar_url` is retained only as a migration fallback);
- the purse is read from `characters.brass`;
- buying, selling, and GM ledger reversal update `characters.brass` and the shop currency index atomically.

The character migration backfills `avatar` and imports the historical `BRASS` values from `warhammer.w_bg_current` when that legacy schema is available. The legacy character seeder contains the same balances for clean installations.

Legacy character avatar IDs are resolved against the application's public Cloudinary image path. `VUE_APP_CHARACTER_AVATAR_BASE_URL` may override that path. An empty `avatar` renders a per-character initials placeholder.

`GET /api/shop/campaigns/{campaignId}/access/options` supplies the selector options. In production it returns only options available to the authenticated user.
