# BlatyRPG — status projektu i prognoza zakończenia

**Data statusu:** 26.08.2026  
**Gałąź / rewizja:** `feature/campaignSession-module` / `475d6c0`  
**Zakres prognozy:** pełne v1 opisane w roadmapie VTT (etapy 0–13) oraz stabilizacja i wdrożenie.

## Wniosek zarządczy

- Ważony postęp całego zakresu v1 wynosi około **57%**.
- **VTT MVP:** 18.09.2026.
- **Feature complete v1:** 29.01.2027.
- **Release Beta:** 19.02.2027.
- **Go-live:** 15.03.2027.
- **Zamknięcie projektu po stabilizacji:** **26.03.2027** — przyjęty termin zakończenia (EAC, P50).
- Bufor P80 wskazuje **23.04.2027**, jeśli zmaterializują się ryzyka integracji, migracji lub zakresu.

Oryginalny plik v3 kończył plan 22.09.2026, ale nie miał zapisanej linii bazowej i prawie wszystkie zadania miały 0% postępu. W nowym planie ta data pozostaje deadline'em pierwotnego zobowiązania dla Go-live. Aktualna prognoza Go-live jest późniejsza o 174 dni kalendarzowe, a zamknięcie projektu o 185 dni.

## Status obszarów

| Obszar | Postęp | Ocena | Dowód / pozostała brama |
|---|---:|---|---|
| Rdzeń aplikacji | 100% | ukończony | Auth, sesje, kampanie, zaproszenia, postacie, sklep, kości, administracja i i18n są obecne w kodzie. |
| Fundament VTT | 59% | w toku | Sceny, tokeny, ściany, realtime i światło działają; otwarte są bramy wydajności, deterministycznego LOS oraz Fog of War. |
| Token / Actor | 78% | w toku | Ruch, obrót, uprawnienia, zasoby, ruch grupowy i akceptacja MG istnieją; pozostał test 100+ tokenów i regresja. |
| Ściany / drzwi | 72% | w toku | Persistencja, edycja i kolizje istnieją; trzeba domknąć semantykę drzwi/sekretów i test wydajności. |
| Vision / Lighting | 76% | w toku | Zaawansowane światła, darkness, global illumination i cienie od ścian są w kodzie; pozostają tryby percepcji i test deterministyczności. |
| Fog of War | 0% | nie rozpoczęty | Brak kompletnego przepływu eksploracji per użytkownik oraz reveal/hide/reset MG. |
| Combat | 63% | w toku | Autorytatywny stan walki, tury, realtime i tracker istnieją; otwarte są targetowanie oraz pełna brama reconnect/ACL. |
| Effects → automatyzacja | 0% poza fundamentami | zaplanowany | Active Effects, Journal, Compendium, Audio, interakcje, Modules/Hooks i bezpieczne makra tworzą główny pozostały zakres v1. |
| Release / wdrożenie | 0% | zaplanowany | Regresja, upgrade migracji, E2E, wydajność, UAT, monitoring, DR i stabilizacja są jawnie wydzielone. |

Postęp 57% jest roll-upem ważonym czasem trwania zadań liściowych: 236,1 dnia równoważnego wykonania z 416 dni planu. Nie jest liczony liczbą commitów.

## Bramy jakości z 26.08.2026

- Backend: **282/282 testów**, 1114 asercji — PASS.
- Frontend: **140/140 plików testowych, 538/538 testów** — PASS.
- Frontend lint: **bez błędów** — PASS.
- Frontend build produkcyjny: **zakończony poprawnie** — PASS.
- WebSocket: **68/68 testów** — PASS.
- Coverage backendu: brak sterownika coverage; wskaźnik nie został zmierzony.

## Założenia estymacji

1. Zakres obejmuje pełne v1 z `docs/vtt-implementation-roadmap.md`, a nie wyłącznie obecne VTT MVP.
2. Dostępne są równoległe strumienie backend, frontend i realtime/QA; kalendarz ma 5 dni roboczych po 8 godzin.
3. Etapy zależne pozostają sekwencyjne: Scene → Token → Walls → Vision → Fog → Combat → Effects → Journal → Compendium → Audio → Interactions → Modules/Hooks → Automation.
4. Przewidziano spowolnienie świąteczno-noworoczne i osobny czas na stabilizację; prace funkcjonalne nie są prowadzone do dnia Go-live.
5. Elementy opcjonalne z dawnego planu są w pełnym v1 tylko wtedy, gdy odpowiadają zatwierdzonej roadmapie. Redukcja zakresu wymaga formalnej decyzji.

## Najważniejsze ryzyka

1. Bieżące drzewo robocze ma bardzo szeroki zestaw zmian staged/unstaged i plików odtworzonych jako untracked. Przed zamknięciem etapów potrzebne są atomowe commity i czysta regresja.
2. Istnieją dwie migracje z tym samym prefiksem czasu `2026-08-26-030000`; należy nadać jednoznaczną kolejność przed wdrożeniem.
3. Etapy Fog, Effects, Journal, Compendium, Audio, interakcji, modułów i automatyzacji są nadal zasadniczym, niezerowym zakresem — termin wrześniowy nie jest realny dla pełnego v1.
4. Brak pomiaru coverage oraz brak zatwierdzonych wyników testów 100+ tokenów / 50 połączeń WebSocket utrzymują ryzyko jakości i wydajności.
5. Produkcyjny bundle jest duży (m.in. moduł kości około 3,1 MiB przed gzip), więc budżet wydajności musi wejść do bramy release.

## Artefakt harmonogramu

`BlatyRPG-Planner.v4.baseline.xml` jest plikiem Microsoft Project XML z 68 zadaniami, 64 zależnościami, linią bazową dla wszystkich zadań, procentem wykonania, datą statusu, milestone'ami, aktualnymi notatkami i pierwotnym deadline'em. Można go otworzyć bezpośrednio w Microsoft Project i zapisać jako MPP.

Źródłowy `BlatyRPG-Planner.v3.fixed.mpp` pozostawiono bez zmian. W środowisku nie ma licencjonowanego zapisującego silnika MPP; zapis binarny przez narzędzie ewaluacyjne byłby zablokowany lub uszkodziłby daty. Nie zastosowano fałszywego rozszerzenia `.mpp` do pliku XML.
