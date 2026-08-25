# Blaty RPG

## Podręcznik użytkownika Stołu VTT

Wydanie dokumentu: 1.0  
Stan aplikacji opisany na dzień: 25 sierpnia 2026 r.  
Odbiorcy: Mistrzowie Gry, gracze, obserwatorzy i administratorzy

> Ten podręcznik opisuje zarówno funkcje dostępne obecnie, jak i zaplanowany sposób działania narzędzi widocznych, lecz jeszcze nieaktywnych. Status jest podany przy każdym narzędziu. Funkcja oznaczona jako PLANOWANA nie wykonuje jeszcze operacji w aplikacji.

## 1. Jak czytać ten podręcznik

### Legenda statusów

| Status | Znaczenie |
|---|---|
| DOSTĘPNE | Funkcja działa i można jej używać podczas sesji. |
| CZĘŚCIOWO | Główna funkcja działa, ale część docelowych możliwości czeka na wdrożenie. |
| PLANOWANE | Element pokazuje docelowy kierunek, lecz przycisk jest obecnie nieaktywny. |

Nazwy przycisków zapisane pogrubieniem odpowiadają etykietom lub podpowiedziom w interfejsie. Symbole M, V, L, B i D są wyjaśnione w rozdziałach dotyczących ścian, tiles i świateł.

### Najważniejsza zasada

Serwer jest źródłem prawdy dla współdzielonego stanu. Pozycje tokenów, ściany, drzwi, światła, tiles, aktywna scena i czat są przesyłane przez istniejące połączenie WebSocket. Ustawienia czysto osobiste, takie jak układ Hotbara, pozostają w przeglądarce użytkownika.

## 2. Pierwsze wejście do Stołu

1. Zaloguj się do Blaty RPG.
2. Otwórz kampanię z listy Stołów.
3. Wejdź do widoku sesji. Aplikacja otworzy pełnoekranowy workspace bez przewijania całej strony.
4. Sprawdź w górnym pasku nazwę kampanii, nazwę sceny oraz stan połączenia.
5. Jeżeli jesteś MG, wybierz scenę roboczą. Gracze zobaczą scenę aktywowaną i udostępnioną przez MG.

> Przykład: MG przygotowuje scenę „Karczma pod Gryfem”, a gracze pozostają na aktywnej scenie „Droga do Altdorfu”. Dopiero użycie **Aktywuj dla graczy** przełączy wszystkich uczestników na przygotowaną karczmę.

## 3. Anatomia workspace

### Górny pasek sesji — DOSTĘPNE

Górny pasek pokazuje tytuł kampanii, aktualnie wybraną scenę, liczbę użytkowników online i status połączenia. Strzałki przechodzą do poprzedniej lub następnej sceny, a lista rozwijana pozwala wybrać konkretną scenę. Symbol odtwarzania aktywuje scenę dla graczy, jeżeli scena jest widoczna.

Przycisk pauzy jest obecnie PLANOWANY. Docelowo MG zatrzyma nim współdzielony czas gry i akcje wymagające aktywnej sesji.

> Przykład: przed ujawnieniem zasadzki MG wybiera ukrytą scenę, przygotowuje tokeny, następnie zaznacza widoczność sceny i klika symbol aktywacji.

### Lewy pasek narzędzi

Pionowy, kompaktowy toolbar znajduje się w lewym górnym obszarze. Kliknięcie wybiera narzędzie. Ponowne kliknięcie aktywnego narzędzia wyłącza je i wraca do **Zaznaczania i przesuwania**. Narzędzia MG są ukryte przed użytkownikiem bez odpowiednich uprawnień. Wyszarzone ikony są PLANOWANE.

### Canvas

Canvas jest główną powierzchnią mapy. Warstwy sceny są renderowane w spójnej kolejności: tło sceny, tiles tła, grid, tiles foreground, tokeny, światło i ciemność oraz edytory elementów MG. Otwarcie panelu bocznego zmniejsza obszar roboczy bez przewijania całej strony.

Sterowanie kamerą:

- rolka myszy — powiększanie lub pomniejszanie względem kursora;
- przeciągnięcie pustego obszaru lewym lub środkowym przyciskiem — przesuwanie mapy;
- strzałki — przesuwanie widoku o 40 pikseli;
- klawisze **+** i **−** — zmiana zoomu;
- klawisz **0** lub **Home** — dopasowanie całej mapy;
- przyciski nad Canvasem — pomniejsz, dopasuj, powiększ, odśwież i ustawienia.

> Przykład: aby przyjrzeć się drzwiom w północnej części lochu, ustaw kursor nad drzwiami i obróć rolkę w górę. Naciśnij **0**, aby wrócić do widoku całej mapy.

### Prawy pasek modułów

