# Kreator map

Kreator map jest narzędziem MG osadzonym w toolboxie stołu. Otwiera się w
maksymalizowalnym `TableFloatingWindow`, zachowuje drewniany pasek i ramkę
aplikacji, a samo wnętrze jest ciemnym obszarem roboczym 2D/2.5D. Edytor
otrzymuje identyfikator kampanii, aktualnej sceny i — jeśli istnieje — projektu
mapy przypisanego do tej sceny.

## Uruchomienie

```bash
cp .env.example .env
# uzupełnij co najmniej hasła bazy, JWT_SECRET i REALTIME_TICKET_SECRET
docker compose up -d --build
docker compose exec php php spark migrate
```

Następnie zaloguj się jako MG, otwórz stół kampanii i wybierz **Kreator map**
z lewego toolboxa. Ten sam edytor można otworzyć skrótem **Utwórz mapę** albo
**Edytuj mapę** w panelu scen.

Uruchomienie lokalne pozostaje zgodne z resztą projektu:

```bash
cd backend && composer install && php spark migrate && php spark serve
cd frontend && npm ci && npm run serve
cd websocket && npm ci && npm start
```

Przy uruchomieniu bez Dockera proces kolejki AI trzeba uruchomić osobno:

```bash
cd backend && php spark maps:ai-worker
```

## Konfiguracja AI

Klucz jest odczytywany wyłącznie przez PHP i worker. Nie trafia do bundla Vue,
odpowiedzi API ani WebSocketu.

```dotenv
MAP_AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_API_BASE=https://api.openai.com/v1
MAP_AI_TEXT_MODEL=gpt-5.4-mini
MAP_AI_IMAGE_MODEL=gpt-image-2
MAP_AI_TIMEOUT_SECONDS=120
MAP_AI_DAILY_COST_UNITS=50
```

Brak `OPENAI_API_KEY` jest wspieranym stanem: edycja ręczna działa normalnie,
a panel AI pokazuje „Brak konfiguracji klucza”. Adapter dostawcy implementuje
`MapAiProviderInterface`, więc innego dostawcę można dodać bez zmiany
kontrolera, kolejki i UI.

Zadanie układu lub edycji kosztuje jedną wewnętrzną jednostkę limitu, a
generowanie grafiki dziesięć. Limit jest naliczany dziennie na kampanię. Klucz
idempotencji oraz atomowa zmiana `queued -> running` chronią przed podwójnym
wykonaniem. Zadanie można anulować przed startem oraz w trakcie; wynik trwającej
operacji dostawcy jest wtedy odrzucany.

## Model i przepływ publikacji

Dokument mapy ma `schemaVersion: 1`, wspólne z VTT współrzędne pikselowe z
początkiem w lewym górnym rogu, skalę `pixelsPerMeter`, poziomy, warstwy,
obiekty, krzywe, maski i ustawienia siatki. Instancje PixiJS, tekstury i cache
URL-i są trzymane poza głęboką reaktywnością Vue.

Każdy zapis tworzy niemutowalną rewizję z SHA-256. Projekt używa optymistycznej
kontroli numeru rewizji, lokalnej kopii awaryjnej i odnawianej co 45 sekund
blokady aktywnego edytora. Blokada wygasa po 120 sekundach. Identyfikator
instancji edytora zapobiega jednoczesnej edycji w dwóch kartach tej samej sesji.

Publikacja działa tak:

1. Edytor zapisuje bieżącą rewizję i eksportuje jej render WebP.
2. Render trafia przez HTTP do istniejącego magazynu assetów sceny.
3. Serwer filtruje prywatne/ukryte warstwy oraz obiekty MG.
4. Geometria ścian, drzwi, okien i świateł trafia do istniejących tabel oraz
   rendererów VTT z polami pochodzenia mapy i rewizji.
5. Tokeny i stan odkrytej mgły pozostają bez zmian.
6. WebSocket rozsyła małe zdarzenie `map.published`; klienci odświeżają scenę,
   natomiast plik graficzny nadal pobierają przez HTTP.

## Biblioteka assetów

Pakiet startowy zawiera dokładnie **24 elementy**: 16 grafik obiektów, pięć
materiałów i trzy gotowe kompozycje. Atlas
`frontend/public/map-builder/assets/starter-medieval-atlas-v1.png` jest grafiką
wygenerowaną dla projektu, z zapisaną proweniencją oraz sumą SHA-256. Kategorie
biblioteki to: natura, zabudowa, wnętrza, lochy, jaskinie, ruiny, wyposażenie,
dekoracje, materiały i gotowe kompozycje. Własna nazwa kategorii działa jak
kolekcja użytkownika.

