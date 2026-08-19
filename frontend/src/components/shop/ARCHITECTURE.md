# Shop Frontend Architecture

## Twarda konwencja

1. `views/` zawiera wyłącznie pliki wejściowe modułów.
   W tym katalogu ma być dokładnie jeden plik `.vue` na jeden moduł widoku.

2. `modules/<module-name>/` zawiera wyłącznie implementację pomocniczą dla danego modułu.
   Dozwolone podkatalogi:
   - `components/`
   - `styles/`
   - `composables/`
   - `options/` jeśli moduł używa osobnego pliku opcji

3. Plik wejściowy z `views/` nie może zawierać logiki pomocniczej specyficznej dla innego modułu.
   Ma tylko składać moduł z komponentów i composables z własnego katalogu `modules/<module-name>/`.

4. Każdy plik `.vue` musi zaczynać się komentarzem HTML opisującym odpowiedzialność pliku.

5. Style lokalne modułu nie mogą wracać do `views/`.
   Muszą mieszkać w `modules/<module-name>/styles/`.

6. Composables lokalne modułu nie mogą wracać do `views/`.
   Muszą mieszkać w `modules/<module-name>/composables/`.

7. Komponenty współdzielone między modułami nie trafiają do `views/` ani do pojedynczego modułu.
   Muszą być umieszczane w `components/shop/common/` albo `components/trade/`.

8. Plik należący do modułu sklepu nie może przekraczać 300 linii.
   Duże widoki, composables, dane i style należy dzielić według odpowiedzialności,
   zachowując mały plik wejściowy składający moduł.

## Aktualne moduły wejściowe

- `ShopView.vue` — Sklep
- `TemplateEditor.vue` — GM: Szablony dodaj/edytuj
- `DefaultStackView.vue` — GM: Przedmioty spersonalizuj
- `TrashBinView.vue` — GM: Otchłań odrzutów
- `ShopEditor.vue` — GM: Sklep dodaj/edytuj
- `AssortmentManager.vue` — GM: Sklep ustal asortyment
- `QuickTransferPreview.vue` — GM: Sklep szybki transfer/podgląd