Prawy pasek zawiera ikony modułów Stołu. Pojedyncze kliknięcie otwiera kompaktową szufladę. Ponowne kliknięcie tej samej ikony ją zamyka. Dwuklik otwiera moduł jako osobne, przeciągane okno.

### Dolny Hotbar

Hotbar ma 10 slotów oznaczonych klawiszami 1–9 i 0. Kliknięcie pustego slotu otwiera paletę akcji. Sloty można przestawiać przez drag & drop, usuwać prawym przyciskiem i uruchamiać cyfrą na klawiaturze. Konfiguracja jest osobna dla kampanii i zapisywana lokalnie w przeglądarce.

## 4. Role i poziomy dostępu

### Role

| Rola | Typowe możliwości |
|---|---|
| Administrator | Pełny dostęp aplikacyjny i możliwość zarządzania każdym Stołem. |
| Mistrz Gry | Zarządzanie kampanią, scenami, postaciami i narzędziami MG. |
| Asystent | Dostęp obserwacyjny oraz dodatkowe możliwości nadane w kampanii. |
| Gracz | Udział w sesji i kontrola powierzonych postaci oraz tokenów. |
| Obserwator | Oglądanie udostępnionych treści bez standardowej kontroli gracza. |

### Poziomy zasobów

| Poziom | Znaczenie praktyczne |
|---|---|
| NONE | Brak dostępu do elementu. |
| LIMITED | Dostęp ograniczony, np. podstawowy widok postaci. |
| OBSERVER | Pełniejszy podgląd bez prawa zarządzania. |
| OWNER | Kontrola i edycja elementu. |

Interfejs ukrywa lub blokuje niedozwolone akcje, ale backend ponownie sprawdza każde żądanie. Samodzielna modyfikacja danych wysyłanych przez przeglądarkę nie omija uprawnień.

> Przykład: gracz z OWNER dla postaci może przesuwać powiązany token. Inny gracz widzi token, ale nie może nim sterować. Ukryty token nie jest przesyłany graczom bez prawa podglądu.

## 5. Narzędzia sceny — lewy toolbar

### 5.1 Zaznaczanie i przesuwanie — DOSTĘPNE

To bezpieczny tryb domyślny. Umożliwia nawigację po Canvasie, zaznaczanie tokenów oraz pracę z ich HUD-em. Wybranie tego trybu kończy rysowanie segmentu ściany, pomiar lub edycję innej warstwy.

Jak używać:

1. Kliknij ikonę kursora.
2. Przeciągnij pusty fragment Canvasu, aby przesunąć mapę.
3. Kliknij token, aby wyświetlić jego kompaktowy HUD.
4. Kliknij pusty obszar lub wybierz inne narzędzie, aby zakończyć operację.

> Przykład: gracz zaznacza token „Eryk”, przesuwa go do sąsiedniej komnaty, a następnie obraca o 15° w stronę przeciwnika.

### 5.2 Tokeny — DOSTĘPNE

Token jest reprezentacją postaci lub NPC na scenie. Tworzenie tokena odbywa się przez przeciągnięcie postaci z okna **Postacie** na Canvas. Token przechowuje pozycję, rozmiar, obrót, elevation, nazwę, obraz, stan blokady i powiązanie z postacią.

Jak używać:

1. Dwukliknij ikonę **Postacie** na prawym pasku.
2. Wyszukaj postać.
3. Przeciągnij postać z listy na wybrane pole mapy.
4. Kliknij token. HUD pozwala obracać go w lewo lub prawo, otworzyć kartę postaci albo — dla MG — usunąć token.
5. Przeciągnij token, aby zmienić pozycję. Ruch zostanie zatwierdzony przez serwer i zsynchronizowany.

Podczas przeciągania aktywny token unosi się ponad mapę i pulsuje złotym światłem. W punkcie startowym pozostaje jego półprzezroczysty „duch”, animowany ślad prowadzi do celownika upuszczenia, a etykieta pokazuje nazwę tokena i aktualny dystans. Ściana blokująca ruch lub zamknięte drzwi zatrzymają niedozwolone przesunięcie. Gracz może poruszać tylko kontrolowanym, nieblokowanym tokenem.

> Przykład: MG przeciąga NPC „Strażnik bramy” z listy postaci na mapę. Gracz nie może poruszyć strażnika, ale MG może go przesunąć, obrócić i usunąć.

### 5.3 Pomiar odległości — DOSTĘPNE

Pomiar pokazuje prostą linię i odległość obliczoną z rozmiaru pola, odległości pola oraz jednostki sceny. Wynik jest podglądem lokalnym: nie jest zapisywany ani transmitowany innym graczom.

Jak używać:

1. Wybierz ikonę linijki.
2. Naciśnij lewy przycisk w punkcie początkowym.
3. Przeciągnij do punktu końcowego i zwolnij.
4. Kliknij prawym przyciskiem albo naciśnij **Esc**, aby usunąć pomiar.

