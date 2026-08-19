# Roadmapa wdrożenia VTT

Roadmapa zachowuje CodeIgniter 4, Vue 3/Vuex, MySQL, nginx, Docker Compose oraz
istniejący moduł sklepu. Każdy etap kończy się działającym pionowym przepływem;
nie wolno wystawiać przycisków ani endpointów, które tylko udają funkcję.

## Etap 0 — bramy bezpieczeństwa, danych i realtime

Ten etap nie zmienia kolejności domen VTT; usuwa blokady niezbędne, aby pierwsza
scena nie powstała na niebezpiecznym fundamencie.

1. Wprowadzić jeden zweryfikowany principal requestu i wspólną politykę kampanii:
   GM, player, observer oraz jawne poziomy `owner/controller/observer/none`.
2. Zamknąć BOLA w CRUD postaci i dodać membership kampanii niezależny od sklepu.
   Adapter sklepu ma korzystać z tej polityki bez utraty `shop_owner_claims`.
3. Dodać nowe migracje naprawcze zamiast edytowania wykonanych: zabezpieczenie
   postaci, brakujące FK, kontrola spójności i test aktualizacji z kopii bazy.
4. Aktywować istniejący slot `websocket`: JWT handshake, pokoje kampanii/sceny,
   wersjonowane eventy, ack/idempotency, heartbeat, reconnect i snapshot.
5. Rozdzielić ścieżkę HMR od gry: obecne `/ws` nadal obsługuje dev server albo
   otrzymuje jednoznaczną nazwę, a osobna ścieżka nginx prowadzi do serwisu
   `websocket`. Nie dodawać drugiego konkurencyjnego transportu.
6. Ustalić wspólną kopertę zdarzenia: `event_id`, `type`, `campaign_id`,
   `scene_id`, `document_id`, `revision`, `actor_user_id`, `occurred_at`, `payload`.

Brama wyjścia: testy dowodzą braku dostępu do cudzej postaci/kampanii; migracja
nie zmniejsza liczby postaci; dwóch klientów w jednym pokoju otrzymuje delty,
klient spoza kampanii nie może dołączyć, a reconnect odtwarza snapshot.

## Etap 1 — Scene/Map (pierwszy etap funkcjonalny)

### Zakres w etapie

- Dokument `Scene` należący do kampanii: nazwa, aktywność, wymiary świata,
  tło/asset, padding, kolor tła, nawigacja i `revision`.
- Konfiguracja metryki: `grid_type` (`square`, dwa warianty hex lub `gridless`),
  rozmiar komórki, origin, skala dystansu i jednostka.
- Stabilny rejestr kolejności warstw. W tym etapie działają warstwa tła i grid;
  późniejsze warstwy nie dostają atrap ani nieaktywnych narzędzi.
- Chronione CRUD/list/activate oraz walidacja zasobów, wymiarów i limitów.
- Widok sceny z rzeczywistym renderem tła/gridu, pan/zoom i dopasowaniem widoku.
  Kamera i wybór narzędzia są wyłącznie lokalne.
- Synchronizacja zmian dokumentu i aktywnej sceny przez kanał z Etapu 0.
- Indeksy po `campaign_id`, aktywności i kolejności; paginacja/lista bez ładowania
  ciężkich danych warstw.

### Poza zakresem etapu

Tokeny, tiles, rysunki, ściany, drzwi, światło, widzenie, fog, walka, audio,
triggery i skrypty. Nie są prezentowane jako gotowe na ekranie sceny.

### Definition of Done

- GM tworzy, edytuje, aktywuje i archiwizuje scenę; gracz widzi wyłącznie scenę,
  do której ma dostęp, bez możliwości operacji MG.
- Kwadrat, hex i gridless mają testowane przeliczenia world↔screen i snapping.
- Dwa klienty widzą aktywację/zmianę sceny bez odświeżenia; reconnect jest spójny.
- Niepoprawne wymiary, obcy asset i stale `revision` są odrzucane przez API.
- Testy migracji, API, ACL, geometrii i UI przechodzą; lint/build są zielone.

## Kolejność zależności i kryteria gotowości

