# Audyt gotowości Blaty RPG do rozbudowy VTT

Stan na 2026-08-19, na podstawie kodu w bieżącym drzewie roboczym. Audyt uznaje
funkcję za istniejącą tylko wtedy, gdy ma model lub stan domenowy, API albo
działający przepływ UI. Teksty i dekoracje strony startowej nie są implementacją.

## Zachowywana architektura

- Backend: CodeIgniter 4/PHP, REST API, MySQL 8 i migracje CI4.
- Frontend: Vue 3, Vue Router, Vuex i Webpack/Vue CLI.
- Brama: nginx oraz obecny Docker Compose.
- Grafika 3D kości: Three.js/Cannon ES i zaadaptowany Dice Roller.
- Sklep pozostaje istniejącym modułem. Nie wolno tworzyć drugiego katalogu,
  portfela, kontenera ani silnika transakcji obok niego.

## Funkcje faktycznie obecne

1. Rejestracja i logowanie zwracające JWT oraz filtr weryfikujący Bearer JWT.
2. Tabela kampanii i relacja kampanii z MG. Ogólnego API kampanii ani członkostwa
   graczy poza kontraktem sklepu jednak nie ma.
3. REST CRUD postaci, elastyczne pole `characters.data`, katalog systemów,
   uniwersów i definicji mechanik RPG.
4. Zestawy grafik postaci z wariantami `avatar`, `portrait`, `token` i `fullbody`.
   Wariant obrazu `token` nie jest tokenem umieszczonym na scenie.
5. Lokalny, animowany rzutnik kości 3D z notacją, fizyką, ulubionymi i ustawieniami.
6. Rozbudowany sklep: profile świata i sklepów, katalogi i słowniki przedmiotów,
   egzemplarze, kontenery, portfele, kupno/sprzedaż, wycena, sugestie oraz rejestr
   transakcji. Ma osobne uprawnienia kampanii i testy jednostkowe/frontendu.
7. Lokalizacja interfejsu po polsku i angielsku.
8. Testy koncentrują się na sklepie, jego autoryzacji, grafikach postaci i UI.
   Nie ma testów domen Scene/Token ani testu wielu klientów w czasie rzeczywistym.

Główne punkty dowodowe: `backend/app/Config/Routes.php`,
`backend/app/Controllers/Api/CharacterController.php`,
`backend/app/Services/Shop/`, `frontend/src/router/index.js`,
`frontend/src/components/dice/` i `frontend/package.json`.

## Macierz funkcji

Statusy: **jest** = działający przepływ; **częściowo** = istnieje użyteczny
fundament, ale nie dana funkcja VTT; **brak** = brak modelu domenowego/API/UI.

| Obszar | Status | Stan rzeczywisty / brak |
|---|---|---|
| Sceny i mapy | brak | Brak tabeli, modelu, API, routingu i edytora sceny. |
| Actor/postać/NPC | częściowo | Są postacie i JSON statystyk; brak spójnego typu NPC, prototypu tokenu i ACL kampanii. |
| Token sceny | brak | Jest tylko wariant grafiki `token`; brak instancji, pozycji, ruchu i widoczności. |
| Warstwy mapy | brak | Brak tła sceny, tiles, rysunków, świateł, ścian i warstwy efektów. |
| Ściany i kolizje | brak | Brak geometrii, indeksu przestrzennego i serwerowej walidacji ruchu. |
| Drzwi/przejścia | brak | Brak stanów drzwi, sekretnych drzwi oraz uprawnień do ich ujawniania. |
| Widzenie i światło | brak | Brak źródeł światła, LOS, percepcji i indywidualnego widoku tokenu. |
| Fog of war/eksploracja | brak | Brak bieżącej widoczności, zapisu eksploracji, reveal/reset i ACL. |
| Grid kwadratowy/heksagonalny | brak | CSS `grid` i grafika landing page nie są siatką mapy. |
| Pomiar/szablony obszarowe | brak | Brak linijki oraz szablonów koła, stożka, promienia i prostokąta. |
| Combat Tracker | brak | Brak walki, inicjatywy, rund, tur i kolejności uczestników. |
| Targetowanie | brak | Brak stanu celów użytkownika i komunikatów realtime. |
| Statusy/Active Effects | częściowo | Sklep zapisuje opis mechaniki przedmiotu; nie ma aktywnego efektu na Actorze ani resolvera statystyk. |
| Testy/ataki/obrażenia | częściowo | Działa lokalny rzut kości; brak autorytatywnej automatyzacji. Metoda zakupu rozwoju postaci nie ma trasy API. |
| Makra/hotbar | brak | Brak bezpiecznego formatu komend, wykonawcy, uprawnień i UI. |
| Ekwipunek/przedmioty | częściowo | Sklep ma realne egzemplarze i kontenery; brak ogólnego dokumentu Item/ekwipowania powiązanego z Actor Effects. |
| Journal/Handouts/linki | brak | Brak dokumentów, stron, ACL i resolvera linków. |
| Compendium | częściowo | Są katalogi systemowe i sklepowe; brak biblioteki wielu typów, importu/eksportu i wersjonowania. |
| Roll Tables | brak | Losowanie asortymentu sklepu nie jest ogólną tabelą losową VTT. |
| Playlisty/ambient/obszary | brak | Brak modeli audio, odtwarzacza współdzielonego i emisji obszarowej. |
| Animowane tokeny/tiles/FX | brak | Animowane są kości, nie elementy sceny. |
| Interakcje mapy | brak | Brak regionów, triggerów, teleportów, pułapek, skrzyń i przełączników. |
| Granularne uprawnienia | częściowo | JWT i ACL sklepu istnieją; brak wspólnej polityki dla kampanii i dokumentów VTT. |
| Moduły/rozszerzenia | brak | Modułowy podział kodu sklepu nie jest API rozszerzeń ani rejestrem modułów. |
| Hooks/zdarzenia domenowe | brak | `Config/Events.php` frameworka i HMR nie tworzą publicznego systemu hooks. |
| Własne typy danych/mechaniki | częściowo | JSON postaci, definicje i strategie są podstawą; brak schematów, wersji i rejestru typów. |
| Synchronizacja realtime | brak | Nie ma serwera/protokołu zdarzeń gry, pokojów, snapshotów ani reconnect. |
| Wielu graczy na scenie | brak | Nie istnieje scena ani współdzielony, autorytatywny stan sesji. |