> Przykład: przy gridzie 100 px = 5 m linia o długości dwóch pól pokaże około 10 m.

### 5.4 Szablony obszarowe — DOSTĘPNE

Narzędzie pokazuje lokalny podgląd obszaru. Dostępne kształty to okrąg, stożek 60° i prostokąt. Szablon nie jest jeszcze zapisywany, współdzielony ani automatycznie powiązany z obrażeniami.

Jak używać:

1. Wybierz ikonę szablonu.
2. W małym selektorze wybierz okrąg, stożek albo prostokąt.
3. Przeciągnij od środka lub początku obszaru do jego krawędzi.
4. Prawy przycisk lub **Esc** czyści podgląd.

> Przykład: mag sprawdza, czy stożek płomieni o zasięgu 8 m obejmie trzech przeciwników bez trafienia sojusznika.

### 5.5 Ściany — CZĘŚCIOWO

Ściany są segmentami używanymi przez serwer do blokowania ruchu oraz przez warstwę światła do rzucania cieni. Każdy segment ma trzy przełączniki: **M** — ruch, **V** — widzenie, **L** — światło. Dane widzenia są już przechowywane, natomiast indywidualne pole widzenia tokenów pozostaje funkcją planowaną.

Jak używać:

1. Jako MG wybierz ikonę ściany.
2. Przeciągnij od początku do końca segmentu.
3. Punkty domyślnie przyciągają się do połowy pola grida. Przytrzymaj **Alt**, aby rysować bez przyciągania.
4. Kliknij segment i ustaw przełączniki M, V oraz L.
5. Użyj **×**, aby usunąć segment.

> Przykład: wzdłuż kamiennej ściany lochu MG rysuje segment z M, V i L. Token nie przejdzie przez ścianę, a pochodnia nie oświetli przestrzeni za nią.

### 5.6 Drzwi — DOSTĘPNE

Drzwi są specjalnym typem segmentu ściany. Mogą być otwarte, zamknięte lub zablokowane. Mogą też zostać oznaczone jako sekretne. Otwarte drzwi przestają blokować ruch i światło. Gracz nie otrzymuje informacji ujawniającej sekretne drzwi.

Jak używać:

1. Jako MG wybierz ikonę drzwi.
2. Przeciągnij segment w otworze ściany.
3. Kliknij segment, aby otworzyć HUD.
4. Symbol rombu otwiera lub zamyka, symbol zamka blokuje lub odblokowuje, a **S** przełącza drzwi sekretne.
5. Dwuklik segmentu szybko zmienia stan otwarte/zamknięte.

> Przykład: MG tworzy sekretne drzwi do skarbca. Dla graczy segment zachowuje się jak zwykła ściana. Po odkryciu MG wyłącza **S** i otwiera przejście.

### 5.7 Światła — CZĘŚCIOWO

Źródła światła rozjaśniają ciemność sceny, mają kolor, zasięg jasny i półmrok, intensywność oraz stan aktywności. Ściany z flagą L i zamknięte drzwi ograniczają kształt światła. Indywidualna wizja tokenów, tryby percepcji i przypisanie źródła do tokena są jeszcze planowane.

Jak używać:

1. Ustaw **Poziom ciemności** w ustawieniach sceny.
2. Wybierz ikonę światła.
3. Kliknij mapę, aby utworzyć źródło. Domyślnie jasny promień ma dwa pola, a półmrok cztery pola.
4. Przeciągnij centralny punkt, aby przesunąć światło.
5. W HUD ustaw kolor, **B−/B+** dla jasnego promienia, **D−/D+** dla półmroku, intensywność, aktywność i widoczność znacznika.

> Przykład: w ciemnej krypcie MG ustawia 80% ciemności i dodaje żółtą pochodnię. Ściana grobowca odcina światło, tworząc ciemny obszar za narożnikiem.

### 5.8 Dźwięki — PLANOWANE

Docelowo narzędzie pozwoli umieszczać na mapie źródła ambientu z promieniem słyszalności, głośnością, zapętleniem i plikiem audio. Dźwięk będzie słabł wraz z odległością kontrolowanego tokena, a uprawnienia MG zdecydują, kto może edytować źródło.

Planowany sposób użycia:

1. MG wybierze ikonę dźwięku i kliknie mapę.
2. Wskaże nagranie z Szafy grającej lub biblioteki.
3. Ustawi promień, głośność i pętlę.
4. Przesunie źródło w odpowiednie miejsce.

> Przykład docelowy: szum wodospadu jest słyszalny w promieniu 30 m i staje się głośniejszy, gdy token zbliża się do jaskini.

### 5.9 Tiles i elementy mapy — DOSTĘPNE

Tiles to obrazy lub wideo nakładane na mapę. Warstwa **Background (B)** znajduje się pod gridem i tokenami, a **Foreground (F)** nad tłem i gridem. Tiles mogą być przesuwane, skalowane, obracane, porządkowane, blokowane, ukrywane i synchronizowane między uczestnikami.

