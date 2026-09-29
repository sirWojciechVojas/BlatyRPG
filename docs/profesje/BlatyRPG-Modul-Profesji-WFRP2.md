# BlatyRPG — moduł Profesje, WFRP 2. edycja

**Cel:** umieścić w sidebarze wspólny katalog profesji dostępny dla MG i graczy oraz połączyć kartę profesji z postacią. Wykorzystać istniejącą tabelę `professions` i zachować jej rekordy.

**Podstawa opracowania:** dostarczony zrzut `dump-blatyrpg-202609231340.sql` oraz załączone pliki Vue i CSS. Liczby w dokumencie dotyczą tego zrzutu, a nie zweryfikowanego połączenia z działającym serwerem.

**Gotowy rezultat tego opracowania:** interaktywny podgląd katalogu ze wszystkimi 227 rekordami, projektem umiejscowienia w sidebarze, wyszukiwaniem, filtrami, paginacją, kartą profesji i trybem pełnego okna. Plik `BlatyRPG-Profesje.html` zawiera kompletny kod podglądu, od początku do końca, i działa samodzielnie. Nie pobiera danych z internetu ani nie zmienia bazy. Osobno opisano integrację z aplikacją, postaciami i mechaniką. Nie wdrożono zmian na serwerze.

## 1. Co rzeczywiście zawiera baza

Zrzut zawiera wyłącznie definicję i dane tabeli `professions`. Odwołuje się kluczem obcym do `rpg_systems`, ale nie zawiera tej tabeli ani tabel bohaterów, rozwinięć, umiejętności, zdolności i powiązań profesji. Brak tych tabel w zrzucie nie oznacza, że nie istnieją w działającej aplikacji.

| Element | Wynik sprawdzenia | Znaczenie dla modułu |
|---|---:|---|
| Rekordy profesji | 227, identyfikatory 1–227 | Wyświetlić wszystkie, bez stałego limitu 100 |
| Unikalne nazwy | 226 | Nazwa nie może być identyfikatorem |
| `system_id` | 1 dla wszystkich 227 rekordów | W podglądzie zbiór przypisano do WFRP 2 zgodnie z zadaniem; produkcyjnie potwierdzić mapowanie w `rpg_systems` |
| `is_advanced = 0` | 134 | Typ „Podstawowa” według aktualnego oznaczenia w bazie |
| `is_advanced = 1` | 93 | Typ „Zaawansowana” według aktualnego oznaczenia w bazie |
| `is_main = 1` | 100 | Znaczenie biznesowe nie wynika ze zrzutu |
| `is_main = 0` | 127 | Wszystkie te rekordy również mają być dostępne w katalogu |
| Niepuste `description` | 227 | Nie oznacza to 227 pełnych opisów |
| Niepuste `details` | 27 | Treści dodatkowe, niejednorodny format |
| Rekordy z dosłownymi sekwencjami nowej linii | 43 | Zamiana sekwencji na nowe linie wyłącznie przy prezentacji |

### Pola i ich wykorzystanie

| Pole istniejące | Wykorzystanie | Reguła |
|---|---|---|
| `id` | Wybór wpisu, odnośniki, powiązanie z bohaterem | Stabilny klucz; nie łączyć po nazwie |
| `system_id` | Ograniczenie katalogu do systemu gry | Pobierać z kontekstu systemu/kampanii |
| `name` | Nazwa w liście i karcie | Można zmienić wielkość pierwszej litery w widoku; nie przepisywać danych |
| `description` | Zakładka „Opis” | Zwykły tekst, bez wykonywania HTML |
| `details` | Zakładka „Uwagi” | Zachować całą treść, oddzielić od zweryfikowanej mechaniki |
| `is_advanced` | Filtr i etykieta typu | Jawnie obsłużyć wartości `0`/`1`; tekstowe `"0"` nie może oznaczać prawdy |
| `is_main` | Zachować w modelu danych | Nie używać jako aktywności, widoczności, zgody MG ani źródła podręcznikowego bez sprawdzenia znaczenia w kodzie |
| `created_at`, `updated_at` | Diagnostyka i odświeżanie danych | Nie pokazywać jako dat w świecie gry |

W danych nie ma pola `is_active`. „Funkcjonujące profesje” w tym opracowaniu oznaczają wszystkie istniejące rekordy danego systemu. Katalog domyślnie nie odrzuca rekordów niekompletnych.