Pojedynczy import przyjmuje PNG/JPEG/WebP do 25 MB. Pakiet ZIP może mieć do
100 MB, 500 wpisów i 2 GB po rozpakowaniu; wpisy większe niż 25 MB, ścieżki
wychodzące z archiwum oraz podejrzany współczynnik kompresji są odrzucane.
Limit magazynu wynosi 2 GB na kampanię.

Minimalny `manifest.json` pakietu:

```json
{
  "assets": [
    {
      "id": "custom.my-pack.oak-table",
      "version": 1,
      "file": "objects/oak-table.webp",
      "name": "Dębowy stół",
      "category": "Moja karczma",
      "tags": ["stół", "karczma"],
      "physicalSize": { "width": 2.4, "height": 1.4, "unit": "m" },
      "anchor": { "x": 0.5, "y": 0.5 },
      "obstacle": { "type": "rect", "width": 2.1, "height": 1.2 },
      "provenance": {
        "type": "licensed",
        "author": "Autor pakietu",
        "license": "CC-BY-4.0"
      }
    }
  ]
}
```

## Scenariusz odbioru: karczma z podwórzem

1. Otwórz Kreator map z toolboxa i utwórz projekt „Karczma pod Dębem”.
2. Pomaluj trawę i ubitą ziemię, narysuj prostokątną izbę oraz podwórze.
3. Dodaj ściany, drzwi i światło paleniska. Umieść kompozycję „Podwórze
   karczmy”.
4. Zaznacz izbę, wybierz **Wyposaż / zmień zaznaczenie**, opisz stoły, krzesła
   i wolne przejście do drzwi, a następnie zaakceptuj podgląd propozycji AI.
5. Ręcznie przesuń i obróć stół. Zapisz projekt jako szablon.
6. Zamknij i ponownie otwórz kreator. Wybierz zapisaną mapę oraz sprawdź
   rewizję i lokalne odzyskiwanie.
7. Kliknij **Publikuj**. Scena dostaje render, działające drzwi i światło;
   istniejące tokeny oraz odkryta mgła pozostają zachowane.

Ten przepływ ma test integracyjny z prawdziwą bazą SQLite i symulowaną,
zwalidowaną propozycją AI. Test nie wykonuje płatnego wywołania zewnętrznego.

## Weryfikacja

```bash
cd backend && composer test
cd frontend && npm run lint
cd frontend && npx vitest run \
  src/components/vtt/map-builder/__tests__/mapDocument.test.js \
  src/components/vtt/table/__tests__/tableWindowMethods.test.js
cd websocket && npm test
cd frontend && npm run build
```

Testy specyficzne dla scenariusza można uruchomić krócej:

```bash
cd backend
vendor/bin/phpunit tests/unit/MapDocumentValidatorTest.php
vendor/bin/phpunit tests/unit/MapBuilderServiceDatabaseTest.php
```

## Rzeczywiste ograniczenia wersji 1

- Interaktywne płótno i dokument obsługują mapy do 50 000 × 50 000 px, ale
  eksport przeglądarkowy jest skalowany do maksymalnego boku 8192 px, aby nie
  przekraczać typowych limitów GPU/canvas.
- Pędzel zachowuje edytowalne wektorowe pociągnięcia, miękką krawędź,
  przepływ, krycie i dynamiczne maski wnętrza/zewnętrza pomieszczeń. Nie jest
  destrukcyjnym, wielokanałowym systemem PBR ani symulacją wysokości terenu.
- Atlas startowy ma wariant podglądowy i standardowy wskazujące obecnie ten sam
  plik. Metadane wspierają dalsze warianty, ale serwer nie generuje jeszcze
  automatycznie mipmap ani piramidy kafelków dla importów.
- Biblioteka renderuje elementy partiami po 100 i zwalnia chronione URL-e po
  zamknięciu. Renderer jest ładowany jako osobny chunk i współdzieli tekstury,
  lecz wersja 1 nie ma serwerowego streamingu kafelków ani terenowego LOD.
- Operacje łączenia/odejmowania pomieszczeń są zachowane jako edytowalne części
  boolowskie. Publikacja prostokątnych części tworzy dokładny obrys komórkowy;
  złożone, obrócone wielokąty są publikowane jako zestaw obrysów, a nie pełne
  CSG dla dowolnych krzywych.
- Współpraca czasu rzeczywistego jest celowo ograniczona do jednego aktywnego
  edytora. WebSocket służy do publikacji i powiadomień, nie do wspólnego
  przesuwania obiektów.
- Test automatyczny sprawdza walidację i pełny przepływ publikacji bez kosztu
  dostawcy. Test produkcyjny OpenAI wymaga osobnego klucza, dostępu sieciowego
  i świadomego uruchomienia przez administratora.

