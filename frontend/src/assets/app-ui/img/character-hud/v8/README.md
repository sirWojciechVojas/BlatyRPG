# Wariant WWW — Full HD / QHD

| Ekran / viewport CSS | Rozmiar HUD-u | Skala | Odstęp ram XP | Transfer obrazów |
|---|---|---:|---:|---:|
| 1920 × 1080 | **804 × 282 px** | 75% | 3 px | 202206 B ≈ 197 KiB |
| 2560 × 1440 | **1072 × 376 px** | 100% | 4 px | 263838 B ≈ 258 KiB |

| Obraz | Piksele | Bajty | Profile |
|---|---|---:|---|
| hud-shell.webp | 1072×376 | 195408 | qhd |
| hud-shell-fhd.webp | 804×282 | 133776 | fhd |
| hud-atlas.webp | 512×256 | 59112 | qhd, fhd |
| hp-fill.webp | 264×19 | 6032 | qhd, fhd |
| xp-min-fill.webp | 264×6 | 1530 | qhd, fhd |
| xp-max-fill.webp | 264×6 | 1756 | qhd, fhd |

Każdy profil pobiera 5 obrazów: swój shell, wspólny atlas oraz trzy tekstury wypełnień. Kompresja WebP jest bezstratna. Nie jest to pomiar czasu ładowania aplikacji. Osobny avatar, CSS i opcjonalny JSON są poza podaną sumą.

## Integracja

Załaduj `runtime.css`. Wersja 8 wymaga kontenera **hud-runtime__stage** wewnątrz głównego elementu. Podkład renderuje pseudoelement głównego kontenera, a stage skaluje avatar i kontrolki. Dzięki temu oba wymiary zmieniają się w tej samej proporcji.

```html
<div class="hud-runtime">
  <div class="hud-runtime__stage">
    <img class="hud-avatar-image" src="/avatar-z-aplikacji.png" alt="Avatar postaci" width="175" height="175">
    <div class="hud-hp-fill" style="--hp:0.75"></div>
    <span class="hud-sprite hud-sprite--hp-foreground hud-hp-foreground" aria-hidden="true"></span>
    <div class="hud-xp-fill hud-xp-fill--min" style="--xp:0.4"></div>
    <div class="hud-xp-fill hud-xp-fill--max" style="--xp:0.375"></div>
    <span class="hud-sprite hud-sprite--dice-d100 hud-die" aria-hidden="true"></span>
    <button class="hud-control hud-sprite hud-sprite--button-menu hud-menu--icon-character" aria-label="Postać">
      <span class="hud-sprite hud-sprite--icon-character" aria-hidden="true"></span>
    </button>
    <button class="hud-control hud-sprite hud-sprite--button-minus hud-minus" aria-label="Zmniejsz HP">
      <span class="hud-sprite hud-sprite--symbol-minus" aria-hidden="true"></span>
    </button>
    <button class="hud-control hud-sprite hud-sprite--button-plus hud-plus" aria-label="Zwiększ HP">
      <span class="hud-sprite hud-sprite--symbol-plus" aria-hidden="true"></span>
    </button>
    <button class="hud-control hud-sprite hud-sprite--button-3d hud-mode" aria-label="Przełącz widok kości">
      <span class="hud-mode-label">3D</span>
    </button>
  </div>
</div>
```

Przykład zawiera jedną ikonę menu. Pozostałe są adresowane tym samym atlasem i mają klasy pozycjonujące opisane w CSS oraz tablicę `controls` w manifeście. Obsługę kliknięć podłącz do istniejącego Vue. Nazwisko, HP, XP, Wydane/Wolne i wynik rzutu renderuj jako HTML. Parametry `--hp` i `--xp` przyjmują liczby od 0 do 1. Zmienia się odsłonięta szerokość wypełnienia; jego tekstura nie jest rozciągana w osi X.

## Wybór profilu

CSS wybiera Full HD poniżej 2240 px szerokości viewportu oraz QHD od 2240 px. Dla 1920×1080 daje to dokładnie 804×282 px, a dla 2560×1440 — 1072×376 px. Są to piksele viewportu CSS. Wymuszenie profilu: `data-profile="fhd"` lub `data-profile="qhd"` na głównym divie.

Otwór avatara ma 175×175 px w bazie QHD i 131,25×131,25 px po skalowaniu Full HD. Pojedynczy PNG ramy pozostaje w wymiarze 231×231 px z otworem 175×175 px. Wnęka obejmuje oba paski, a odstęp ich ram wynosi 4 px w QHD i 3 px w Full HD.

Do wdrożenia wystarcza katalog runtime. Osobne PNG i podglądy służą do edycji, więc aplikacja nie musi ich dodatkowo wczytywać. Publikuj pliki pod adresem konkretnej wersji, np. `/assets/character-hud/v8/`, i korzystaj z cache zasobów statycznych.

## Osadzenie przy dolnej krawędzi

`preview.html` w tym katalogu korzysta z lokalnych plików WebP i `preview.css`; otwórz go po rozpakowaniu całego katalogu. Samodzielny HTML z osadzonymi grafikami znajduje się w katalogu głównym pełnej paczki. Panel ma `position:fixed; left:50%; bottom:-6px; transform:translateX(-50%)`. Dolne 6 CSS px jest przycięte przez viewport. Pełny układ ma 804×282 px / 1072×376 px; widoczna wysokość to 276 px / 370 px. Nie stosuj dodatkowego przycięcia o 6 px.

HP kończy się na y261 w bazie QHD, menu zajmuje y272–308, a wspólna wnęka XP y314–368. Opisy i procenty są renderowane na paskach: y322 i y344, bez zmniejszania szerokości pasków. Po przycięciu oba paski pozostają w całości widoczne.

## Tekstura środka

Wnętrze za przyciskami i XP wykorzystuje dostarczony kafel 128×128 px od y266 do samego dołu, y376. Wokół HP dominuje drewno. Kolory i detale kafla zachowano bez zmian, a powtarzanie odbywa się w naturalnej skali QHD. Zewnętrzna konstrukcja i obrzeże wnęki XP pozostają drewniane. W runtime tekstura jest częścią podkładu — nie zwiększa liczby wczytywanych obrazów.

## HP i czytelne opisy XP

`hp-well.png` to nowa wnęka HP: 423×39 px, pozycja QHD (324,5; 226), marginesy 9-slice: góra 6, prawo 5, dół 4, lewo 5 px. W runtime jest scalona z podkładem, więc nadal wystarcza pięć obrazów.

Oba paski XP zachowują pełną szerokość 822 px w QHD / 616,5 px w Full HD. Opisy są na odpowiadających im paskach, procenty po prawej. Tekst ma 16 px w QHD / 12 px w Full HD i ciemny obrys. Między paskami jest 4 px / 3 px odstępu. Wydane/Wolne znajduje się po lewej od HP, PD po prawej.

## Kostka — korekta v8

Zmiany liczone w bazie QHD: średnica 144 → 149 px, środek (974; 274) → (974; 284), czyli 10 px w dół. Powiększenie jest względem środka, więc lewy górny narożnik ma teraz (899,5; 209,5). W Full HD zmiany wynoszą odpowiednio +3,75 px i +7,5 px.

Nowa rama wnęki ma zewnętrzną średnicę 166 px i otwór 149 px, pozycję (891; 201) oraz mocniejszą wewnętrzną krawędź i cień. Kostka i wnęka pozostają koncentryczne, mieszczą się w panelu i nie zasłaniają XP.