## 2. Umiejscowienie i wygląd

### Wejście z sidebara

1. Dodać pozycję **Profesje** w grupie katalogów, obok postaci i przedmiotów. Pozycja jest widoczna dla MG i graczy.
2. Pojedyncze kliknięcie otwiera katalog w istniejącej przestrzeni sidebara.
3. Kliknięcie profesji zastępuje listę jej kartą w tym samym panelu. Przycisk **Lista profesji** przywraca poprzednie zapytanie, filtr i stronę.
4. Przycisk **Otwórz okno** oraz dwuklik pozycji **Profesje w sidebarze** otwierają ten sam moduł w istniejącym systemie okien. Dwuklik wiersza listy nie jest wymagany do otwierania okna.
5. Ponowne otwarcie aktywuje istniejące okno zamiast tworzyć kolejne kopie. Lista i okno korzystają ze wspólnego stanu i wspólnego zbioru danych.
6. Zamknięcie okna nie kasuje filtrów ani zaznaczenia. Zmiana systemu lub wylogowanie usuwa niewłaściwy kontekst.

### Stylistyka

- Zachować aktualną drewnianą belkę tytułową, drewniane zakładki i stopkę. Podłączyć istniejące tekstury oraz elementy obramowania aplikacji.
- Jasny pergamin, ciemnobrązowe litery. Jasny tekst wyłącznie na ciemnym drewnie.
- Brak gradientów w zakładkach, zbędnych dużych kafelków, ilustracji zajmujących treść i dodatkowych ramek wewnątrz ramek.
- Obramowanie na zewnątrz powierzchni roboczej. Stałe wymiary narożników; dopasowywać odcinki boków. Narożniki nie mogą nachodzić na tekst.
- Zachować szerokość istniejącego sidebara. Układ ma działać w szerokości około 300–380 px; nie rozszerzać całego interfejsu dla tego modułu.
- Zwarte wiersze, odstępy zwykle 6–10 px. Czytelne przyciski, widoczny fokus i działanie klawiaturą. Na ekranach dotykowych powiększyć obszar trafienia przycisków.
- W osobnym oknie lista zajmuje około 30–35% dostępnej szerokości, karta pozostałą część. Przy małej szerokości przejść do jednej kolumny.
- W docelowym panelu przewijać treść zgodnie z istniejącym kontenerem sidebara; belka i podstawowe sterowanie pozostają dostępne. Podgląd HTML używa paginacji i naturalnej wysokości.

W dostarczonym CSS występują m.in. `titleBar-center.png` i tekstury pergaminu. Same pliki graficzne nie zostały dołączone. Podgląd odtwarza paletę i układ, a docelowy komponent powinien wykorzystywać rzeczywiste zasoby motywu z aktualnego repozytorium.

## 3. Zawartość modułu

### Katalog

| Element | Zachowanie |
|---|---|
| Wyszukiwarka | Nazwa, opis i informacje dodatkowe. Bez rozróżniania wielkości liter i polskich znaków, w tym `ł/l` |
| Typ | Wszystkie / Podstawowe / Zaawansowane, z licznikami odpowiadającymi zapytaniu |
| Lista | Nazwa i typ, bez rozwiniętego opisu w każdym wierszu |
| Powtarzająca się nazwa | Dodatkowy numer wpisu, np. „Szuler — wpis #193” i „Szuler — wpis #211” |
| Sortowanie | Polski porządek alfabetyczny, a następnie `id` dla rozstrzygnięcia remisów |
| Nawigacja | Zachowanie wyszukiwania i filtra po powrocie z karty |
| Stan pusty | „Brak pasujących profesji” i możliwość zmiany lub wyczyszczenia filtrów |
| Ładowanie i błąd | Osobne stany. Błąd pobierania nie może wyglądać jak pusty katalog; zapewnić ponowienie |

Nie wprowadzać na starcie filtrów rasowych, religijnych, regionalnych, według podręcznika ani według dostępności awansu. Zrzut nie zawiera zweryfikowanych danych pozwalających poprawnie obsłużyć te filtry. Wyszukiwanie po tekście działa również dla nazwy podręcznika zapisanej w `description`.

### Karta profesji