Dodawanie przez URL:

1. Wybierz ikonę warstw.
2. W dolnym formularzu wklej bezpieczny adres HTTP/HTTPS albo ścieżkę aplikacji.
3. Wybierz obraz lub wideo oraz Background lub Foreground.
4. Kliknij **+**.

Dodawanie z biblioteki:

1. Dwukliknij **Biblioteka grafik**.
2. Przeciągnij grafikę na Canvas.
3. Upuść ją w miejscu, w którym ma powstać tile.

Edycja:

- przeciągnij ramkę, aby przenieść element;
- użyj uchwytu narożnego, aby zmienić rozmiar;
- **B/F** zmienia warstwę;
- strzałki obrotu zmieniają kąt o 15°;
- strzałki góra/dół zmieniają kolejność;
- suwak zmienia krycie;
- kłódka blokuje przesuwanie i skalowanie;
- symbol widoczności ukrywa tile przed graczami;
- dla wideo symbol pętli włącza zapętlenie.

> Przykład: MG dodaje animowane płomienie jako wideo Foreground, ustawia 60% krycia, zapętla animację i blokuje tile, aby przypadkowo nie przesunąć go podczas sesji.

### 5.10 Rysowanie — PLANOWANE

Docelowo umożliwi linie odręczne, proste, prostokąty, elipsy, wielokąty, kolor wypełnienia i konturu, grubość oraz widoczność. Rysunki będą mogły być trwałe lub tymczasowe i respektować uprawnienia.

> Przykład docelowy: gracz rysuje tymczasową strzałkę pokazującą planowany kierunek ucieczki, a MG dodaje trwały obrys zawalonego tunelu.

### 5.11 Notatki mapy — PLANOWANE

Docelowo pinezka na mapie będzie prowadzić do Journalu, handoutu, sceny, postaci lub przedmiotu. MG ustawi ikonę, etykietę, widoczność i poziom dostępu, a wybraną notatkę będzie mógł pokazać uczestnikom.

> Przykład docelowy: pinezka „Ołtarz Sigmara” otwiera handout z opisem, ilustracją i linkiem do NPC opiekuna świątyni.

### 5.12 Regiony — PLANOWANE

Region będzie interaktywnym obszarem mapy. Wejście, opuszczenie lub kliknięcie regionu uruchomi zdarzenie, makro albo zmianę Scene/Actor/Token. Regiony posłużą do teleportów, pułapek, przełączników, skrzyń i przejść.

> Przykład docelowy: wejście tokena na schody wyświetla potwierdzenie, a po akceptacji przenosi token i użytkownika na scenę „Piwnice”.

### 5.13 Fog of war — PLANOWANE

Docelowo MG zakryje lub odkryje fragmenty mapy, a każdy gracz otrzyma widok wynikający z kontrolowanych tokenów i zapisanej eksploracji. MG będzie mógł resetować eksplorację i korzystać z ręcznego pędzla mgły.

> Przykład docelowy: gracze widzą odkryte korytarze lochu, ale pomieszczenie za zamkniętymi drzwiami pozostaje zasłonięte do chwili wejścia.

### 5.14 Konfiguracja grida — DOSTĘPNE

Ikona grida otwiera ustawienia sceny. Dostępne są: brak siatki, siatka kwadratowa, heksagonalna z wierzchołkiem u góry oraz heksagonalna z płaską górą. Można ustawić rozmiar pola, odległość, jednostkę, kolor, przesunięcie X/Y i krycie.

Jak używać:

1. Wybierz ikonę grida.
2. Wybierz typ siatki.
3. Dopasuj rozmiar pola do grafiki mapy.
4. Skoryguj przesunięcia X i Y.
5. Ustaw odległość i jednostkę używaną przez pomiary.
6. Zapisz scenę.

> Przykład: mapa ma pola 70 px i skalę 1 pole = 2 m. MG ustawia rozmiar 70, odległość 2 i jednostkę „m”, a następnie koryguje offset o kilka pikseli.

## 6. Token HUD i praca z postacią

Po zaznaczeniu tokena wyświetla się mały HUD. Obrót odbywa się skokowo o 15°. Ikona postaci otwiera kartę powiązanego Actora, a **×** usuwa token po potwierdzeniu. Usunięcie tokena nie usuwa postaci.

Kontrola ruchu jest sprawdzana przez backend. Zmiana pozycji przechodząca przez ścianę z flagą M lub zamknięte drzwi zostanie odrzucona. Otworzenie drzwi pozwala przejść. Pozycja zaakceptowana przez serwer jest przesyłana pozostałym uczestnikom.

> Przykład: właściciel postaci próbuje przeciągnąć token przez zamknięte drzwi — ruch nie zostaje zatwierdzony. Po otwarciu drzwi przez MG gracz może wejść do pomieszczenia.