## WebSocket: stan faktyczny

- Serwis `websocket` w `docker-compose.yml` wykonuje tylko `tail -f /dev/null`;
  zamontowany katalog nie zawiera serwera aplikacyjnego.
- Nginx kieruje `/ws` i `/sockjs-node` do `frontend:8080`. To kanały
  Webpack Dev Server służące HMR/live reload, a nie synchronizacji gry.
- `frontend/vue.config.js` jawnie ustawia `webSocketServer: "ws"` dla dev servera.
- `Teal` w rzutniku ustawia `offline = true`; lokalny callback nie rozsyła wyniku
  innym graczom i nie zapisuje autorytatywnego rzutu.

Wniosek: istnieje miejsce infrastrukturalne na usługę WebSocket i kod klienta
odziedziczony po rzutniku, ale nie istnieje działający WebSocket aplikacyjny.
Nie można uznać standby/HMR za spełnienie wymagań realtime.

## Krytyczne ryzyka przed budową VTT

### Autoryzacja i bezpieczeństwo

1. `CharacterController::index/show/update/delete` nie sprawdza właściciela ani
   dostępu do kampanii. Dowolny użytkownik z poprawnym JWT może odczytać listę,
   wskazać cudze ID, zmienić dozwolone pola (w tym właściciela/kampanię/walutę)
   albo usunąć cudzą postać. Jest to krytyczne BOLA/IDOR.
2. Kontrolę własności ma tylko przypisanie zestawu grafik. Nie chroni to CRUD.
3. `AuthFilter` weryfikuje token, ale nie ustanawia jednego principal używanego
   przez kontrolery. Sklep ponownie dekoduje JWT we własnym serwisie, a inne
   kontrolery nie pobierają zweryfikowanej tożsamości.
4. Członkostwo i role kampanii są zdefiniowane praktycznie tylko dla sklepu przez
   `shop_owner_claims`; nie ma wspólnej polityki GM/player/observer dla VTT.
5. Developerskie anonimowe nagłówki sklepu są domyślnie włączone w Compose.
   Są odrzucane dla `CI_ENVIRONMENT=production`, lecz wdrożenie musi fail-closed
   przy błędnej lub brakującej konfiguracji środowiska.
6. Każde połączenie i każde zdarzenie przyszłego WebSocket musi ponownie
   autoryzować użytkownika, kampanię, scenę i operację; zaufanie do klienta nie
   może zastąpić kontroli serwera.

### Migracje i integralność danych

1. `2025-12-08-230833_CreateCharactersTable.php` wyłącza klucze obce i bezwarunkowo
   usuwa tabelę `characters` w `up()`. Uruchomienie na istniejącej instalacji może
   bezpowrotnie skasować postacie.
2. Po odtworzeniu tabela nie odzyskuje FK `user_id -> users` ani
   `campaign_id -> campaigns`, więc baza nie wymusza tych relacji.
3. `EnhanceCharacterStructure::down()` nie odtwarza usuniętej kolumny
   `current_profession_id`; rollback nie przywraca poprzedniego schematu.
4. W bieżącym drzewie zmodyfikowanych jest dziewięć historycznych migracji z 2025.
   Migracji, które mogły zostać wykonane, nie wolno poprawiać wstecz; naprawy
   wymagają nowych, idempotentnych migracji forward z kontrolą danych.
5. Brak testu aktualizacji realnej starszej bazy oraz backup/restore gate. Przed
   schematem VTT potrzebna jest kopia, dry-run, weryfikacja FK i liczby rekordów.
6. Elastyczny JSON nie ma jawnego `type`, `schema_version` ani walidacji schematu.
   Bez tego własne mechaniki będą trudne do bezpiecznej migracji.

## Zasady dalszej rozbudowy

- Serwer jest źródłem prawdy dla dokumentów świata, ruchu, drzwi, walki i efektów.
- Kamera, zaznaczenie, hover, otwarte panele i roboczy pomiar pozostają lokalne;
  nie trafiają do bazy.
- Każdy dokument współdzielony otrzymuje wersję/revision do kontroli konfliktów.
- Zdarzenia realtime przenoszą minimalne delty, a reconnect pobiera autorytatywny
  snapshot; klient nie może sam zatwierdzać wyniku operacji.
- Każda nowa tabela ma migrację forward, indeksy pod kampanię/scenę i test upgrade.
- Zmiany integracyjne sklepu są osobnym atomowym commitem i zachowują jego API,
  transakcje, portfele, kontenery oraz dotychczasowe testy.