**Nagłówek:** nazwa, typ, identyfikator przydatny do rozróżniania wpisów. Dyskretny komunikat jakości danych tylko wtedy, gdy dotyczy konkretnego rekordu.

**Opis:** zawartość `description`. Dla `BRAK` lub pustego tekstu pokazać informację o nieuzupełnionym opisie. Nazwę podręcznika zapisaną w opisie zachować i oznaczyć jako treść wymagającą uzupełnienia; nie tworzyć na tej podstawie pozornie pełnego opisu.

**Uwagi:** cała zawartość `details` z czytelnymi podziałami wierszy. Pusty wpis oznacza brak dodatkowych uwag. W istniejącym zbiorze znajdują się tu zarówno zasady szczególne, jak i komentarze autora danych. Nie wolno uruchamiać automatycznych reguł na podstawie dowolnego tekstu w tym polu.

**Rozwój:** miejsce na schemat rozwinięć, umiejętności, zdolności, wyposażenie oraz profesje wstępne i wyjściowe. Do czasu pozyskania i sprawdzenia relacji wyświetlać „Nie uzupełniono” albo „Nie zweryfikowano”. Nie wpisywać zer i nie podawać wymyślonych kosztów PD. Jeśli informacje występują opisowo w `details`, wskazać zakładkę „Uwagi”.

Sam katalog nie zmienia danych bohatera. Otwarcie profesji, filtrowanie i przechodzenie między kartami nie kupują rozwinięć, nie przyznają wyposażenia, nie zmieniają profesji i nie wydają PD.

### Profesja postaci

Druga zakładka modułu wykorzystuje aktualnie wybraną postać przy stole. MG może korzystać z bohaterów, do których ma dostęp w kampanii, a gracz ze swoich oraz innych udostępnionych mu zgodnie z istniejącymi uprawnieniami.

| Obszar | Wymagane zachowanie |
|---|---|
| Obecna profesja | Nazwa i odnośnik otwierający właściwy `profession_id` w tym samym katalogu |
| Historia | Profesje faktycznie zapisane przy postaci, we właściwej kolejności |
| Status historii | „Zakończona” tylko wtedy, gdy istnieje takie potwierdzenie. Dawna profesja nie musi oznaczać profesji ukończonej |
| Brak wybranej postaci | „Nie wybrano bohatera”; katalog nadal działa |
| Brak powiązania | „Profesja postaci nie jest powiązana z katalogiem”; nie zgadywać po nazwie |
| Brak danych historii | Odróżnić niedostępną historię od potwierdzonego braku wcześniejszych profesji |
| Rozwój i zmiana profesji | Przejście do istniejącego modułu Awans z kontekstem postaci i profesji, dopiero po obsłużeniu wymaganych danych |

W podglądzie zakładka postaci pokazuje stan bez wybranego bohatera. Zrzut nie zawiera postaci ani ich powiązań; nie przypisano żadnemu prawdziwemu bohaterowi przykładowej profesji.

## 4. WFRP 2 — granica między katalogiem a mechaniką

Katalog musi rozróżniać profesje podstawowe i zaawansowane. Nie przenosić do niego klas, poziomów profesji i reguł awansu pochodzących z innych edycji.

Docelowa pełna karta mechaniczna obejmuje:

- schemat rozwinięć cech głównych i drugorzędnych;
- umiejętności, ich specjalizacje i rzeczywiste warianty wyboru;
- zdolności i warianty wyboru;
- wyposażenie i szczególne warunki;
- profesje wstępne i wyjściowe;
- źródło, stronę, wersję reguł i status weryfikacji.

Taki zakres pól jest zgodny z układem profesji pokazanym w oficjalnej próbce *Career Compendium*. Próbka informuje także o poprawkach schematów, umiejętności, zdolności i powiązań profesji. Oficjalny dodatek *Master List of Career Entries & Exits*, wersja 1.1, zawiera rozwinięte powiązania między profesjami z różnych dodatków. Przy uzupełnianiu danych należy zapisywać zastosowane źródło i wersję, aby nie mieszać różnych zestawów przejść.

Te źródła posłużyły do określenia struktury modułu. Nie nadpisano nimi danych użytkownika ani nie zaimportowano katalogu z internetu. W tym opracowaniu nie ustalono liczbowych kosztów awansu ani kompletnych warunków przejść. Implementacja automatycznego awansu wymaga osobnego oparcia reguł o właściwy podręcznik WFRP 2, erratę i zasady kampanii.