## 7. Prawy pasek i okna modułów

### Obsługa wielu okien — DOSTĘPNE

- pojedyncze kliknięcie ikony otwiera kompaktowy panel;
- ponowne kliknięcie zamyka panel;
- dwuklik otwiera pełne okno;
- przeciąganie nagłówka przesuwa okno po viewportcie;
- kliknięcie okna przenosi je na wierzch;
- **—** minimalizuje, **□** przywraca, a **×** zamyka;
- można jednocześnie otworzyć wiele różnych modułów;
- ponowny dwuklik ikony już otwartego modułu przywraca i fokusuje jego okno.

> Przykład: MG otwiera jednocześnie Czat, Postacie i Sceny. Kartę Postacie ustawia po lewej stronie, Czat po prawej, a Sceny minimalizuje do czasu zmiany lokacji.

### 7.1 Czat — DOSTĘPNE

Czat kampanii działa w czasie rzeczywistym przez WebSocket, przechowuje historię i po ponownym połączeniu synchronizuje brakujące wiadomości. Można doładować starszą historię, ręcznie odświeżyć synchronizację i wysłać wiadomość klawiszem Enter. Obecny formularz obsługuje wiadomości ogólne; tryby IC, OOC, whisper, wiadomości systemowe i rozbudowane karty rzutów są planowane.

> Przykład: gracz wpisuje „Sprawdzam drzwi pod kątem pułapek” i naciska Enter. Wiadomość pojawia się bez okresowego odpytywania serwera u wszystkich uczestników kampanii.

### 7.2 Walka / Combat Tracker — PLANOWANE

Docelowy tracker obsłuży rozpoczęcie i zakończenie walki, uczestników powiązanych z tokenami, inicjatywę, rundę, turę, kolejność, poprzedniego/następnego combatanta, ukrytych NPC, statusy i targetowanie. Serwer będzie autorytatywnie synchronizował stan walki.

> Przykład docelowy: MG zaznacza cztery tokeny, dodaje je do walki, wykonuje rzuty inicjatywy i uruchamia rundę 1. Wszyscy widzą aktywną turę, lecz gracze nie widzą ukrytego skrytobójcy.

### 7.3 Biblioteka grafik — DOSTĘPNE

Panel pokazuje grafiki przypisane do kampanii i tła scen. Obrazy są ładowane leniwie. MG może przeciągnąć grafikę na Canvas, tworząc tile; gracz może przeglądać udostępnione zasoby bez prawa tworzenia elementów.

> Przykład: MG przeciąga tło sceny „Most” do aktywnej sceny, a następnie w narzędziu Tiles zmienia nowy element na Foreground i używa go jako górnego poziomu mostu.

### 7.4 Postacie — DOSTĘPNE

Okno zawiera wyszukiwarkę, listę postaci i pełny edytor karty. Można odświeżać dane, wybierać kartę, zapisywać zmiany i — przy odpowiednich uprawnieniach — usuwać postać. Postać można przeciągnąć na Canvas, aby utworzyć powiązany token.

> Przykład: MG wyszukuje „Ulrika”, poprawia punkty życia na karcie, zapisuje zmianę i przeciąga postać na mapę. Kliknięcie ikony w HUD tokena ponownie otwiera jej kartę.

### 7.5 Przedmioty / Ekwipunek — PLANOWANE

Docelowo panel pokaże przedmioty świata i ekwipunek Actorów, umożliwi foldery, wyszukiwanie, drag & drop na kartę postaci oraz akcje użycia, wyposażenia i przekazania. Przedmioty ze Sklepu mają korzystać z tego samego modelu danych, bez ponownego implementowania sklepu.

> Przykład docelowy: gracz przeciąga „Miksturę leczenia” z ekwipunku na Hotbar i używa jej podczas tury, co aktualizuje ilość oraz publikuje efekt w czacie.

### 7.6 Handouty — PLANOWANE

Docelowo panel pozwoli tworzyć foldery i dokumenty z tekstem, obrazem, wideo lub PDF, wyszukiwać je, linkować Actorów, Items i Scenes oraz pokazywać materiał wszystkim albo wskazanym graczom.

> Przykład docelowy: MG otwiera list znaleziony w skrzyni i wybiera „Pokaż graczom”. Każdy uczestnik otrzymuje okno dokumentu, ale tylko MG może go edytować.

### 7.7 Scenariusz — DOSTĘPNE

Panel pokazuje opis i status kampanii oraz uporządkowaną listę scen. Jest szybkim podglądem przebiegu przygody bez opuszczania Stołu. Rozbudowany edytor węzłów, foldery i linkowanie dokumentów są planowane.

> Przykład: MG sprawdza kolejność lokacji „Karczma → Las → Ruiny” i wybiera właściwą scenę w panelu Sceny.

