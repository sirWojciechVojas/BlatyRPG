#!/usr/bin/env python3
"""Build a curated WFRP2 Compendium corpus from the supplied PDF.

Requires pypdfium2 (``python -m pip install pypdfium2``). The generated JSON
contains selected rules concepts, setting lore, events and structured profiles.
It deliberately stores neither complete chapters nor book illustrations.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
from typing import Any

import pypdfium2 as pdfium


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_PDF = ROOT / "docs" / "Warhammer_FRP2_Rekonstrukcja_AI_269_stron.pdf"
DEFAULT_OUTPUT = ROOT / "backend" / "app" / "Data" / "Compendium" / "wfrp2-corebook-pl.json"
EXPECTED_PAGES = 269
STAT_KEYS = ("ww", "us", "k", "odp", "zr", "int", "sw", "ogd", "a", "zyw", "s", "wt", "sz", "mag", "po", "pp")


def split_items(value: str) -> list[str]:
    return [item.strip() for item in value.split("|") if item.strip()]


def stats(*values: Any) -> dict[str, Any]:
    if len(values) != len(STAT_KEYS):
        raise ValueError(f"Expected {len(STAT_KEYS)} statistics, got {len(values)}")
    return dict(zip(STAT_KEYS, values))


def topic(
    slug: str,
    title: str,
    scope: str,
    record_type: str,
    entry_type: str,
    category: str,
    summary: str,
    facts: tuple[str, ...],
    pdf_pages: tuple[int, ...],
    aliases: tuple[str, ...] = (),
    kind: str = "topic",
    identity: dict[str, Any] | None = None,
    chronology: dict[str, Any] | None = None,
) -> dict[str, Any]:
    printed_pages = [page - 1 if 2 <= page <= 266 else None for page in pdf_pages]
    record = {
        "id": f"corebook:{slug}",
        "type": record_type,
        "entryType": entry_type,
        "scope": scope,
        "kind": kind,
        "title": title,
        "aliases": list(aliases),
        "summary": summary,
        "category": category,
        "content": [{
            "title": "Najważniejsze informacje",
            "text": "\n\n".join(facts),
        }],
        "source": {
            "pdfPages": list(pdf_pages),
            "printedPages": printed_pages,
        },
        "identity": identity,
        "profile": None,
        "bestiary": False,
    }
    if chronology is not None:
        record["chronology"] = chronology
    return record


def profile(
    slug: str,
    title: str,
    printed_page: int,
    category: str,
    summary: str,
    values: tuple[Any, ...],
    skills: str = "",
    talents: str = "",
    special_rules: str = "",
    armour: str = "",
    armour_points: str = "",
    weapons: str = "",
    equipment: str = "",
    career: str = "",
    race: str = "",
    disposition: str = "hostile",
    token_size: int = 100,
    aliases: tuple[str, ...] = (),
    errata_applied: str | None = None,
) -> dict[str, Any]:
    source: dict[str, Any] = {
        "pdfPages": [printed_page + 1],
        "printedPages": [printed_page],
    }
    if errata_applied:
        source["pdfPages"].append(269)
        source["errataApplied"] = errata_applied
    return {
        "id": f"corebook:bestiary:{slug}",
        "type": "creature",
        "entryType": "creature",
        "scope": "bestiary",
        "kind": "creature",
        "title": title,
        "aliases": list(aliases),
        "summary": summary,
        "category": f"Bestiariusz: {category}",
        "content": [{"title": "Opis", "text": summary}],
        "source": source,
        "identity": None,
        "bestiary": True,
        "profile": {
            "attributes": stats(*values),
            "skills": split_items(skills),
            "talents": split_items(talents),
            "specialRules": split_items(special_rules),
            "armour": armour or None,
            "armourPoints": armour_points or None,
            "weapons": split_items(weapons),
            "equipment": split_items(equipment),
            "career": career or None,
            "race": race or None,
            "token": {
                "width": token_size,
                "height": token_size,
                "disposition": disposition,
                "hidden": False,
            },
        },
    }


CURATED_TOPICS = (
    # Rules concepts. These are concise reference entries, not chapter copies.
    topic(
        "rules:tests", "Testy cech i umiejętności", "rules", "concept", "general",
        "Zasady: Testy",
        "Podstawowa procedura rozstrzygania niepewnych działań w WFRP2.",
        (
            "Test wykonuje się rzutem 1k100. Wynik równy lub niższy od używanej cechy albo umiejętności oznacza powodzenie.",
            "Mistrz Gry wybiera właściwą cechę lub umiejętność, ustala modyfikator i interpretuje poziom powodzenia albo porażki.",
            "Testy przeciwstawne porównują wyniki dwóch stron; przewagę uzyskuje strona, która skuteczniej zdała swój test.",
        ),
        (12, 13),
    ),
    topic(
        "rules:difficulty", "Stopnie trudności", "rules", "concept", "general",
        "Zasady: Testy",
        "Modyfikatory opisujące, jak łatwe albo trudne jest działanie.",
        (
            "Stopień trudności zmienia wartość cechy używanej w teście. Dodatnie modyfikatory ułatwiają działanie, a ujemne je utrudniają.",
            "Okoliczności, narzędzia, presja czasu, pomoc innych osób i przewaga pozycyjna mogą zmienić stopień trudności.",
        ),
        (91, 130),
    ),
    topic(
        "rules:fate-fortune", "Punkty Przeznaczenia i Szczęścia", "rules", "concept", "general",
        "Zasady: Bohater",
        "Dwie powiązane pule reprezentujące wyjątkowy los Bohatera Gracza.",
        (
            "Punkty Szczęścia pozwalają między innymi powtórzyć nieudany test lub uzyskać dodatkową korzyść i odnawiają się zgodnie z zasadami gry.",
            "Punkt Przeznaczenia można trwale poświęcić, aby ocalić Bohatera przed śmiercią lub nieodwracalnym losem.",
        ),
        (145, 222),
    ),
    topic(
        "rules:character-creation", "Tworzenie Bohatera Gracza", "rules", "concept", "general",
        "Zasady: Tworzenie bohatera",
        "Kolejność tworzenia nowego Bohatera Gracza w WFRP2.",
        (
            "Tworzenie obejmuje wybór lub losowanie rasy, ustalenie cech, profesji początkowej, umiejętności, zdolności i szczegółów pochodzenia.",
            "Końcowym etapem jest zapisanie wyposażenia, historii, wyglądu oraz osobistych motywacji postaci.",
        ),
        (16, 28),
    ),
    topic(
        "rules:playable-races", "Grywalne rasy", "rules", "concept", "general",
        "Zasady: Tworzenie bohatera",
        "Cztery podstawowe rasy dostępne dla Bohaterów Graczy.",
        (
            "Podręcznik przedstawia ludzi, krasnoludy, elfy i niziołki. Każda rasa ma własne wartości początkowe cech, zdolności oraz perspektywę kulturową.",
            "Wybór rasy wpływa również na dostępne profesje początkowe, losowanie pochodzenia i Punktów Przeznaczenia.",
        ),
        (16, 23),
    ),
    topic(
        "rules:careers", "Profesje i Schemat Rozwoju", "rules", "concept", "general",
        "Zasady: Profesje",
        "Profesja określa rolę postaci oraz kierunek jej mechanicznego rozwoju.",
        (
            "Schemat Rozwoju wskazuje, które cechy można zwiększać oraz jakich umiejętności i zdolności można nauczyć się w danej profesji.",
            "Profesje dzielą się na podstawowe i zaawansowane. Zmiana profesji wymaga spełnienia warunków oraz wydania Punktów Doświadczenia.",
        ),
        (29, 89),
    ),
    topic(
        "rules:skills-talents", "Umiejętności i zdolności", "rules", "concept", "general",
        "Zasady: Umiejętności i zdolności",
        "Dwa podstawowe rodzaje możliwości wykraczających poza surowe wartości cech.",
        (
            "Umiejętności są testowane i mogą występować jako podstawowe lub zaawansowane. Ponowne nabycie umiejętności zapewnia specjalizację +10 albo +20.",
            "Zdolności zapewniają szczególne reguły, premie lub nowe możliwości i zazwyczaj nie wymagają osobnego testu.",
        ),
        (90, 105),
    ),
    topic(
        "rules:combat", "Rundy, inicjatywa i akcje w walce", "rules", "concept", "general",
        "Zasady: Walka",
        "Struktura starcia oraz kolejność działań uczestników.",
        (
            "Walka przebiega w rundach. Kolejność działania wyznacza inicjatywa, a zaskoczenie może ograniczyć działania na początku starcia.",
            "W swojej turze postać wykorzystuje akcje na ruch, atak, obronę, przeładowanie, rzucanie czarów i inne czynności opisane w zasadach.",
        ),
        (129, 136),
    ),
    topic(
        "rules:damage-healing", "Obrażenia, trafienia krytyczne i leczenie", "rules", "concept", "general",
        "Zasady: Walka",
        "Zasady utraty Żywotności, ochrony pancerza i konsekwencji ciężkich ran.",
        (
            "Obrażenia pomniejsza Wytrzymałość oraz Punkty Zbroi chroniące trafioną lokację. Pozostała wartość zmniejsza Żywotność.",
            "Po utracie Żywotności kolejne rany mogą powodować trafienia krytyczne. Leczenie zależy od rodzaju obrażeń, odpoczynku i pomocy medycznej.",
        ),
        (137, 144),
    ),
    topic(
        "rules:magic", "Magia i Wiatry Magii", "rules", "concept", "general",
        "Zasady: Magia",
        "Podstawowe założenia rzucania czarów i korzystania z energii magicznej.",
        (
            "Magia pochodzi z ośmiu Wiatrów Magii. Kolegialni czarodzieje uczą się kontrolować jeden z nich w ramach wybranej Tradycji.",
            "Rzucający wykonuje rzut kośćmi zależny od cechy Magia i musi osiągnąć wymagany poziom mocy zaklęcia. Splatanie magii i składniki mogą pomóc w rzuceniu czaru.",
            "Nieostrożne lub pechowe użycie mocy grozi Klątwą Tzeentcha i niebezpiecznymi efektami ubocznymi.",
        ),
        (147, 178),
    ),
    topic(
        "rules:insanity", "Punkty Obłędu", "rules", "concept", "general",
        "Zasady: Zdrowie psychiczne",
        "Miara psychicznych urazów wynikających z grozy Starego Świata.",
        (
            "Punkty Obłędu można otrzymać wskutek przerażających przeżyć, ciężkich obrażeń i zetknięcia z nadnaturalnym złem.",
            "Nagromadzenie Punktów Obłędu może prowadzić do trwałych zaburzeń psychicznych, które wpływają na zachowanie postaci.",
        ),
        (216, 220),
    ),
    topic(
        "rules:experience", "Punkty Doświadczenia", "rules", "concept", "general",
        "Zasady: Rozwój bohatera",
        "Nagroda służąca do rozwijania cech i możliwości postaci.",
        (
            "Punkty Doświadczenia przyznaje Mistrz Gry za ukończone przygody, realizację celów oraz dobre odgrywanie postaci.",
            "Wydaje się je zgodnie ze Schematem Rozwoju profesji, na zakup umiejętności i zdolności oraz na zmianę profesji.",
        ),
        (29, 224),
    ),

    # Setting lore. It is explicitly separated from adventure material.
    topic(
        "world:old-world", "Stary Świat", "world", "place", "place",
        "Świat: Stary Świat",
        "Region cywilizowanych krain ludzi, krasnoludów, elfów i niziołków, otoczony licznymi zagrożeniami.",
        (
            "Do najważniejszych państw należą Imperium, Bretonia i Kislev. Między nimi rozciągają się puszcze, góry, rzeki oraz ziemie, nad którymi władza cywilizacji jest słaba.",
            "Chaos, zielonoskórzy, nieumarli i skaveny stanowią stałe zagrożenie, często ukryte przed zwykłymi mieszkańcami.",
        ),
        (233, 238),
    ),
    topic(
        "world:empire", "Imperium", "world", "place", "place",
        "Świat: Imperium",
        "Największe państwo Starego Świata i główny bastion ludzkości przeciw Chaosowi.",
        (
            "Imperium jest związkiem prowincji rządzonych przez Książąt-Elektorów. W roku 2522 KI włada nim Imperator Karl Franz.",
            "Większość kraju pokrywają pradawne lasy, a miasta i wsie są wyspami cywilizacji pośród niebezpiecznych pustkowi.",
        ),
        (225, 226),
        aliases=("Cesarstwo",),
    ),
    topic(
        "world:imperial-provinces", "Prowincje Imperium", "world", "concept", "general",
        "Świat: Imperium",
        "Dziesięć krain elektorskich tworzących współczesne Imperium.",
        (
            "Prowincje to Averland, Hochland, Middenland, Nordland, Ostland, Ostermark, Reikland, Stirland, Talabekland i Wissenland.",
            "Dawny Solland włączono do Wissenlandu, a ziemie prowincji Drakwald podzielono między Nordland i Middenland.",
        ),
        (226,),
    ),
    topic(
        "world:imperial-election", "Elekcja Imperialna", "world", "concept", "general",
        "Świat: Imperium",
        "System wyboru Imperatora przez elektorów prowincji i najważniejszych dostojników religijnych.",
        (
            "Tron nie jest formalnie dziedziczny. Elektorzy wybierają spośród Książąt-Elektorów następcę uznawanego za namiestnika Sigmara.",
            "Głosy posiadają również najwyżsi dostojnicy kultów Sigmara i Ulryka oraz Starszy Krainy Zgromadzenia.",
        ),
        (228, 229),
    ),
    topic(
        "world:altdorf", "Altdorf", "world", "place", "place",
        "Świat: Miasta Imperium",
        "Stolica Imperium i Reiklandu oraz siedziba Karla Franza.",
        (
            "Altdorf leży u zbiegu Reiku i Talabeku. Mieszczą się tam Kolegia Magii, Akademia Inżynierów i główna Świątynia Sigmara.",
            "Port Reiklandzki czyni miasto jednym z najważniejszych centrów handlowych Imperium.",
        ),
        (227,),
    ),
    topic(
        "world:nuln", "Nuln", "world", "place", "place",
        "Świat: Miasta Imperium",
        "Ważny ośrodek handlu, nauki, artylerii i imperialnej inżynierii.",
        (
            "Miasto kontroluje ruch towarów z południowych prowincji. Słynie z uniwersytetów, Akademii Artylerii, ludwisarni i wielkich dział.",
            "Charakterystycznym elementem Nuln jest zwodzony most nad Reikiem.",
        ),
        (227,),
    ),
    topic(
        "world:talabheim", "Talabheim", "world", "place", "place",
        "Świat: Miasta Imperium",
        "Potężnie ufortyfikowane miasto położone w wielkim, żyznym zagłębieniu.",
        (
            "Skalista krawędź krateru i rozbudowany system umocnień uczyniły Talabheim twierdzą, której nie zdobyła dotąd wroga armia.",
            "Miasto czerpie znaczenie z rolnictwa oraz handlu prowadzonego Talabekiem i Starą Drogą Leśną.",
        ),
        (227,),
    ),
    topic(
        "world:middenheim", "Middenheim", "world", "place", "place",
        "Świat: Miasta Imperium",
        "Miasto Białego Wilka wzniesione na Górze Ulryka, stolica Middenlandu i centrum kultu Ulryka.",
        (
            "Do miasta prowadzą cztery wielkie wiadukty. Pod skałą rozciągają się naturalne i krasnoludzkie podziemia przecinane tunelami goblinów i skavenów.",
            "Burza Chaosu poważnie zniszczyła miasto, którego mieszkańcy nadal odpierają kultystów i potwory.",
        ),
        (227, 228),
        aliases=("Miasto Białego Wilka",),
    ),
    topic(
        "world:moot", "Kraina Zgromadzenia", "world", "place", "place",
        "Świat: Imperium",
        "Ojczyzna niziołków i formalna część Imperium.",
        (
            "Kraina słynie z żyznych wzgórz, silnych więzi rodzinnych i rozbudowanych domostw niziołków.",
            "Wybrany Starszy ma głos w Elekcji Imperialnej, choć mieszkańcy zwykle stronią od wielkiej polityki.",
        ),
        (228,),
    ),
    topic(
        "world:imperial-calendar", "Kalendarz Imperium", "world", "concept", "general",
        "Świat: Imperium",
        "Najpowszechniejszy ludzki system datowania w Imperium.",
        (
            "Rok pierwszy upamiętnia zwycięstwo Sigmara i Kurgana Żelaznobrodego nad zielonoskórymi na Przełęczy Czarnego Ognia.",
            "Domyślny czas gry w podręczniku to rok 2522 Kalendarza Imperium, zapisywany jako 2522 KI.",
        ),
        (228,),
        aliases=("KI",),
    ),
    topic(
        "world:karl-franz", "Karl Franz", "world", "person", "person",
        "Świat: Postacie",
        "Imperator, Książę-Elektor Reiklandu i książę Altdorfu w roku 2522 KI.",
        (
            "Karl Franz jest uznawany za przywódcę zdolnego jednoczyć skłócone rody elektorskie wobec wspólnego zagrożenia.",
            "Jego władza pozostaje zależna od poparcia elektorów, kultu Sigmara i wpływowych stronnictw Imperium.",
        ),
        (225, 226),
        identity={"givenName": "Karl", "familyName": "Franz", "displayName": "Karl Franz"},
    ),
    topic(
        "world:borys-todbringer", "Borys Todbringer", "world", "person", "person",
        "Świat: Postacie",
        "Książę-Elektor Middenlandu i graf Middenheim.",
        (
            "Borys Todbringer jest wpływowym rywalem politycznym Reiklandu i obrońcą ziem szczególnie dotkniętych przez Burzę Chaosu.",
            "Jego osobistym wrogiem jest Khazrak Jednooki, wódz zwierzoludzi z Drakwaldu.",
        ),
        (226, 252),
        aliases=("Boris Todbringer",),
        identity={"givenName": "Borys", "familyName": "Todbringer", "displayName": "Borys Todbringer"},
    ),
    topic(
        "world:sigmar-unification", "Zjednoczenie plemion przez Sigmara", "world", "event", "history",
        "Świat: Historia Imperium",
        "Początek Imperium i wspólnej państwowości ludzkich plemion.",
        (
            "W roku 1 KI Sigmar i jego wodzowie, wspierani przez krasnoludy króla Kurgana Żelaznobrodego, rozgromili wielką hordę zielonoskórych na Przełęczy Czarnego Ognia.",
            "Po zwycięstwie wodzowie plemion zawarli trwały sojusz, a Sigmar przyjął tytuł pierwszego Imperatora. Po jego odejściu ustanowili urząd elekcyjnego Imperatora.",
            "Z czasem Sigmar został uznany za boga opiekuńczego, a jego kult stał się główną religią Imperium.",
        ),
        (225, 226, 228),
        chronology={"precision": "year", "start": {"year": 1}},
    ),
    topic(
        "world:mandred-election", "Wybór Mandreda na Imperatora", "world", "event", "history",
        "Świat: Historia Imperium",
        "Zakończenie dziewięcioletniego kryzysu elektorskiego po śmierci Borysa Złotobiernego.",
        (
            "Po śmierci Borysa Złotobiernego w roku 1115 elektorzy przez dziewięć lat nie potrafili uzgodnić następcy.",
            "W roku 1124 na Imperatora wybrano księcia Mandreda z Middenheim, przywódcę oporu przeciw skavenom.",
        ),
        (228, 232),
        chronology={"precision": "year", "start": {"year": 1124}},
    ),
    topic(
        "world:mandred-assassination", "Zabójstwo Imperatora Mandreda", "world", "event", "history",
        "Świat: Historia Imperium",
        "Śmierć Mandreda zapoczątkowała długotrwały rozłam Imperium.",
        (
            "W roku 1152 Imperator Mandred został zamordowany, a elektorzy nie zdołali porozumieć się w sprawie następcy.",
            "Wojna między Stirlandem i Talabeklandem rozpoczęła epokę rywalizujących stronnictw imperialnych i osłabiła państwo na wiele stuleci.",
        ),
        (228,),
        chronology={"precision": "year", "start": {"year": 1152}},
    ),
    topic(
        "world:age-of-three-emperors", "Wiek Trzech Imperatorów", "world", "event", "history",
        "Świat: Historia Imperium",
        "Wielowiekowy okres rozbicia i rywalizacji o tron Imperium.",
        (
            "Kryzys elektorski rozpoczął się po zabójstwie Mandreda. W roku 1547 elektor Middenheim ogłosił się kolejnym Imperatorem, powołując się na pochodzenie od Mandreda.",
            "Imperium miało odtąd trzech rywalizujących władców, a prowincje prowadziły wojny o władzę i wpływy.",
            "Okres zakończyło dopiero ponowne zjednoczenie Imperium w obliczu wielkiego najazdu Chaosu.",
        ),
        (228, 229),
        chronology={"precision": "year", "start": {"year": 1547}},
    ),
    topic(
        "world:vampire-wars", "Wojny z Wampirami", "world", "event", "history",
        "Świat: Historia Imperium",
        "Seria wojen przeciw wampirzym władcom Sylvanii z rodu von Carsteinów.",
        (
            "W noc Geheimnisnacht roku 2010 Vlad von Carstein wezwał umarłych Sylvanii i rzucił wyzwanie rywalizującym Imperatorom.",
            "Władcy Sylvanii wykorzystywali armie nieumarłych do kolejnych ataków na osłabione Imperium.",
            "Po klęsce Manfreda von Carsteina Sylvanię włączono do Stirlandu, lecz zagrożenie nieumarłych nie zniknęło.",
        ),
        (230, 231, 232),
        chronology={"precision": "range", "start": {"year": 2010}, "end": {"year": 2132}},
    ),
    topic(
        "world:siege-of-altdorf", "Oblężenie Altdorfu przez Vlada von Carsteina", "world", "event", "history",
        "Świat: Historia Imperium",
        "Kulminacyjne starcie pierwszej fazy Wojen z Wampirami.",
        (
            "Zimą roku 2051 armia von Carsteinów obległa Altdorf, a Vlad zażądał od mieszkańców służby za życia albo po śmierci.",
            "Złodziej Felix Mann wykradł pierścień zapewniający Vladowi powrót do życia. Wielki Teogonista Wilhelm III poświęcił się, strącając wampira z murów na pale w fosie.",
        ),
        (231,),
        chronology={"precision": "year", "start": {"year": 2051}},
    ),
    topic(
        "world:manfred-attack", "Najazd Manfreda von Carsteina", "world", "event", "history",
        "Świat: Historia Imperium",
        "Ostatnia wielka ofensywa von Carsteinów opisana w historii Imperium.",
        (
            "W roku 2132 Manfred rozbił pospiesznie zebrane armie imperialne i zaatakował Altdorf na czele hordy nieumarłych.",
            "Wielki Teogonista Kurt III pozbawił go armii Wielkim Zaklęciem Uwolnienia. Zjednoczeni książęta wyparli Manfreda do Sylvanii i pokonali go na Bagnie Fenn.",
        ),
        (232,),
        chronology={"precision": "year", "start": {"year": 2132}},
    ),
    topic(
        "world:black-plague", "Czarna Zaraza i wojna ze skavenami", "world", "event", "history",
        "Świat: Historia Imperium",
        "Katastrofalna epidemia rozpoczęta przez skavenów w roku 1111 KI.",
        (
            "W roku 1111 skaveny zatruły źródła wody spaczeniem, wywołując epidemię, która zabiła niemal trzy czwarte ludności Imperium.",
            "Po śmierci Borysa Złotobiernego w roku 1115 książę Mandred z Middenheim zorganizował opór. Po trzech latach walk jego armia rozbiła lub wyparła niemal wszystkie oddziały szczuroludzi.",
        ),
        (232, 235),
        chronology={"precision": "range", "start": {"year": 1111}, "end": {"year": 1118}},
    ),
    topic(
        "world:skaven-marienburg", "Skaveński atak na port w Marienburgu", "world", "event", "history",
        "Świat: Historia Imperium",
        "Sabotaż portu przeprowadzony przez skaveńskich agentów i ich wspólników.",
        (
            "W roku 2320 skaveńscy szpiedzy, wspierani przez oszukanych żeglarzy i przekupionych dokerów, uderzyli na port w Marienburgu.",
            "Za pomocą dzbanów z palną substancją zatopili wiele statków, pokazując, że po dawnej wojnie skaveny nadal potrafią atakować Imperium z ukrycia.",
        ),
        (235,),
        chronology={"precision": "year", "start": {"year": 2320}},
    ),
    topic(
        "world:skaven-waldenhof", "Ataki skavenów w Sylvanii", "world", "event", "history",
        "Świat: Historia Imperium",
        "Skaveńskie napaści na zamek Siegfried i Waldenhof.",
        (
            "W roku 2387 skaveny podkopały mury zamku Siegfried w Sylvanii i zaatakowały zaskoczony garnizon od środka.",
            "Gdy książę Karsten z Waldenhofu odmówił zapłaty swoim dawnym sprzymierzeńcom, szczuroludzie napadli na miasto i porwali wszystkie dzieci.",
        ),
        (235,),
        chronology={"precision": "year", "start": {"year": 2387}},
    ),
    topic(
        "world:storm-of-chaos", "Burza Chaosu", "world", "event", "history",
        "Świat: Historia współczesna",
        "Wielka inwazja Archaona na północne prowincje Imperium.",
        (
            "Armie Chaosu spustoszyły północ, dotarły pod Middenheim i pozostawiły po sobie zrujnowane miasta, uchodźców oraz rozproszone bandy potworów.",
            "Archaon został odparty, ale wojna nie zakończyła lokalnych walk ani odbudowy zniszczonych ziem.",
        ),
        (228, 249, 259),
        aliases=("Najazd Archaona",),
        chronology={"precision": "year", "start": {"year": 2522}},
    ),
    topic(
        "world:imperial-gods", "Bogowie Imperium", "world", "concept", "general",
        "Świat: Religia",
        "Najważniejsze oficjalne i ludowe kulty ludzi Imperium.",
        (
            "Do najważniejszych bóstw należą Sigmar, Ulryk, Taal i Rhya, Morr, Shallya, Verena, Manann oraz Ranald.",
            "Kulty posiadają własne świątynie, kapłanów, święta, doktryny i obszary wpływów, a wiara przenika codzienne życie mieszkańców.",
        ),
        (179, 198),
    ),
    topic(
        "world:colleges-of-magic", "Kolegia Magii", "world", "concept", "general",
        "Świat: Magia",
        "Imperialne instytucje uczące bezpiecznego korzystania z ośmiu Wiatrów Magii.",
        (
            "Każde Kolegium skupia się na jednym Wietrze i odpowiadającej mu Tradycji. Czarodzieje podlegają prawu i kontroli swoich zakonów.",
            "Siedziby Kolegiów znajdują się w Altdorfie, a nauka stanowi legalną alternatywę dla niekontrolowanego czarnoksięstwa.",
        ),
        (147, 154, 227),
    ),
    topic(
        "world:bretonnia", "Bretonia", "world", "place", "place",
        "Świat: Kraje Starego Świata",
        "Feudalne królestwo rycerzy położone na zachód od Imperium.",
        (
            "Bretonię zjednoczył Gilles le Breton po zwycięstwach nad orkami i objawieniu Pani Jeziora.",
            "Społeczeństwo dzieli się na rycerską arystokrację oraz chłopstwo, a kult Pani Jeziora wyznacza ideały szlachty.",
        ),
        (235, 236),
    ),
    topic(
        "world:kislev", "Kislev", "world", "place", "place",
        "Świat: Kraje Starego Świata",
        "Północne królestwo i najważniejszy sojusznik Imperium w walce z Chaosem.",
        (
            "Kislev rozciąga się między Imperium, Morzem Szponów i Górami Krańca Świata. Północ kraju przechodzi w niebezpieczne ziemie Kraju Trolli.",
            "Surowy klimat, koczownicze tradycje północy i ciągłe najazdy ukształtowały wojowniczą kulturę mieszkańców.",
        ),
        (236, 238),
    ),

    # Adventure records. Their category and scope keep spoilers away from lore.
    topic(
        "scenario:through-the-drakwald", "Przez ostępy Drakwaldu", "scenario", "concept", "general",
        "Scenariusz: Przez ostępy Drakwaldu",
        "Krótki scenariusz dla początkujących Bohaterów Graczy, rozgrywany po Burzy Chaosu.",
        (
            "Przygoda zaczyna się w Untergardzie. Po ataku mutantów i wieściach o nadciągającej hordzie mieszkańcy ruszają do Middenheim.",
            "Wędrówka łączy obronę uchodźców, śledztwo, spotkania w Drakwaldzie i finał związany z tajemnicą Babuni Moescher.",
        ),
        (249, 259),
        kind="scenario",
    ),
    topic(
        "scenario:untergard", "Untergard", "scenario", "place", "place",
        "Scenariusz: Miejsca",
        "Miasteczko nad rzeką i ważny punkt przeprawy przez Drakwald.",
        (
            "Untergard założyli uciekinierzy z okolic Grimminhagen. Kamienny most uczynił osadę ośrodkiem handlowym i strategicznym punktem wojskowym.",
            "Po dziewięciodniowej bitwie ze zwierzoludźmi, zarazie i zniszczeniu wschodniej dzielnicy pozostało około 75 mieszkańców.",
        ),
        (249, 250),
        kind="scenario_place",
    ),
    topic(
        "scenario:grimminhagen", "Grimminhagen", "scenario", "place", "place",
        "Scenariusz: Miejsca",
        "Zrujnowane miasto leżące na trasie uchodźców z Untergardu.",
        (
            "Oddziały Archaona złupiły Grimminhagen. Nieliczni ocalali próbują przetrwać w ruinach, a ślady zniszczeń przypominają o niedawnej wojnie.",
        ),
        (254, 255),
        kind="scenario_place",
    ),
    topic(
        "scenario:immelscheld", "Immelscheld", "scenario", "place", "place",
        "Scenariusz: Miejsca",
        "Splądrowane podczas Burzy Chaosu miasteczko, przy którym zatrzymuje się kolumna uchodźców.",
        (
            "Immelscheld znajduje się w stanie podobnym do Grimminhagen: większość zabudowy jest zrujnowana, a ocalali gnieżdżą się pośród ruin.",
            "Postój staje się początkiem finałowego śledztwa dotyczącego zniknięcia Babuni Moescher.",
        ),
        (257,),
        kind="scenario_place",
    ),
    topic(
        "scenario:fahndorf", "Fahndorf", "scenario", "place", "place",
        "Scenariusz: Miejsca",
        "Rodzinna wieś Babuni Moescher i miejsce śmierci jej ojca.",
        (
            "Wieś została zniszczona przez ludzi grafa Sternhauera. Jej ruiny leżą niedaleko rozstajów na trasie do Middenheim.",
            "Babunia wybiera Fahndorf jako miejsce odprawienia rytuału zemsty.",
        ),
        (256, 258),
        kind="scenario_place",
    ),
    topic(
        "scenario:battle-of-untergard", "Dziewięciodniowa bitwa o Untergard", "scenario", "event", "event",
        "Scenariusz: Wydarzenia",
        "Obrona mostu i miasta przed zwierzoludźmi Khazraka Jednookiego.",
        (
            "Zwierzoludzie zdobyli wschodnią część miasta, lecz obrońcy utrzymali most przez dziewięć dni dzięki wsparciu imperialnych żołnierzy i krasnoludów.",
            "Khazrak wycofał się na północ, pozostawiając zniszczone miasto i setki poległych.",
        ),
        (249, 250),
        kind="scenario_event",
    ),
    topic(
        "scenario:mutant-attack", "Atak mutantów na Ackerplatz", "scenario", "event", "event",
        "Scenariusz: Wydarzenia",
        "Pierwsze bezpośrednie starcie scenariusza w Untergardzie.",
        (
            "Podczas przemowy kapitana Schillera grupa mutantów atakuje przez most, a ukryty strzelec otwiera ogień ze zrujnowanego zajazdu.",
            "Zdarzenie poprzedza wieść o większym zagrożeniu i decyzję o opuszczeniu miasta.",
        ),
        (252, 253),
        kind="scenario_event",
    ),
    topic(
        "scenario:untergard-evacuation", "Ewakuacja Untergardu", "scenario", "event", "event",
        "Scenariusz: Wydarzenia",
        "Wymarsz mieszkańców zagrożonych przez nadciągającą hordę zwierzoludzi.",
        (
            "Kapitan Schiller organizuje kolumnę uchodźców i prowadzi ją przez Drakwald w kierunku Middenheim.",
            "Bohaterowie Graczy mogą pełnić role obrońców, zwiadowców i śledczych odpowiedzialnych za bezpieczeństwo grupy.",
        ),
        (253, 254),
        kind="scenario_event",
    ),
    topic(
        "scenario:crossroads-ambush", "Zasadzka na rozstajach", "scenario", "event", "event",
        "Scenariusz: Wydarzenia",
        "Ślady ataku na podróżnych odnalezione na drodze uchodźców.",
        (
            "Ciała i rozbite wozy blokują drogę, a ukryte pułapki nadal stanowią zagrożenie. Ojciec Dietrich zostaje śmiertelnie ranny.",
            "Kapłan powierza bohaterom relikwię Sigmara i prosi o dostarczenie jej do świątyni w Middenheim.",
        ),
        (256,),
        kind="scenario_event",
    ),
    topic(
        "scenario:moescher-ritual", "Rytuał zemsty Babuni Moescher", "scenario", "event", "event",
        "Scenariusz: Tajemnice",
        "Finałowe wydarzenie scenariusza i główny sekret Babuni Moescher.",
        (
            "Babunia udaje się do ruin Fahndorfu, aby przywołać istotę mającą wymordować rodzinę Sternhauerów w odwecie za śmierć jej ojca.",
            "Rytuał w rzeczywistości otwiera przejście do Domeny Chaosu i przywołuje demona. Bohaterowie mają niewiele czasu, by przerwać obrzęd.",
        ),
        (257, 258),
        kind="scenario_secret",
    ),
)


BESTIARY = (
    profile("goblin", "Goblin", 239, "Stwory Ciemności", "Niewielki, zielonoskóry i kłótliwy goblinoid, groźny przede wszystkim liczebnością i podstępem.",
            (25, 30, 30, 30, 25, 25, 30, 20, 1, 8, 3, 3, 4, 0, 0, 0),
            "jeździectwo albo pływanie|skradanie się|spostrzegawczość|sztuka przetrwania|ukrywanie się|wspinaczka|znajomość języka (gobliński)",
            "widzenie w ciemności", "Elfiaki som straszni", "lekki pancerz (skórzany kaftan)", "głowa 0, ręce 0, korpus 1, nogi 0", "broń jednoręczna|krótki łuk albo włócznia"),
    profile("mutant", "Mutant", 240, "Stwory Ciemności", "Człowiek trwale wypaczony przez Chaos, zwykle odrzucony przez społeczeństwo i przygarnięty przez inne sługi Chaosu.",
            (31, 31, 31, 31, 31, 31, 31, 31, 1, 11, 3, 3, 4, 0, 0, 0),
            "opieka nad zwierzętami|skradanie się|spostrzegawczość|sztuka przetrwania|ukrywanie się|znajomość języka (mroczna mowa albo staroświatowy)",
            "chodu!", "Mutacje Chaosu: 1k10 określa liczbę mutacji", "brak", "głowa 0, ręce 0, korpus 0, nogi 0", "broń jednoręczna (pałka)"),
    profile("ork", "Ork", 240, "Stwory Ciemności", "Silny, agresywny zielonoskóry wojownik, który rozstrzyga spory przemocą i nienawidzi krasnoludów.",
            (35, 35, 35, 45, 25, 25, 30, 20, 1, 12, 3, 4, 4, 0, 0, 0),
            "jeździectwo albo pływanie|spostrzegawczość|sztuka przetrwania|torturowanie|wspinaczka|zastraszanie|znajomość języka (gobliński)",
            "bijatyka|groźny|silny cios|widzenie w ciemności", "Nienawiść|Siekacz: +1 do obrażeń w pierwszej rundzie", "średni pancerz (koszulka kolcza, skórzana kurta i skórzany czepiec)", "głowa 1, ręce 1, korpus 3, nogi 0", "siekacz|sztylet albo łuk"),
    profile("skaven", "Skaven", 241, "Stwory Ciemności", "Człekokształtny szczurolud z podziemnego imperium, posługujący się podstępem, zarazą i skrytobójstwem.",
            (30, 25, 30, 30, 40, 25, 25, 15, 1, 9, 3, 3, 5, 0, 0, 0),
            "broń specjalna (proce)|pływanie|skradanie się|spostrzegawczość|sztuka przetrwania|ukrywanie się|wspinaczka|znajomość języka (queekish)",
            "grotołaz|widzenie w ciemności", "brak", "lekki pancerz (skórzana kurta i skórzany czepiec)", "głowa 1, ręce 1, korpus 1, nogi 0", "broń jednoręczna (miecz)|sztylet albo proca"),
    profile("zwierzoczlek", "Zwierzoczłek", 241, "Stwory Ciemności", "Naznaczona Chaosem hybryda człowieka i zwierzęcia, polująca w leśnych zbrojnych stadach.",
            (40, 25, 35, 45, 35, 25, 25, 25, 1, 12, 3, 4, 5, 0, 0, 0),
            "skradanie się|spostrzegawczość|sztuka przetrwania|śledzenie|tropienie|ukrywanie się|zastraszanie|znajomość języka (mroczna mowa)",
            "groźny|wędrowiec|wyostrzone zmysły", "Mutacje Chaosu: zwierzęce odnóża i rogi, 25% szans na dodatkową mutację|Mieszkaniec lasów", "lekki pancerz (skórzana kurta)", "głowa 0, ręce 1, korpus 1, nogi 0", "broń jednoręczna albo włócznia|rogi (obrażenia S-1)|tarcza"),
    profile("demoniczny-chochlik", "Demoniczny chochlik", 241, "Stwory Ciemności", "Mały skrzydlaty demon, często pełniący rolę posłańca potężniejszych istot z Domeny Chaosu.",
            (33, 0, 40, 33, 40, 30, 33, 15, 1, 12, 4, 3, "3(6)", 0, 0, 0),
            "język tajemny (demoniczny)|spostrzegawczość|unik|zastraszanie|znajomość języka (mroczna mowa)",
            "broń naturalna|lewitacja|nieustraszony|oburęczność|straszny|widzenie w ciemności", "Mutacje Chaosu: 1k10 określa 1-3 mutacje", "brak", "głowa 0, ręce 0, korpus 0, nogi 0", "pazury", token_size=70),
    profile("pomniejszy-demon", "Pomniejszy demon", 242, "Stwory Ciemności", "Wysłannik Mrocznych Bogów przywoływany do Starego Świata w celu wykonania konkretnego zadania.",
            (50, 40, 45, 45, 50, 35, 50, 15, 2, 15, 4, 4, "4(6)", 0, 0, 0),
            "język tajemny (demoniczny)|spostrzegawczość|unik|zastraszanie|znajomość języka (mroczna mowa)",
            "broń naturalna|latanie|nieustraszony|oburęczność|silny cios|straszny|widzenie w ciemności", "Mutacje Chaosu: 1k10 określa 1-4 mutacje", "brak", "głowa 0, ręce 0, korpus 0, nogi 0", "pazury"),
    profile("szkielet", "Szkielet", 242, "Stwory Ciemności", "Ożywione przez nekromantę kości zmarłego; bezrozumne, nieustraszone i powolne.",
            (25, 20, 30, 30, 25, None, None, None, 1, 10, 3, 3, 4, 0, 0, 0),
            "", "ożywieniec|straszny", "Bezrozumne|Powolne: nie może wykonywać akcji bieg", "lekki pancerz (skórzany kaftan i skórzany czepiec)", "głowa 1, ręce 0, korpus 1, nogi 0", "broń jednoręczna|sztylet albo łuk"),
    profile("upior", "Upiór", 243, "Stwory Ciemności", "Ożywiony pradawny wojownik z kurhanu, inteligentniejszy i znacznie groźniejszy od szkieletu.",
            (40, 35, 45, 45, 30, 25, 35, 20, 1, 15, 4, 4, 4, 0, 0, 0),
            "spostrzegawczość|znajomość języka (klasyczny)", "ożywieniec|straszny", "Upiorna broń: magiczna, +2 do obrażeń i dwa rzuty trafienia krytycznego", "średni pancerz (zbroja kolcza)", "głowa 3, ręce 3, korpus 3, nogi 3", "upiorna broń|tarcza"),
    profile("zombi", "Zombi", 243, "Stwory Ciemności", "Ożywione, gnijące zwłoki używane przez nekromantów i wampiry jako bezrozumni żołnierze.",
            (25, 0, 35, 35, 10, None, None, None, 1, 12, 3, 3, 4, 0, 0, 0),
            "", "ożywieniec|straszny", "Bezrozumne|Powolne: nie może wykonywać akcji bieg", "lekki pancerz (skórzana kurta)", "głowa 0, ręce 1, korpus 1, nogi 0", "broń jednoręczna"),
    profile("kuc", "Kuc", 243, "Zwierzęta", "Zwierzę juczne i wierzchowiec odpowiedni dla krasnoludów oraz niziołków.",
            (25, 0, 35, 35, 35, 10, 10, 0, 0, 12, 3, 3, 6, 0, 0, 0), "pływanie|spostrzegawczość", "czuły słuch|wyostrzone zmysły", disposition="neutral"),
    profile("kon-wierzchowy", "Koń wierzchowy", 243, "Zwierzęta", "Popularny podjezdek używany przez rycerzy i szlachtę Imperium.",
            (25, 0, 38, 38, 30, 10, 10, 0, 0, 12, 3, 3, 8, 0, 0, 0), "pływanie|spostrzegawczość +10", "czuły słuch|wyostrzone zmysły", disposition="neutral", token_size=150,
            errata_applied="Zastosowano erratę ze strony PDF 269: profil konia wierzchowego zamieniono z profilem rumaka."),
    profile("lekki-kon-bojowy", "Lekki koń bojowy", 243, "Zwierzęta", "Koń kawaleryjski przyuczony do walki, gryzienia i kopania, niepłoszący się na widok krwi.",
            (30, 0, 40, 40, 30, 10, 10, 0, 1, 14, 4, 4, 8, 0, 0, 0), "pływanie|spostrzegawczość +10", "broń naturalna|czuły słuch|silny cios|wyostrzone zmysły", disposition="neutral", token_size=150),
    profile("rumak", "Rumak", 244, "Zwierzęta", "Ciężki koń bojowy rycerstwa zakonnego i pancernej konnicy, tresowany do noszenia ciężkiego oporządzenia.",
            (30, 0, 45, 45, 30, 10, 10, 0, 1, 18, 4, 4, 8, 0, 0, 0), "pływanie|spostrzegawczość +10", "broń naturalna|czuły słuch|silny cios|wyostrzone zmysły", disposition="neutral", token_size=150,
            errata_applied="Zastosowano erratę ze strony PDF 269: profil rumaka zamieniono z profilem konia wierzchowego."),
    profile("pies-bojowy", "Pies bojowy", 244, "Zwierzęta", "Duży i agresywny pies szkolony do walki, polowania albo ochrony.",
            (41, 0, 32, 38, 30, 15, 43, 0, 1, 10, 3, 3, 6, 0, 0, 0), "pływanie|spostrzegawczość +20|tropienie", "broń naturalna|silny cios|wyostrzone zmysły", disposition="neutral"),
    profile("kruk", "Kruk", 244, "Zwierzęta", "Padlinożerny ptak zwabiany przez pola bitew i błyszczące przedmioty.",
            (38, 0, 10, 10, 38, 12, 24, 0, 2, 6, 1, 1, "2(8)", 0, 0, 0), "spostrzegawczość +20", "bystry wzrok|latanie|wyostrzone zmysły", disposition="neutral", token_size=50),
    profile("niedzwiedz", "Niedźwiedź", 244, "Zwierzęta", "Górski niedźwiedź, najczęściej spotykany w Starym Świecie gatunek tego drapieżnika.",
            (33, 0, 52, 47, 25, 10, 25, 0, 2, 20, 5, 4, 4, 0, 0, 0), "pływanie|spostrzegawczość", "broń naturalna|morderczy atak|niepokojący|silny cios|wyostrzone zmysły", token_size=150),
    profile("pies-domowy", "Pies domowy", 244, "Zwierzęta", "Pospolity pies gospodarski; zdziczałe sfory mogą być groźne dla podróżnych.",
            (25, 0, 21, 21, 30, 15, 30, 0, 1, 6, 2, 2, 6, 0, 0, 0), "pływanie|spostrzegawczość +20|tropienie", "broń naturalna|chodu!|wyostrzone zmysły", disposition="neutral"),
    profile("wilk", "Wilk", 244, "Zwierzęta", "Drapieżnik północnych prowincji Imperium, uznawany za święte zwierzę przez kapłanów Ulryka.",
            (30, 0, 30, 30, 40, 14, 25, 0, 1, 10, 3, 3, 6, 0, 0, 0), "pływanie|spostrzegawczość +10|tropienie", "broń naturalna|wyostrzone zmysły"),
)


CREATURE_BESTIARY = BESTIARY


def generic_npc(
    slug: str,
    title: str,
    printed_pages: tuple[int, ...],
    summary: str,
    values: tuple[Any, ...],
    skills: str,
    talents: str,
    armour: str,
    armour_points: str,
    weapons: str,
    equipment: str,
    career: str,
    race: str = "człowiek",
) -> dict[str, Any]:
    record = profile(
        slug, title, printed_pages[0], "Bohaterowie Niezależni", summary, values,
        skills, talents, armour=armour, armour_points=armour_points,
        weapons=weapons, equipment=equipment, career=career, race=race,
        disposition="neutral",
    )
    record.update({
        "id": f"corebook:npc:generic:{slug}",
        "type": "person",
        "entryType": "creature",
        "scope": "bestiary",
        "kind": "generic_npc",
        "npcKind": "generic",
        "category": "Bohaterowie niezależni: Generyczni",
        "identity": {
            "givenName": None,
            "familyName": None,
            "honorific": None,
            "displayName": title,
            "sourceSuppliedName": False,
            "archetype": True,
        },
        "content": [
            {"title": "Archetyp", "text": "Generyczny Bohater Niezależny przeznaczony do szybkiego wykorzystania przez Mistrza Gry."},
            {"title": "Opis", "text": summary},
            {"title": "Profesja", "text": f"Profesja wzorcowa: {career}. Nazwa wpisu określa rolę, a nie imię postaci."},
        ],
        "source": {
            "pdfPages": [page + 1 for page in printed_pages],
            "printedPages": list(printed_pages),
        },
    })
    return record


GENERIC_NPCS = (
    generic_npc(
        "kieszonkowiec", "Kieszonkowiec", (245,),
        "Drobny złodziej działający na gwarnych ulicach i targach; kradnie mieszki, sakiewki oraz niewielką biżuterię, po czym znika w tłumie.",
        (26, 32, 28, 31, 43, 31, 29, 40, 1, 11, 2, 3, 4, 0, 0, 0),
        "hazard|plotkowanie|przekonywanie|przeszukiwanie|sekretne znaki (złodziei)|skradanie się|spostrzegawczość|ukrywanie się|wiedza (Imperium)|wycena|znajomość języka (staroświatowy)|zwinne palce",
        "bystry wzrok|charyzmatyczny|łotrzyk|szybki refleks",
        "lekki pancerz (skórzany kaftan)", "głowa 0, ręce 0, korpus 1, nogi 0",
        "sztylet", "tobołek", "złodziej",
    ),
    generic_npc(
        "kowal", "Kowal", (245,),
        "Powszechnie szanowany rzemieślnik, który podkuwa konie, wyrabia i naprawia prostą broń, a czasem również zbroje.",
        (38, 29, 42, 41, 30, 34, 30, 20, 1, 11, 3, 4, 3, 0, 0, 0),
        "czytanie i pisanie|plotkowanie|powożenie|rzemiosło (kowalstwo)|rzemiosło (płatnerstwo)|sekretny język (gildii)|spostrzegawczość|targowanie|wiedza (krasnoludy)|wycena|znajomość języka (khazalid)|znajomość języka (staroświatowy)",
        "krasnoludzki fach|krzepki|odwaga|widzenie w ciemności|zapiekła nienawiść|żyłka handlowa",
        "lekki pancerz (skórzany kaftan)", "głowa 0, ręce 0, korpus 1, nogi 0",
        "broń jednoręczna (młot)", "narzędzia (rzemieślnika)", "rzemieślnik", "krasnolud",
    ),
    generic_npc(
        "kramarz", "Kramarz", (245,),
        "Handlarz prowadzący kram, sklepik, niewielką oberżę albo tawernę w mieście lub porcie Imperium.",
        (28, 25, 28, 31, 32, 43, 30, 39, 1, 11, 2, 3, 4, 0, 0, 0),
        "czytanie i pisanie|mocna głowa|plotkowanie|powożenie|przeszukiwanie|spostrzegawczość|targowanie|wiedza (Imperium)|wycena|znajomość języka (kislevski)|znajomość języka (staroświatowy) +10",
        "błyskotliwość|charyzmatyczny|czuły słuch|żyłka handlowa",
        "lekki pancerz (skórzany kaftan)", "głowa 0, ręce 0, korpus 1, nogi 0",
        "broń jednoręczna (pałka)", "sklepik lub tawerna", "mieszczanin",
    ),
    generic_npc(
        "najmita", "Najmita", (245, 246),
        "Osiłek albo wojownik do wynajęcia, zwykle mający za sobą służbę w armii lub garnizonie straży miejskiej.",
        (35, 30, 33, 35, 30, 25, 35, 28, 1, 11, 3, 3, 4, 0, 0, 0),
        "hazard|plotkowanie +10|powożenie|sekretny język (bitewny)|spostrzegawczość|unik|wiedza (Imperium)|wiedza (Tilea)|znajomość języka (staroświatowy)|znajomość języka (tileański)",
        "błyskawiczne przeładowanie|opanowanie|rozbrajanie|strzał mierzony|strzelec wyborowy",
        "średni pancerz (skórznia, kaftan kolczy i kolczy czepiec)", "głowa 3, ręce 1, korpus 3, nogi 1",
        "kusza|broń jednoręczna (miecz)|tarcza", "20 bełtów", "najemnik",
    ),
    generic_npc(
        "straznik-miejski", "Strażnik miejski", (246,),
        "Stróż prawa patrolujący ulice miast i portów, chroniący targowiska oraz urzędy i aresztujący przestępców.",
        (31, 31, 33, 41, 30, 38, 28, 30, 1, 12, 3, 4, 4, 0, 0, 0),
        "nauka (prawo)|plotkowanie +10|przeszukiwanie|spostrzegawczość|tropienie|unik|wiedza (Imperium)|zastraszanie|znajomość języka (staroświatowy)",
        "błyskotliwość|niezwykle odporny|ogłuszanie|opanowanie|rozbrajanie|silny cios",
        "lekki pancerz (skórzana kurta)", "głowa 0, ręce 1, korpus 1, nogi 0",
        "broń jednoręczna (pałka)|sztylet", "latarnia na drągu|mundur", "strażnik",
    ),
    generic_npc(
        "szuler", "Szuler", (246,),
        "Zawodowy hazardzista, który zna gry karciane i kościane oraz potrafi oszukiwać za pomocą znaczonych kart i podrabianych kości.",
        (25, 31, 28, 32, 38, 42, 30, 38, 1, 12, 2, 3, 5, 0, 0, 0),
        "gadanina|hazard|kuglarstwo (aktorstwo)|plotkowanie +10|przekonywanie|sekretny język (złodziejski)|spostrzegawczość|wiedza (Imperium)|wycena|znajomość języka (staroświatowy) +10",
        "bardzo szybki|geniusz arytmetyczny|łotrzyk|przemawianie|szósty zmysł",
        "lekki pancerz (skórzany kaftan)", "głowa 0, ręce 0, korpus 1, nogi 0",
        "sztylet", "talia kart|para kości do gry", "kanciarz",
    ),
    generic_npc(
        "pirat", "Pirat", (246,),
        "Morski albo rzeczny rozbójnik napadający przede wszystkim na samotne statki handlowe, łodzie przewoźników i flisaków.",
        (33, 32, 40, 35, 29, 25, 30, 28, 1, 11, 4, 3, 4, 0, 0, 0),
        "mocna głowa|plotkowanie|pływanie|spostrzegawczość|unik|wiedza (Imperium)|wiedza (Jałowa Kraina)|wioślarstwo|wspinaczka|znajomość języka (bretoński)|znajomość języka (staroświatowy)|żeglarstwo",
        "bijatyka|niezwykle odporny|obieżyświat|silny cios|urodzony wojownik",
        "lekki pancerz (skórzana kurta)", "głowa 0, ręce 1, korpus 1, nogi 0",
        "broń jednoręczna (kordelas)|łuk", "20 strzał", "żeglarz",
    ),
    generic_npc(
        "zawadiaka", "Zawadiaka", (247,),
        "Młody szlachcic prowadzący z nudów lub dla przyjemności hulaszcze życie i często gnębiący ludzi niższego stanu.",
        (36, 27, 31, 30, 43, 29, 30, 35, 1, 11, 3, 3, 4, 0, 0, 0),
        "czytanie i pisanie|gadanina|hazard|jeździectwo|mocna głowa|plotkowanie|przekonywanie|wiedza (Imperium) +10|znajomość języka (staroświatowy) +10",
        "broń specjalna (parująca)|broń specjalna (szermiercza)|charyzmatyczny|etykieta|przemawianie|szybki refleks",
        "lekki pancerz (skórzana kurta i skórzane nogawice)", "głowa 0, ręce 1, korpus 1, nogi 1",
        "rapier|lewak", "strój szlachecki|sakiewka z 3k10 zk", "szlachcic",
    ),
    generic_npc(
        "zbir", "Zbir", (247,),
        "Groźny przestępca trudniący się rozbojem, napadami i kradzieżą, czasem wynajmowany do pobicia albo zamordowania wskazanej osoby.",
        (33, 26, 43, 31, 32, 25, 36, 30, 1, 12, 4, 3, 4, 0, 0, 0),
        "hazard|mocna głowa|plotkowanie|sekretny język (złodziejski)|unik|wiedza (Imperium)|zastraszanie|znajomość języka (staroświatowy)",
        "bardzo silny|odporność na trucizny|ogłuszanie|rozbrajanie|szybki refleks|szybkie wyciągnięcie|zapasy",
        "lekki pancerz (skórzana kurta)", "głowa 0, ręce 1, korpus 1, nogi 0",
        "broń jednoręczna (pałka)|kastet", "płaszcz z kapturem", "oprych",
    ),
    generic_npc(
        "zboj", "Zbój", (247,),
        "Wygnaniec albo zbiegły przestępca żyjący poza miastami i napadający na samotnych podróżnych oraz słabo chronione karawany.",
        (29, 42, 30, 31, 35, 30, 28, 25, 1, 12, 3, 3, 4, 0, 0, 0),
        "opieka nad zwierzętami|plotkowanie|powożenie|skradanie się|spostrzegawczość|ukrywanie się|unik|wiedza (Imperium)|wspinaczka|zastawianie pułapek|znajomość języka (staroświatowy)",
        "strzał mierzony|szybki refleks|wędrowiec",
        "lekki pancerz (skórzana kurta i skórzany czepiec)", "głowa 1, ręce 1, korpus 1, nogi 0",
        "łuk|broń jednoręczna (miecz)|tarcza", "20 strzał", "banita",
    ),
    generic_npc(
        "zebrak", "Żebrak", (247,),
        "Ubogi mieszkaniec miasta żyjący z jałmużny, czasem zbierający informacje dla miejskich gangów albo gildii złodziei.",
        (25, 25, 28, 37, 31, 29, 37, 33, 1, 11, 2, 3, 4, 0, 0, 0),
        "kuglarstwo (śpiew)|oswajanie|plotkowanie|pływanie|powożenie|przekonywanie|skradanie się|sztuka przetrwania|ukrywanie się|wiedza (Imperium)|wioślarstwo|znajomość języka (staroświatowy)",
        "chodu!|czuły słuch|odporność na choroby|twardziel",
        "brak", "głowa 0, ręce 0, korpus 0, nogi 0",
        "broń jednoręczna (kula)", "miseczka żebracza|łachmany", "chłop",
    ),
)


def scenario_npc(
    slug: str,
    given_name: str,
    family_name: str | None,
    printed_page: int,
    summary: str,
    values: tuple[Any, ...],
    skills: str,
    talents: str,
    armour: str,
    armour_points: str,
    weapons: str,
    equipment: str,
    career: str,
    previous_careers: str,
    honorific: str = "",
) -> dict[str, Any]:
    title = " ".join(part for part in (honorific, given_name, family_name or "") if part)
    record = profile(
        slug, title, printed_page, "Bohaterowie Niezależni", summary, values,
        skills, talents, armour=armour, armour_points=armour_points,
        weapons=weapons, equipment=equipment, career=career, race="człowiek",
        disposition="neutral",
    )
    record.update({
        "id": f"corebook:scenario:npc:{slug}",
        "type": "person",
        "entryType": "creature",
        "scope": "scenario",
        "kind": "named_npc",
        "npcKind": "named",
        "category": "Scenariusz: Bohaterowie niezależni",
        "identity": {
            "givenName": given_name,
            "familyName": family_name or None,
            "honorific": honorific or None,
            "displayName": title,
            "sourceSuppliedName": True,
        },
        "content": [
            {"title": "Rola w scenariuszu", "text": summary},
            {"title": "Profesje", "text": f"Obecna: {career}. Poprzednie: {previous_careers or 'brak podanych'}."},
        ],
    })
    record["profile"]["previousCareers"] = split_items(previous_careers)
    return record


SCENARIO_NPCS = (
    scenario_npc(
        "gerhard-schiller", "Gerhard", "Schiller", 249,
        "Kapitan straży Untergardu, doświadczony żołnierz wybrany przez mieszkańców na przywódcę miasta; surowy, sprawiedliwy i oddany obronie ocalałych.",
        (50, 46, 43, 44, 40, 42, 45, 50, 2, 15, 4, 4, 4, 0, 4, 0),
        "czytanie i pisanie|dowodzenie +10|jeździectwo|nauka (prawo)|plotkowanie +10|przeszukiwanie|sekretny język (bitewny)|spostrzegawczość +10|tropienie|unik|wiedza (Imperium)|wiedza (Jałowa Kraina)|zastraszanie|znajomość języka (staroświatowy)|znajomość języka (tileański)",
        "bijatyka|błyskotliwość|groźny|niezwykle odporny|ogłuszanie|opanowanie|rozbrajanie|silny cios",
        "średni pancerz (zbroja kolcza)", "głowa 3, ręce 3, korpus 3, nogi 3",
        "broń jednoręczna (miecz)|sztylet|tarcza", "mundur", "oficer", "strażnik|sierżant",
    ),
    scenario_npc(
        "babunia-moescher", "Babunia", "Moescher", 249,
        "Najstarsza mieszkanka Untergardu, uzdrowicielka i opiekunka sierot, która ukrywa wyszkolenie w magii zwierząt oraz długo pielęgnowane pragnienie zemsty.",
        (28, 30, 30, 31, 35, 56, 53, 45, 1, 13, 3, 3, 4, 2, 5, 0),
        "czytanie i pisanie|leczenie|nauka (magia) +10|nauka (teologia)|opieka nad zwierzętami|oswajanie +10|plotkowanie +10|przekonywanie|przeszukiwanie|sekretny język (magiczny)|splatanie magii +10|spostrzegawczość|wiedza (Imperium)|wiedza (Jałowa Kraina)|wykrywanie magii|znajomość języka (klasyczny)|znajomość języka (staroświatowy)",
        "błyskotliwość|gusła|magia prosta (gusła)|magia prosta (tajemna)|magia tajemna (zwierzęta)|widzenie w ciemności|odporność psychiczna|zmysł magii",
        "brak", "głowa 0, ręce 0, korpus 0, nogi 0", "kij",
        "plecak|księga wiedzy tajemnej|sakiewka z 30 zk|przybory do pisania",
        "wędrowna czarodziejka", "guślarka|uczennica czarodzieja",
    ),
    scenario_npc(
        "hans-baumer", "Hans", "Baumer", 250,
        "Drwal, łowca i zwiadowca z okolic Untergardu, który patroluje las, zdobywa żywność i ostrzega mieszkańców przed ruchami zwierzoludzi.",
        (42, 25, 41, 34, 35, 33, 38, 25, 1, 14, 4, 3, 5, 0, 3, 0),
        "plotkowanie|sekretne znaki (zwiadowców)|sekretny język (zwiadowców)|skradanie się|tropienie|ukrywanie się|wspinaczka|znajomość języka (staroświatowy)",
        "bardzo szybki|broń specjalna (broń dwuręczna)|czuły słuch|odporność na choroby|wędrowiec",
        "lekki pancerz (skórzana kurta)", "głowa 0, ręce 1, korpus 1, nogi 0",
        "broń dwuręczna (dwuręczny topór)|łuk", "plecak|10 strzał", "leśnik", "",
    ),
    scenario_npc(
        "ojciec-dietrich", "Dietrich", None, 250,
        "Kapłan Sigmara przybyły z Altdorfu, duchowy doradca kapitana Schillera i opiekun wiernych po śmierci miejscowego kapłana.",
        (37, 27, 41, 37, 24, 30, 48, 43, 1, 13, 4, 3, 4, 0, 3, 0),
        "czytanie i pisanie|leczenie|nauka (historia)|nauka (teologia) +10|przekonywanie|spostrzegawczość|znajomość języka (klasyczny)|znajomość języka (staroświatowy)",
        "bardzo silny|charyzmatyczny|morderczy atak|odporność na magię|przemawianie|urodzony wojownik",
        "średni pancerz (skórznia, kaftan kolczy, hełm)",
        "głowa 3, ręce 1, korpus 3, nogi 1",
        "broń jednoręczna (młot bojowy)",
        "modlitewnik|symbol Sigmara|relikwia|tobołek|przybory do pisania",
        "kapłan", "akolita", honorific="Ojciec",
    ),
)


def checksum(record: dict[str, Any]) -> str:
    payload = json.dumps(record, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()

def extract(pdf_path: Path) -> dict[str, Any]:
    document = pdfium.PdfDocument(str(pdf_path))
    if len(document) != EXPECTED_PAGES:
        raise RuntimeError(f"Expected {EXPECTED_PAGES} PDF pages, found {len(document)}")
    document.close()

    records: list[dict[str, Any]] = []
    for item in (*CURATED_TOPICS, *CREATURE_BESTIARY, *GENERIC_NPCS, *SCENARIO_NPCS):
        record = dict(item)
        record["checksum"] = checksum(record)
        records.append(record)

    pdf_sha256 = hashlib.sha256(pdf_path.read_bytes()).hexdigest()
    return {
        "schemaVersion": "1.0.0",
        "source": {
            "key": "wfrp2-corebook-pl-reconstruction-269",
            "name": "Warhammer Fantasy Roleplay 2 ed. — wybrane hasła z Podręcznika Głównego",
            "language": "pl",
            "edition": "WFRP2",
            "pdfFilename": pdf_path.name,
            "pdfSha256": pdf_sha256,
            "pageCount": EXPECTED_PAGES,
            "licenseStatus": "copyrighted_user_supplied",
            "attributionStatus": "credits_preserved_in_front_matter",
        },
        "records": records,
        "counts": {
            "records": len(records),
            "rulesEntries": sum(item["scope"] == "rules" for item in records),
            "worldEntries": sum(item["scope"] == "world" for item in records),
            "scenarioEntries": sum(item["scope"] == "scenario" for item in records),
            "bestiaryEntries": sum(item["bestiary"] for item in records),
            "creaturesAndAnimals": len(CREATURE_BESTIARY),
            "genericNpcProfiles": len(GENERIC_NPCS),
            "namedNpcProfiles": len(SCENARIO_NPCS),
        },
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("pdf", nargs="?", type=Path, default=DEFAULT_PDF)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()

    pdf_path = args.pdf.resolve()
    if not pdf_path.is_file():
        raise SystemExit(f"PDF not found: {pdf_path}")
    corpus = extract(pdf_path)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(corpus, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    print(json.dumps(corpus["counts"], ensure_ascii=False))
    print(args.output.resolve())


if __name__ == "__main__":
    main()