### Zasady modelowania przyszłej mechaniki

- Brak danych to `null` albo jednoznaczny status niekompletności; brak nie oznacza wartości zero ani braku wymagań.
- Opcje „A lub B”, „dowolne dwie” i wybory specjalizacji przechowywać jako grupy wyboru z liczbą wymaganych opcji. Nie rozbijać ich automatycznie na obowiązek wykupienia wszystkiego.
- Przejścia zapisywać kierunkowo, z identyfikatorami profesji i źródłem. Nie dopisywać automatycznie przejścia odwrotnego.
- Nie utożsamiać informacji o profesji wstępnej z potwierdzeniem, że konkretny bohater może teraz przejść do tej profesji.
- Oddzielić dane katalogu od zakupionych rozwinięć i historii konkretnej postaci.
- Ostateczne wyliczenia, sprawdzanie uprawnień i zapis zmiany profesji wykonywać po stronie serwera, z wykorzystaniem istniejącej obsługi Awansu. Nie tworzyć drugiego, niezależnego mechanizmu odejmowania PD.
- W razie braku któregokolwiek potrzebnego zestawu danych pokazać „Nie można jeszcze ocenić dostępności”. Nie wyświetlać fałszywej zgody na awans.

## 5. Integracja z istniejącą aplikacją

### Ustalenia z załączonych źródeł

| Załączony plik | Ustalenie | Konsekwencja |
|---|---|---|
| `GameSessionView.vue` | Importuje osobny `sidebar/Sidebar.vue` i obsługuje zdarzenie `open-window` | Rejestrację pozycji trzeba wykonać w rzeczywistym komponencie sidebara |
| `Sidebar.vue` | Nie został dołączony | Nie można bezpiecznie przygotować jego pełnej podmiany ani twierdzić, że pozycja została już dodana w działającej aplikacji |
| `WindowManager.vue` | Zawiera mapę nazw zakładek do komponentów zawartości | W aktualnym odpowiedniku dopisać obsługę Profesji i aktywowanie istniejącego okna |
| `GameSessionView.vue` i `WindowManager.vue` | Widoczny handler przekazuje nazwę, podczas gdy menedżer przyjmuje także `campaignId` | Sprawdzić i przekazywać pełny kontekst okna: kampanię, system i wybraną postać |
| `FloatingWindow.vue` | Widoczny wariant ogranicza rozmiar do 600 × 600 px | Szeroki katalog wymaga konfiguracji rozmiaru dla tego typu okna albo układu jednokolumnowego w istniejącym limicie |
| `LeftPanel.vue` | To panel ekwipunku wewnątrz karty postaci | Nie jest sidebarem sesji; nie umieszczać tam katalogu |
| `CharacterStatsModal.vue` | `curCareer`, `prevCareer` i bohater są przykładami wpisanymi w kod | Nie traktować ich jako danych pobranych z bazy |
| `HeroEditModal.vue` i `LeftPanel.vue` | Korzystają z istniejącego `apiService` | Użyć rzeczywistej, bieżącej warstwy API i istniejącej autoryzacji |

Nazwy powyżej identyfikują dostarczone załączniki. Przy wdrożeniu trzeba ustalić aktualne odpowiedniki w repozytorium na podstawie importów, rejestru zakładek i odpowiedzialności komponentów. Nie odtwarzać dawnych nazw plików tylko dlatego, że występują w załącznikach.

### Proponowany podział odpowiedzialności

Poniższe nazwy są propozycją nowych modułów, a nie stwierdzeniem, że takie pliki już istnieją.

| Element | Odpowiedzialność |
|---|---|
| `ProfessionsContent.vue` | Kontener współdzielony przez sidebar i osobne okno |
| `ProfessionList.vue` | Wyszukiwanie, filtry, lista i stany pobierania |
| `ProfessionDetails.vue` | Opis, uwagi, dostępne dane mechaniczne i braki danych |
| `CharacterProfessions.vue` | Obecna profesja oraz historia wybranej postaci |
| Moduł store `professions` | Rekordy, stan katalogu, wybrany identyfikator, cache i odświeżanie |
| Warstwa API profesji | Pobieranie danych przez istniejący mechanizm komunikacji |
| Odpowiedni kontroler/model CodeIgniter | Odczyt istniejącej tabeli i egzekwowanie uprawnień |