### 7.8 Sceny — DOSTĘPNE

Panel umożliwia tworzenie, wybieranie, duplikowanie, edycję, usuwanie, preload tła i aktywację sceny. Pokazuje wymiary, typ grida i jednostkę. Scena musi być widoczna, zanim MG aktywuje ją dla graczy.

> Przykład: MG duplikuje scenę „Sala tronowa”, zmienia nazwę na „Sala tronowa — po bitwie”, usuwa część przeciwników i aktywuje wariant po zakończeniu starcia.

### 7.9 Tabele losowe — PLANOWANE

Docelowo Roll Tables będą zawierać zakresy wyników, wagi, tekst, odnośniki do dokumentów i możliwość publikacji wyniku w czacie. Tabele będzie można umieszczać w Compendium i na Hotbarze.

> Przykład docelowy: MG uruchamia tabelę „Spotkania w lesie”; wynik 37 wybiera „Dwóch zwiadowców goblinów” i publikuje rezultat tylko dla MG.

### 7.10 Sklep — DOSTĘPNE

Istniejący moduł Sklepu jest osadzony w Stołowym oknie i zachowuje swoje mechanizmy. Pojedyncze kliknięcie pokazuje kompaktowy launcher, a dwuklik otwiera duże okno Sklepu. Dostęp zależy od uprawnień kampanii.

> Przykład: MG dwuklika ikonę Sklepu, otwiera jego pełny workspace, zarządza ofertą i wraca do mapy bez opuszczania Stołu.

### 7.11 Szafa grająca — PLANOWANE

Docelowo panel obsłuży playlisty, utwory, play/pause/stop, zapętlenie, kolejność i głośność. Playlistę będzie można przypisać do sceny, a nagranie wykorzystać jako ambient na mapie.

> Przykład docelowy: aktywacja sceny „Świątynia” uruchamia zapętloną playlistę chóralną, a MG płynnie ścisza ją przed dialogiem.

### 7.12 Compendium / Biblioteka — PLANOWANE

Docelowo wspólna, stronicowana biblioteka będzie przechowywać Actors/NPC, Items, Scenes, Maps, Journals, Roll Tables, Playlists, Macros i assety. Obsłuży foldery, wyszukiwanie, filtrowanie, drag & drop i import do bieżącego Stołu bez ładowania całego zbioru do pamięci.

> Przykład docelowy: MG wyszukuje NPC „Kultysta”, przeciąga go z Compendium do kampanii, a następnie z listy Postaci na Canvas.

### 7.13 Powiadomienia — DOSTĘPNE

Panel pokazuje stan połączenia, liczbę uczestników online, listę obecnych osób oraz — dla MG — oczekujące zaproszenia.

> Przykład: przed rozpoczęciem walki MG sprawdza, że 4 z 5 uczestników są online i czeka na ostatniego gracza.

### 7.14 Moje ustawienia — DOSTĘPNE

Panel prowadzi do ustawień Stołu/kampanii i profilu użytkownika. Ustawienia sceny pozostają dostępne osobno z paska nad Canvasem lub przez narzędzie grida.

> Przykład: gracz otwiera **Moje ustawienia**, przechodzi do profilu i zmienia dane konta; MG korzysta z **Ustawień Stołu**, aby zarządzać kampanią.

## 8. Zarządzanie scenami

### Tworzenie sceny

1. Otwórz panel **Sceny**.
2. Kliknij **Nowa scena**.
3. Podaj nazwę, opis i URL tła.
4. Ustaw szerokość, wysokość, margines i kolor tła.
5. Ustaw poziom ciemności.
6. Skonfiguruj grid oraz widoczność dla graczy.
7. Zapisz.

Dozwolone wymiary sceny wynoszą od 256 do 50 000 pikseli na oś. Dla dużych scen warto ograniczać rozmiar plików tła i liczbę animowanych elementów.

### Duplikowanie i przygotowanie poza widokiem graczy

Duplikowanie tworzy kopię ustawień sceny. MG może pracować na scenie wybranej lokalnie, podczas gdy gracze pozostają na scenie aktywnej. Ukrytej sceny nie można aktywować, dopóki nie zostanie oznaczona jako widoczna.

### Preload

**Preload tła** wczytuje obraz do pamięci przeglądarki bieżącego użytkownika. Jest przydatny przed aktywacją dużej mapy. Obecnie operacja dotyczy lokalnego tła i nie wysyła polecenia preload do wszystkich klientów.

### Usuwanie

Usunięcie wymaga potwierdzenia. Zawsze upewnij się, że usuwasz właściwą kopię sceny. Operacja jest trwała z perspektywy interfejsu użytkownika.

> Przykład pełnego przygotowania: utwórz scenę 2800 × 1800, ustaw grid 70 px = 2 m, ciemność 70%, dodaj ściany i drzwi, rozmieść światła, przeciągnij dekoracje jako tiles, dodaj postacie jako tokeny, wykonaj preload, zaznacz widoczność i aktywuj scenę.

