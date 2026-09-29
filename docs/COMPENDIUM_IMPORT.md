# Import Kompendium WFRP2

Moduł przechowuje źródłowe dokumenty i niezmienne rewizje oddzielnie od encji świata, profili mechanicznych oraz danych kampanii. Import wiki domyślnie nie publikuje treści graczom: rekordy otrzymują status `unverified`, widoczność `gm` i wymagają jawnego ujawnienia konkretnej rewizji.

## Uruchomienie

Paczki ZIP są montowane przez `docker-compose.yml` tylko do odczytu w `/var/www/compendium`.

```bash
docker compose exec php php spark migrate

# Walidacja strumieniowa, bez zapisu artykułów
docker compose exec php php spark compendium:import-wfrp2

# Import pełnego korpusu i zastosowanie map architektury
docker compose exec php php spark compendium:import-wfrp2 \
  /var/www/compendium/BlatyRPG-Kompendium-PL.zip \
  --architecture /var/www/compendium/BlatyRPG-Kompendium-WFRP2-Architektura.zip \
  --publish --universe old_world --batch 100

# Niezależna synchronizacja istniejących katalogów WFRP2 aplikacji
docker compose exec php php spark compendium:sync-wfrp2-catalog old_world

# Import wybranych haseł z podręcznika głównego oraz profili bestiariusza
docker compose exec php php spark compendium:import-wfrp2-corebook

# Kontrola pełności, wyszukiwania i granicy uprawnień na kampanii z aktywnym graczem
docker compose exec php php spark compendium:audit 5
```

Korpus podręcznika znajduje się w
`backend/app/Data/Compendium/wfrp2-corebook-pl.json`. Nie przechowuje pełnych
rozdziałów. Zawiera wybrane, zwięzłe hasła dotyczące zasad i świata, osobno
oznaczone miejsca, wydarzenia i tajemnice scenariusza `Przez ostępy Drakwaldu`
oraz ustrukturyzowane profile bestiariusza.

Zakresy są rozdzielone polem `scope` i kategoriami:

- `rules` — pojęcia oraz zagadnienia mechaniczne;
- `world` — informacje o świecie, miejsca, osoby i wydarzenia historyczne;
- `scenario` — treści i spoilery konkretnego scenariusza;
- `bestiary` — stworzenia i zwierzęta.

Profile mechaniczne obejmują 19 stworzeń i zwierząt, czterech nazwanych BN ze
scenariusza (Gerharda Schillera, Babunię Moescher, Hansa Baumera i Ojca
Dietricha) oraz 11 generycznych archetypów BN ze stron 245–247. Archetypy takie
jak `Kieszonkowiec`, `Kowal` czy `Najmita` są postaciami generycznymi: ich nazwa
opisuje rolę, a nie imię i nazwisko. Pole `npcKind` oraz interfejs rozróżniają je
od postaci imiennych. Ojciec Dietrich zachowuje jednoczłonowe imię podane w
źródle — importer nie dopowiada mu nazwiska. Dla konia wierzchowego i rumaka
zastosowana jest errata ze strony PDF 269.

Walidacja bez zapisu do bazy:

```bash
docker compose exec php php spark compendium:import-wfrp2-corebook --validate
```

Po zmianie źródłowego PDF korpus można odtworzyć deterministycznie. Generator
sprawdza plik źródłowy i buduje wyłącznie wybrane hasła; nie kopiuje pełnych
rozdziałów ani ilustracji:

```bash
python3 -m pip install pypdfium2
python3 tools/extract-wfrp2-corebook.py
```

Importer jest idempotentny: niezmieniony rekord nie tworzy nowej rewizji.
Każda rewizja zachowuje sumę kontrolną PDF, numery stron i status pochodzenia
`copyrighted_user_supplied`.

Przerwany import można wznowić za pomocą `--resume ID`. Ponowne uruchomienie tej samej paczki nie tworzy rewizji ani encji ponownie. Wycofanie pojedynczego importu źródłowego:

```bash
docker compose exec php php spark compendium:rollback-import ID
```

Rollback zmienia wyłącznie wskaźniki treści źródłowej. Notatki, ujawnienia i instancje kampanii pozostają nienaruszone, a późniejsza wersja utworzona przez redaktora nie jest nadpisywana.

## Interfejs

- `/campaigns/:campaignId/compendium` — pełny czytnik uczestnika kampanii;
- `/worlds/:universeId/compendium` — pełny workspace właściciela, redaktora lub administratora;
- pojedynczy klik „Kompendium” przy stole — kompaktowy panel lista/czytnik;
- podwójny klik — pełna Biblioteka bez mnożenia okien artykułów.
- `/#/admin` → **Kompendium** — globalny pulpit administratora: światy,
  pełna redakcja, kolejka weryfikacji, źródła, profile WFRP2, assety i historia
  importów.

W trybie **Redakcja** obrazy i PDF-y dodaje się z paska nad publiczną treścią
lub notatkami MG. Obraz jest od razu widoczny w miejscu kursora, ale dokument
zapisuje wyłącznie stabilne `assetId`; chroniony adres pliku jest rozwiązywany
dopiero podczas odczytu po sprawdzeniu uprawnień.

Zarejestrowane ilustracje można uzupełnić z panelu właściwości pełnego workspace. Pliki korzystają z prywatnego magazynu i limitów Handoutów (10 MiB obraz, 25 MiB PDF) oraz limitu świata `COMPENDIUM_WORLD_QUOTA_BYTES` (domyślnie 500 MiB).