Stan katalogu: `systemId`, zapytanie, typ, numer strony, `selectedProfessionId`, aktywna sekcja karty. Kontekst postaci: `campaignId`, `characterId`. Ukrywanie i pokazywanie panelu nie powinno powodować ponownego pobierania niezmienionego katalogu.

### Kontrakt odczytu — do podłączenia w bieżącym API

Proponowana operacja: `GET /api/systems/{systemId}/professions`. To projekt endpointu, nie istniejący endpoint potwierdzony w załącznikach. Jeśli aplikacja ma już odpowiadającą mu operację, należy ją wykorzystać.

Odpowiedź:

- `items`: wszystkie rekordy danego systemu, z polami opisanymi w sekcji 1; `id` i `system_id` jako liczby, flagi jako jawne wartości logiczne albo liczby zgodnie z jednym kontraktem;
- `meta.system_id`: system, którego dotyczy odpowiedź;
- `meta.total`, `meta.basic`, `meta.advanced`: liczności zwróconego katalogu;
- `meta.rules_available`: informacja o dostępnych, zweryfikowanych danych mechanicznych; nie ustawiana na podstawie samej obecności `details`.

W obecnej skali katalog można pobrać w całości po pierwszym otwarciu i wyszukiwać po stronie Vue. Paginacja dotyczy wówczas prezentacji, a nie odcinania zbioru. Dla istotnie większych katalogów można później przenieść wyszukiwanie i paginację do serwera, zachowując pełne liczniki. Nie łączyć niepełnej strony API z filtrowaniem udającym przeszukanie całości.

Obsłużyć rozróżnialnie brak logowania, brak uprawnień, nieznany system, błąd połączenia i poprawną odpowiedź z pustym zbiorem. Anulować lub ignorować spóźnioną odpowiedź dla poprzedniego systemu. Cache rozdzielić według systemu i zakresu dostępu oraz wyczyścić przy wylogowaniu lub zmianie uprawnień.

Historię profesji pobierać istniejącym endpointem bohatera, jeśli już ją zwraca. W przeciwnym razie dodać odczyt w kontekście kampanii i bohatera po ustaleniu rzeczywistych tabel i kluczy. Nie tworzyć relacji na podstawie przykładów `curCareer` i `prevCareer` zapisanych w komponencie.

### Uprawnienia

| Operacja | MG | Gracz |
|---|---|---|
| Odczyt katalogu systemu dostępnego w aplikacji | Tak | Tak |
| Wyszukiwanie i podgląd profesji | Tak | Tak |
| Podgląd profesji bohatera | W zakresie istniejących uprawnień do bohaterów kampanii | W zakresie własnych i udostępnionych bohaterów |
| Zmiana globalnej definicji profesji | Poza zakresem; osobne uprawnienie redakcyjne, jeśli istnieje | Nie |
| Zmiana profesji lub zakup rozwinięcia | Przez istniejący moduł Awans i jego reguły | Przez istniejący moduł Awans i jego reguły |

Nie stosować warunku „tylko MG” do pozycji Profesje ani endpointu katalogu. Tożsamość i uprawnienia ustala serwer na podstawie istniejącej autoryzacji, nie parametrów `username`, deklarowanej roli lub samego ukrycia przycisku. Odczyt globalnego katalogu nie może ujawniać prywatnych danych bohaterów ani notatek z kampanii.

### Baza danych

Uruchomienie katalogu nie wymaga nowej tabeli ani ponownego importu profesji. Nie wykonywać na działającej bazie poleceń `DROP TABLE` ze zrzutu. Dane już istnieją; zrzut służy tu do analizy.

Przed rozszerzaniem mechaniki sprawdzić rzeczywisty schemat całej bazy. Jeżeli odpowiednich struktur nie ma, zaprojektować osobno schematy rozwinięć, opcje umiejętności i zdolności, wyposażenie, warunki i kierunkowe przejścia oraz historię profesji postaci. Nazwy tabel i klucze obce dopasować do obecnej konwencji. Nie zakładać istnienia `heroes.id` na podstawie danych frontendowych z kluczem `ID`.