## 9. Hotbar — konfiguracja i skróty

Aktualnie dostępne akcje Hotbara to: pomniejsz, dopasuj, powiększ, odśwież, Czat, Postacie, Sceny i Ustawienia sceny. Ustawienia sceny są dostępne tylko osobie zarządzającej.

Jak skonfigurować:

1. Kliknij pusty slot z symbolem **+**.
2. Wybierz akcję z palety.
3. Przeciągnij slot na inny slot, aby zamienić ich położenie.
4. Kliknij slot prawym przyciskiem, aby go wyczyścić.
5. Naciśnij odpowiadającą cyfrę poza polem formularza, aby uruchomić akcję.

> Przykład: przypisz Czat do 1, Postacie do 2, Sceny do 3 i Dopasuj do 0. W trakcie sesji klawisz 1 otworzy Czat, a 0 natychmiast pokaże całą mapę.

Docelowo Hotbar przyjmie makra, testy, ataki, umiejętności, przedmioty, rzuty i akcje systemowe. Elementy będą dodawane przez drag & drop z kart postaci, ekwipunku i Compendium.

## 10. Synchronizacja wielu graczy

### Co synchronizuje się teraz

- wiadomości czatu i brakująca historia po reconnect;
- obecność użytkowników;
- aktywna scena;
- tworzenie, ruch, obrót i usuwanie tokenów;
- tworzenie i zmiany ścian oraz drzwi;
- tworzenie, ruch i parametry świateł;
- tworzenie, ruch, rozmiar i parametry tiles.

### Prywatność

Ukryte tiles i tokeny nie są przekazywane graczom bez dostępu. Zmiana widocznego tile na ukryty powoduje usunięcie go z widoku gracza, podczas gdy MG zachowuje podgląd. Sekretne drzwi są prezentowane osobie bez uprawnień zarządzania jak zwykła ściana.

### Konflikt zmian

Elementy współdzielone mają numer rewizji. Jeżeli dwie osoby spróbują zapisać różne wersje tego samego elementu, serwer może odrzucić starszą zmianę. Odśwież scenę i ponów świadomą edycję zamiast nadpisywać cudzą pracę.

## 11. Przykładowe scenariusze pracy

### Przygotowanie oświetlonej karczmy

1. Utwórz scenę i dopasuj kwadratowy grid.
2. Narysuj ściany z M, V i L.
3. Dodaj drzwi i pozostaw je zamknięte.
4. Ustaw ciemność na 40%.
5. Dodaj ciepłe światła w palenisku i przy świecach.
6. Przeciągnij stoły i beczki jako tiles Background.
7. Dodaj animowany ogień jako tile Foreground i zablokuj go.
8. Przeciągnij postacie na Canvas.
9. Wykonaj preload i aktywuj scenę.

### Eksploracja lochu

1. Gracze poruszają wyłącznie własnymi tokenami.
2. MG otwiera drzwi dwuklikiem segmentu.
3. Zamknięte drzwi i ściany zatrzymują ruch oraz światło.
4. Gracz używa pomiaru, aby sprawdzić odległość do skrzyżowania.
5. MG ukrywa tile pułapki przed graczami i ujawnia go w odpowiednim momencie.
6. Do czasu wdrożenia Fog of War MG nie powinien umieszczać na wspólnej mapie sekretów, których nie da się ukryć jako oddzielne tiles lub tokeny.

### Przygotowanie obszaru zaklęcia

1. Wybierz **Szablony obszarowe**.
2. Wybierz okrąg, stożek lub prostokąt.
3. Przeciągnij podgląd od miejsca rzucającego.
4. Odczytaj zasięg.
5. Ustal trafione cele ręcznie; automatyczne targetowanie i obrażenia są planowane.

## 12. Wydajność i dobre praktyki

- Używaj obrazów dopasowanych rozdzielczością do sceny; bardzo duże pliki zwiększają czas wczytywania.
- Ogranicz liczbę jednocześnie odtwarzanych tiles wideo.
- Blokuj gotowe tiles, aby uniknąć przypadkowej edycji.
- Dziel długie mury na logiczne segmenty, szczególnie przy drzwiach i narożnikach.
- Przed aktywacją dużej sceny użyj preload tła.
- Zamykaj niepotrzebne okna, jeżeli zasłaniają Canvas; minimalizacja zachowuje szybki dostęp.
- Nie zapisuj sekretów w widocznej grafice tła. Użyj ukrytego tile albo osobnej sceny.

## 13. Rozwiązywanie problemów

### Nie mogę wybrać ikony

Wyszarzona ikona oznacza funkcję PLANOWANĄ. Jeżeli narzędzie powinno być dostępne tylko MG, sprawdź rolę i uprawnienia kampanii.

