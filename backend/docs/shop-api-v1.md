# Shop API v1

Backend API modułu sklepu (CodeIgniter 4), zakres:
- kontekst kampanii: `/shop/campaigns/{campaignId}`
- autoryzacja JWT (filter `auth`)
- GM: pełny CRUD i narzędzia sklepu
- Player: odczyt + `trade/buy` i `trade/sell` dla przypisanego `ownerCode`

## Base URL

`/api/shop/campaigns/{campaignId}`

## Legacy DTO

### `LegacyTemplateRecord`

`ID, NAME, DESCRIPTION, DETAILS, ITEM_CLASS, ITEM_ID, ITEM_GENRE, IMG_CLASS, PRIZE, CHARGE, DRAFT, WEAPON`

### `LegacyInventoryRecord`

`ID, INV_ID, ITEM_PLACE, SLOT, PERSONAL_PSEU, PERSONAL_DESC, PERSONAL_COST, QUANTITY, OWNER_OPT, OWNER, NAME, DESCRIPTION, IMG_CLASS, PRIZE, CHARGE, TRASH_KIND, TRASH_SOURCE_ID`

### `LegacyShopRecord`

`id, name, ownerCode, ownerName, isActive, shopEntries, items`

## Endpointy

### Bootstrap i referencje

- `GET /bootstrap?ownerCode=BG1` — zawiera również `context`, `actors`,
  `permissions` i `containerState`; pola zgodności pozostają dostępne przez
  jeden cykl migracyjny.
- `GET /catalog/network`
- `GET /world-profiles`

### Sklepy

- `GET /shops`
- `POST /shops`
- `GET /shops/{shopId}`
- `PATCH /shops/{shopId}`
- `DELETE /shops/{shopId}`
- `PATCH /shops/{shopId}/activation`
- `POST /shops/{shopId}/duplicate` (`copyMode=profile|profile_with_offer`)

### Profile sklepów

- `GET /shops/{shopId}/profile`
- `PUT /shops/{shopId}/profile`

`pricingConfig` w profilu jest wersjonowaną polityką cenową. Wersja 2 zawiera:

- `policyId`: `balanced`, `friendly`, `premium`, `unrestricted`, `custom` albo
  `null`. Wartość `null` oznacza brak polityki przypisanej do sklepu i wymusza
  bezpieczne zasady ogólne. Ręczne zmiany zasad zapisują politykę jako
  `custom`;
- `baseMultipliers.buy|sell`, `minimumPrice`, `roundingStep`, `roundingMode`;
- konfigurowalne `priceBands`, niezależne od jednostek konkretnego systemu;
- `currencyPolicy` z walutą rozliczeniową, kursami walut źródłowych oraz
  oddzielną prowizją zakupu i skupu;
- przełączniki `enabledModifiers` dla reguł ogólnych;
- priorytetowe `rules[]`. Kryteria reguły mogą obejmować tryb transakcji,
  szablon, klasę, gatunek, walutę źródłową, próg wartości, legalność i
  dostępność. Efekt może być mnożnikiem, korektą kwotową lub ceną stałą;
- `guardrails`, w tym widełki względem ceny katalogowej i maksymalny stosunek
  skupu do ceny sprzedaży, chroniący przed arbitrażem.

Przypisanie jest zapisywane osobno w profilu każdego sklepu. Stare,
niestandardowe konfiguracje bez `policyId` są rozpoznawane jako `custom`, a
konfiguracje domyślne jako brak przypisania. Polityka jest liczona
identycznie w podglądzie klienta i autorytatywnie podczas transakcji na
backendzie. Brak kursu dla obcej waluty blokuje transakcję gracza.

### Template items

- `GET /templates?status=active|archived|all`
- `POST /templates`
- `PUT /templates/{templateId}`
- `DELETE /templates/{templateId}`
- `POST /templates/{templateId}/restore`
- `POST /templates/{templateId}/duplicate`
- `PATCH /item-instances/{instanceId}`

### Kontenery

- `GET /containers?ownerCode=BG1`
- `POST /containers/move`
- `PATCH /containers/quantities`
- `POST /containers/buy`
- `POST /containers/trash`
- `POST /containers/restore`
- `POST /containers/merge`

### Trade (player flow)

- `POST /trade/buy`
- `POST /trade/sell`
- `GET /trade/ledger?page=1&pageSize=50` — GM widzi kampanię, gracz tylko
  własny `ownerCode`.

Nagłówek opcjonalny:
- `Idempotency-Key: <unique-key>`

### Asortyment i sugestie (GM)

- `POST /shops/{shopId}/assortment/replace`
- `POST /shops/{shopId}/assortment/transfer` — obsługuje pojedynczy payload
  zgodności lub atomowe `moves[]`.
- `POST /shops/{shopId}/suggestions/generate`
- `GET /shops/{shopId}/suggestions`
- `POST /shops/{shopId}/suggestions/promote`
- `POST /shops/{shopId}/suggestions/apply`
- `POST /shops/{shopId}/suggestions/materialize`
- `POST /shops/{shopId}/assortment/roll`

## Przykłady

### `POST /trade/buy`

```json
{
  "ownerCode": "BG1",
  "shopId": 1,
  "selections": [
    { "templateId": 12, "quantity": 2 },
    { "templateId": 7, "quantity": 1 }
  ]
}
```

### `POST /trade/sell`

```json
{
  "ownerCode": "BG1",
  "shopId": 1,
  "selections": [
    { "templateId": 12, "quantity": 1 }
  ]
}
```

### Odpowiedź sukcesu `trade/buy` / `trade/sell`

```json
{
  "ok": true,
  "ownerCode": "BG1",
  "shopId": 1,
  "totalBrass": 340,
  "walletBrass": 2535,
  "items": [
    { "templateId": 12, "quantity": 2, "unitPrice": 120 },
    { "templateId": 7, "quantity": 1, "unitPrice": 100 }
  ],
  "containerState": {
    "containers": [],
    "templateRows": [],
    "instanceRows": [],
    "itemInstances": []
  }
}
```

### Błąd domenowy (format ujednolicony)

```json
{
  "ok": false,
  "code": "insufficient_funds",
  "message": "insufficient_funds"
}
```

## Kody błędów domenowych

- `insufficient_funds`
- `insufficient_stock`
- `shop_inactive`
- `forbidden_owner`
- `invalid_quantity`
- `not_found`
- `invalid_payload`
- `missing_exchange_rate`
- `transaction_failed`

## Operacyjnie

- Operacje `trade/buy` i `trade/sell` działają transakcyjnie.
- Log audytowy zapisuje się do `shop_trade_transactions`.
- Idempotencja opiera się o `(campaign_id, transaction_type, idempotency_key)` i cache odpowiedzi z poprzedniej transakcji `SUCCESS`.