Spójność relacji ma obejmować także zgodność systemu gry. Sama obecność identyfikatora profesji nie wystarcza do powiązania jej z bohaterem dowolnego systemu. Migracje rozszerzające nie mogą usuwać ani przenumerowywać istniejących rekordów. Obecny indeks `system_id` wystarcza do rozpoczęcia prac; nowe indeksy dobierać do rzeczywistych zapytań i ich planów wykonania.

## 6. Dane wymagające weryfikacji

| Problem | Rekordy | Postępowanie |
|---|---|---|
| Dwie profesje o nazwie „szuler” | #193 i #211 | Zachować oba wpisy i ich identyfikatory. #193 ma opis, #211 odwołanie do „Dziedzictwa Sigmara”. Bez sprawdzenia źródeł i relacji nie scalać |
| Opis `BRAK` | #115 Arcydruid, #129 Druid, #130 Druidzki Kapłan, #184 Wielki Druid | Pokazać brak opisu; nie dopisywać treści z pamięci |
| Odwołanie do podręcznika zamiast opisu | 25 rekordów wymienionych poniżej | Zachować wpis, oznaczyć potrzebę uzupełnienia |
| Sprzeczne oznaczenie typu | #226 „artylerzsta”: `is_advanced=0`, opis zawiera „zaawansowana” | Nadal pokazywać typ z flagi i ostrzeżenie; nie poprawiać automatycznie flagi ani nazwy |
| Komentarze redakcyjne | #113 „Podejrzenie…”, #182 „ERROR?”, #189 pytanie o przejście | Nie traktować jako obowiązujących zasad |
| Swobodny zapis reguł | M.in. #195–198, #208, #216, #227 | Udostępnić tekst w Uwagach; strukturalne mapowanie wykonywać po weryfikacji |
| Niejednoznaczne `is_main` | Wszystkie rekordy | Ustalić znaczenie w aplikacji; pozostawić poza filtrami użytkownika |
| Błędy OCR i pisowni | Widoczne w wielu opisach oraz nazwach | Korekty w osobnym, kontrolowanym przeglądzie danych |

25 rekordów z odwołaniem do źródła w opisie: **199, 200, 201, 202, 203, 204, 205, 206, 207, 209, 210, 211, 212, 213, 214, 217, 218, 219, 220, 221, 222, 223, 224, 225, 226**.

Oceny jakości w podglądzie są ręczną adnotacją do tego konkretnego zrzutu. Przy integracji nie przenosić list identyfikatorów jako uniwersalnej heurystyki dla innych systemów ani kolejnych wersji danych. Docelowe adnotacje powinny mieć źródło, wersję rekordu i status weryfikacji; po korekcie opisu nie mogą pozostawać nieaktualne.

Nie wykonywać podczas wyświetlania dekodowania HTML ani `v-html`. Dosłowne sekwencje nowych linii przekształcać jednokrotnie w tekst prezentacyjny; zachować oryginał do kontroli. Nie usuwać ani nie poprawiać treści źródłowej pod pozorem formatowania.

## 7. Kolejność wdrożenia i odbiór

### Etap 1 — działający katalog w sidebarze

1. Ustalić aktualny rejestr zakładek, komponent sidebara, system okien oraz warstwę API.
2. Dodać odczyt istniejącej tabeli dla systemu kampanii i zapewnić dostęp MG oraz graczom.
3. Zbudować wspólną zawartość katalogu: wyszukiwanie, typ, lista, karta i stany pobierania.
4. Podłączyć pojedyncze kliknięcie i dwuklik pozycji Profesje w sidebarze oraz przycisk otwarcia okna.
5. Dopasować motyw, szerokość i obramowanie do obecnej aplikacji.

### Etap 2 — powiązanie z postacią

1. Sprawdzić istniejące źródło obecnej profesji i historii bohatera.
2. Powiązać odczyt z aktywną postacią oraz jej faktycznymi uprawnieniami.
3. Udostępnić odnośniki z obecnej i wcześniejszych profesji do kart katalogu.
4. Obsłużyć nierozpoznane, archiwalne i niejednoznaczne powiązania bez zgadywania.

Etapy 1 i 2 stanowią docelowy zakres opisanego modułu. Drugi etap wymaga danych nieobecnych w załączonym zrzucie; jego kontrakt jest zaprojektowany, ale połączenie nie jest symulowane jako wykonane.

### Etap 3 — rozszerzenie mechaniki