### Nie mogę aktywować sceny

Scena musi być oznaczona jako widoczna dla graczy. Otwórz ustawienia sceny, zaznacz **Widoczna dla graczy**, zapisz i ponów aktywację.

### Token nie chce się przesunąć

Sprawdź, czy token nie jest zablokowany, czy masz prawo kontroli oraz czy ruch nie przecina ściany lub zamkniętych drzwi. W razie utraty połączenia poczekaj na status online i odśwież dane.

### Tile nie daje się przesunąć lub skalować

Wybierz narzędzie Tiles, zaznacz element i sprawdź stan kłódki. Ukryty tile jest widoczny dla MG z obniżonym kryciem, ale nie pojawia się graczom.

### Światło przechodzi przez przeszkodę

Zaznacz segment ściany i upewnij się, że przełącznik **L** jest aktywny. Sprawdź też, czy drzwi nie są otwarte.

### Mapa jest zgubiona poza ekranem

Naciśnij **0** lub **Home**, albo użyj przycisku **Dopasuj**.

### Czat nie pokazuje najnowszych wiadomości

Sprawdź wskaźnik połączenia. Po reconnect aplikacja synchronizuje braki. Możesz użyć przycisku odświeżenia w nagłówku czatu; nie uruchamia on cyklicznego pollingu.

## 14. Macierz funkcji

| Obszar | Status | Najważniejsze ograniczenie obecnej wersji |
|---|---|---|
| Sceny i grid | DOSTĘPNE | Preload jest lokalny dla przeglądarki. |
| Tokeny i Actorzy | DOSTĘPNE | Tworzenie i kontrola działają; rozbudowane zasoby oraz statusy tokena są planowane. |
| Pomiar i AoE | DOSTĘPNE | Podgląd lokalny, bez zapisu i automatyzacji efektów. |
| Ściany i drzwi | CZĘŚCIOWO | Ruch i światło działają; indywidualne widzenie czeka na wdrożenie. |
| Światło | CZĘŚCIOWO | Brak token vision i trybów percepcji. |
| Tiles | DOSTĘPNE | Brak zdarzeń/makr przypisanych do tile. |
| Czat | CZĘŚCIOWO | Obecnie wiadomości ogólne; IC/OOC/whisper są planowane. |
| Postacie | DOSTĘPNE | Zaawansowane efekty i automatyzacja są planowane. |
| Sklep | DOSTĘPNE | Korzysta z istniejącego modułu i jego uprawnień. |
| Okna i Hotbar | DOSTĘPNE | Hotbar obsługuje obecnie akcje systemowe. |
| Combat Tracker | PLANOWANE | Brak aktywnego modułu walki. |
| Journal/Handouty | PLANOWANE | Brak aktywnego edytora dokumentów. |
| Compendium i Roll Tables | PLANOWANE | Brak aktywnych paneli danych. |
| Audio i ambient | PLANOWANE | Brak odtwarzania i źródeł mapowych. |
| Rysunki, notatki, regiony, fog | PLANOWANE | Ikony są nieaktywne. |

## 15. Skrócona ściąga

| Czynność | Sterowanie |
|---|---|
| Przesuń mapę | Przeciągnij pusty Canvas lewym lub środkowym przyciskiem. |
| Zoom pod kursorem | Rolka myszy. |
| Dopasuj mapę | 0, Home lub przycisk Dopasuj. |
| Otwórz panel | Pojedyncze kliknięcie ikony prawego paska. |
| Otwórz okno | Dwuklik ikony prawego paska. |
| Przesuń okno | Przeciągnij jego nagłówek. |
| Uruchom Hotbar | Klawisze 1–9 lub 0. |
| Usuń akcję Hotbara | Prawy przycisk na slocie. |
| Wyczyść pomiar | Prawy przycisk lub Esc. |
| Rysuj ścianę bez snap | Przytrzymaj Alt. |
| Szybko otwórz/zamknij drzwi | Dwuklik segmentu drzwi. |
| Utwórz token | Przeciągnij postać z okna Postacie na Canvas. |
| Utwórz tile | Przeciągnij grafikę albo użyj formularza URL. |

## 16. Kierunek dalszego rozwoju

Kolejne funkcje powinny rozwijać wspólny łańcuch danych **Actor → Token → Scene → Combat → Effects → Chat → Journal → Compendium**. Interaktywne regiony rozszerzą go do **Region → Event → Macro → Actor/Token/Scene**. Narzędzia opisane jako PLANOWANE mają korzystać z istniejących modeli uprawnień, API i WebSocketów, dzięki czemu użytkownik pozostanie w jednym workspace bez przeładowania strony.

> Koniec podręcznika. W przypadku różnicy między dokumentem a interfejsem sprawdź datę stanu aplikacji na stronie tytułowej — aktywnie rozwijane narzędzia mogą otrzymywać nowe możliwości.