| Etap | Dostarczany pion funkcjonalny | Brama do następnego etapu |
|---|---|---|
| 1. Scene | Dokument sceny, tło, square/hex/gridless, warstwy bazowe, aktywacja | Stabilny układ współrzędnych, ACL, revision i synchronizacja dwóch klientów. |
| 2. Token/Actor | `characters` jako źródło Actora, NPC, prototyp i instancja tokenu, pozycja/ruch/obrót, kontrola właściciela | Ruch autoryzowany na serwerze; linked/unlinked dane zdefiniowane; test 100+ tokenów. |
| 3. Walls | Segmenty, typy blokad, kolizje, drzwi otwarte/zamknięte/zablokowane i sekretne | Deterministyczne przecięcia; gracz nie przechodzi i nie ujawnia sekretu; indeks przestrzenny działa. |
| 4. Vision/Lighting | LOS per token, darkness, światła globalne/tokenowe, zasięgi i tryby percepcji | Ten sam snapshot daje ten sam polygon widzenia; ściany blokują wzrok/światło zgodnie z typem. |
| 5. Fog of War | Widoczność chwilowa, eksploracja per użytkownik, reveal/hide/reset MG | Fog nie ujawnia danych niewidocznych w payloadach; reconnect zachowuje eksplorację. |
| 6. Combat | Inicjatywa, uczestnicy, rundy/tury, Combat Tracker, targety, pomiary i area templates | Jedna autorytatywna kolejność; tylko dozwolone role zmieniają turę; reconnect nie duplikuje akcji. |
| 7. Active Effects | Statusy, warunki, czasy trwania, źródła efektów i deterministyczny resolver statystyk | Efekt ma audytowalne before/after, wygasa w poprawnej turze i nie modyfikuje bazowej wartości. |
| 8. Journal | Dokumenty/strony, handouty, ACL oraz stabilne linki do Actor/Item/Scene/lokacji | Linki przeżywają zmianę nazwy; treść prywatna nie trafia do nieuprawnionego klienta. |
| 9. Compendium | Pakiety NPC, Items, Scenes, Journals, Roll Tables i audio; import/eksport/wersje | Import jest walidowany, ma mapę ID i nie nadpisuje świata bez jawnej decyzji. |
| 10. Audio | Playlisty, ambient sceny, głośność/fade i źródła obszarowe | Zdarzenia start/stop/seek są zsynchronizowane; brak autoplay bez gestu i wycieków prywatnych assetów. |
| 11. Interakcje | Regiony, teleporty, przejścia scen, pułapki, przełączniki i skrzynie | Trigger jest serwerowy, idempotentny, ma cooldown/ACL i zapis audytu; pętla teleportu jest blokowana. |
| 12. Modules/Hooks | Manifest, wersje/zgodność, rejestr typów, jawne uprawnienia i stabilne hooks | Moduł da się wyłączyć bez utraty core; hook ma limit błędów i nie omija ACL/transakcji. |
| 13. Automatyzacja | Makra/hotbar, testy, ataki, obrażenia, zapisy, Roll Tables i aplikowanie efektów | Komendy są deklaratywne/ograniczone, audytowalne i testowane dla ról; brak dowolnego kodu klienta na serwerze. |

Łańcuch jest obowiązkowy:

`Scene → Token/Actor → Walls → Vision/Lighting → Fog → Combat → Effects →`
`Journal → Compendium → Audio → interakcje → Modules/Hooks → automatyzacja`.

## Decyzje przekrojowe

### Stan i wydajność

- Persistować: dokumenty świata, pozycje tokenów, stan drzwi, eksplorację fog,
  combat i aktywne efekty. Nie persistować: kamery, hover, zaznaczenia, otwartych
  paneli i roboczego pomiaru. Targety użytkownika mogą być stanem efemerycznym
  realtime; zapis tylko wtedy, gdy reguła gry wymaga trwałości.
- Rozdzielić lekkie manifesty scen od ciężkich elementów; ładować dane per warstwa
  i viewport, używać indeksów przestrzennych oraz batchowanych delt.
- Walidować limity liczby elementów, rozmiary payloadów i częstotliwość ruchu.
  Coalescing ruchu nie może zgubić końcowej pozycji.
- Dodać optimistic concurrency przez `revision`; odrzucony konflikt zwraca
  aktualny dokument zamiast wykonywać last-write-wins bez informacji.

### Rozszerzalność bez przebudowy core

- Już od Scene emitować wewnętrzne, typowane zdarzenia domenowe, ale publiczne
  API modułów stabilizować dopiero w Etapie 12.
- Każdy rozszerzalny dokument ma `type`, `schema_version`, walidowany `system_data`
  i migrator wersji. JSON nie może być niekontrolowanym magazynem dowolnych pól.
- Hook obserwacyjny i komenda zmieniająca stan to różne kontrakty. Zmiana stanu
  zawsze przechodzi przez autoryzowany serwis domenowy i transakcję.

### Integracja istniejącego sklepu

- Nie tworzyć alternatywnego Item/Inventory. Warstwa Actor/Item dostaje adapter do
  obecnych słowników, instancji, kontenerów, portfeli i transakcji sklepu.
- Active Effects mogą odczytywać zatwierdzoną mechanikę przedmiotu, ale nie mogą
  omijać transakcji zakupu/sprzedaży ani modyfikować salda po stronie klienta.
- Każda zmiana kompatybilności sklepu ma własny commit (np.
  `Feat: Integrate shop items with actor inventory`) oraz pełny zestaw regresji
  sklepu. Etap VTT i refaktor sklepu nie trafiają do jednego commita.

## Minimalne bramy jakości każdego etapu

1. Pliki pozostają poniżej 300 linii; logika domenowa nie trafia do komponentu UI.
2. Nowy endpoint ma walidację, policy/ACL, ograniczenia rozmiaru i negatywne testy.
3. Nowa tabela ma migrację, indeksy, test clean install oraz upgrade istniejącej bazy.
4. Każdy event realtime ma test autoryzacji, revision, duplikatu i reconnect.
5. Frontend ma test modelu stanu oraz kluczowego przepływu użytkownika.
6. Uruchomione są `composer test`, `npm run test`, `npm run lint` i `npm run build`.
7. Etap kończy osobny, atomowy commit zgodny z `Feat:`, `Fix:`, `Infra:` lub
   `Config:`; pliki tymczasowe, buildy i sekrety nie są commitowane.