Uzupełnić i zatwierdzić brakujące reguły WFRP 2, a następnie podłączyć ich odczyt oraz ocenę dostępności w istniejącym module Awans. To rozszerzenie wymaga osobnego zestawu źródeł i danych. Nie blokuje udostępnienia katalogu do przeglądania.

### Kryteria odbioru

- Ten zrzut daje 227 widocznych wpisów: 134 podstawowe i 93 zaawansowane, zgodnie z flagami. Nie znika 127 rekordów z `is_main=0`.
- Szukanie „szuler” obejmuje #193 i #211 jako osobne wpisy; może też odnaleźć inne profesje zawierające to słowo w opisie lub uwagach.
- Szukanie „lowca nagrod” odnajduje „łowca nagród”. Wielkie litery i polskie znaki nie zmieniają dostępności wyników.
- Typ „Zaawansowane” nie obejmuje rekordów z `is_advanced=0`. Konflikt #226 jest sygnalizowany, ale nie korygowany bez decyzji redakcyjnej.
- Cyrkowiec udostępnia uwagę o dwóch wybieranych zdolnościach. Wielowierszowe dane Kowala run są czytelne.
- Druid pokazuje brak opisu. Szuler #211 pokazuje istniejące odwołanie do źródła, a nie wygenerowany opis.
- „Rozwój” rozróżnia dane niedostępne od rzeczywistej wartości zero i nie sugeruje gotowości do awansu.
- Powrót z karty, ponowne otwarcie i przełączenie sidebar/okno zachowują stan. Osobne okno nie zasłania sterowania własnej karty.
- Gracz może korzystać z katalogu na równi z MG; nie uzyskuje przez niego danych nieudostępnionych bohaterów.
- Zmiana wybranej postaci lub systemu nie pozostawia danych poprzedniego kontekstu. Spóźniona odpowiedź API ich nie przywraca.
- Brak logowania, odmowa dostępu, błąd połączenia i pusty katalog mają różne komunikaty.
- Obsługa klawiatury i ekranu dotykowego pozwala wybrać profesję, wrócić i otworzyć okno. Przy szerokości 320 px nie ma poziomego przepełnienia.
- Żadna czynność przeglądania nie wykonuje zapisu profesji bohatera, wydania PD ani modyfikacji globalnego katalogu.

## 8. Źródła i zakres weryfikacji

**Źródło danych:** załączony `dump-blatyrpg-202609231340.sql`; odczytano definicję tabeli oraz wszystkie 227 wierszy. Podgląd zawiera te rekordy i nie uzupełnia ich fikcyjnymi wartościami.

**Źródła architektury:** załączone `App.vue`, `GameSessionView.vue`, `HeroEditModal.vue`, `CharacterStatsModal.vue`, `LeftPanel.vue`, `WindowManager.vue`, `FloatingWindow.vue` i `index.css`. Wnioski odnoszą się do tych kopii, nie do nieudostępnionego aktualnego repozytorium. Pliku harmonogramu XML nie modyfikowano.

**Źródła struktury WFRP 2:**

- [Oficjalna próbka Career Compendium](https://d1vzi28wh99zvq.cloudfront.net/pdf_previews/64673-sample.pdf) — układ karty profesji i informacja o korektach; strony drukowane 4–6.
- [Fantasy Flight Games: Career Compendium — Official Web Enhancement, wersja 1.1](https://images-cdn.fantasyflightgames.com/ffg_content/wfrp/media/WFRP_CComp_WebEnhance.pdf) — charakter i wersjonowanie list profesji wstępnych oraz wyjściowych.

**Status:** gotowy projekt i działający podgląd. Do integracji produkcyjnej potrzebne są aktualny komponent sidebara, obecna warstwa API/uprawnień oraz schemat relacji postaci. W dostarczonych plikach nie ma wystarczających danych, aby uczciwie oznaczyć takie wdrożenie jako zakończone.

**Sprawdzenie podglądu:** wszystkie 227 osadzonych rekordów porównano z ekstrakcją SQL. Logikę interakcji sprawdzono w środowisku JavaScript z uproszczonym modelem DOM. Nie wykonano wizualnej kontroli w prawdziwej przeglądarce, ponieważ lokalny silnik przeglądarki był niedostępny; układ responsywny wymaga jeszcze odbioru w docelowej aplikacji.
