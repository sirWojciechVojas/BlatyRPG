# Voice i Jukebox

Moduł rozdziela trzy niezależne tory:

- istniejący serwer Node `ws` utrzymuje autorytatywny stan i zegar Jukeboxa;
- LiveKit przenosi wyłącznie rozmowy i jawnie wybrane zewnętrzne wejścia audio;
- Web Audio API odtwarza i miksuje na każdym kliencie pliki biblioteki, głosy oraz wejścia.

Audio rozmów ani pliki muzyczne nie są przesyłane przez Node WebSocket. Muzyka dostępna przez API kampanii jest pobierana z autoryzacją JWT do lokalnego Blob URL. YouTube używa oficjalnego IFrame Player API; backend zapisuje tylko metadane i identyfikator filmu.

## Konfiguracja

Wymagane wartości znajdują się wyłącznie w backendzie i środowisku procesu LiveKit:

```dotenv
LIVEKIT_URL=wss://localhost:8443/livekit
LIVEKIT_API_KEY=local_api_key
LIVEKIT_API_SECRET=replace_with_at_least_16_random_characters
LIVEKIT_TOKEN_TTL=300
LIVEKIT_NODE_IP=127.0.0.1
AUDIO_UPLOAD_MAX_BYTES=52428800
```

`LIVEKIT_TOKEN_TTL` przyjmuje 30–900 sekund. Token aplikacji nigdy nie zawiera klucza ani sekretu LiveKit. Pokój ma nazwę `campaign_{id}`, a tożsamość uczestnika `user_{id}`; obie wartości wylicza backend po ponownej weryfikacji sesji i aktywnego członkostwa.

`LIVEKIT_URL` może wskazywać LiveKit Cloud albo własny serwer. Dla Cloud należy wkleić URL `wss://...livekit.cloud` oraz parę klucz/sekret z ustawień projektu. Nie trzeba wtedy uruchamiać profilu Docker LiveKit.

## Self-hosted LiveKit

Profil do lokalnego developmentu uruchamia SFU LiveKit 1.13.6:

```bash
docker compose --profile self-hosted-livekit up -d livekit
docker compose up -d php frontend websocket nginx
```

Nginx wystawia szyfrowany signaling pod `wss://localhost:8443/livekit`; TCP 7881 i UDP 7882 są wystawione bezpośrednio dla WebRTC. Jeżeli łączą się inne urządzenia, `LIVEKIT_NODE_IP` musi być osiągalnym adresem hosta, a firewall musi przepuszczać porty 7881/TCP i 7882/UDP.

Profil `--dev` jest przeznaczony wyłącznie do pracy lokalnej. Produkcyjny self-host powinien używać domeny z publicznie zaufanym certyfikatem, konfiguracji wygenerowanej oficjalnym generatorem LiveKit, publicznego zakresu UDP oraz TURN/TLS. Klucze pozostają w menedżerze sekretów lub zmiennych środowiskowych.

## Baza danych

Migracja `2026-09-13-100000_CreateVoiceAndJukebox.php` dodaje:

- `audio_libraries` — biblioteki systemowe/settingowe i osobiste biblioteki MG;
- `audio_tracks` — metadane uploadów, systemowych assetów i providerów;
- `campaign_audio_tracks` — jawne przypisanie utworu do kampanii;
- `campaign_jukebox_settings` — ostatni stan sześciu kanałów, ustawienia duckingu i rewizja.

Migracja `2026-09-13-120000_CreateJukeboxPlaylistsAndQueues.php` dodaje:

- `audio_playlists` — prywatne playlisty danego konta MG;
- `audio_playlist_items` — uporządkowaną sekwencję utworów playlisty;
- `campaign_jukebox_queue_items` — niezależną, trwałą kolejkę każdego kanału kampanii.

Uruchomienie:

```bash
docker compose exec php php spark migrate
```

Pliki trafiają poza katalog publiczny do wolumenu `audio_storage`. Endpoint odtwarzania ponownie sprawdza dostęp do kampanii i obsługuje żądania byte-range. Upload dopuszcza wyłącznie zweryfikowane pary MIME/rozszerzenie: MP3, WAV, OGG, WebM audio, M4A/MP4 audio, AAC i FLAC. Limit aplikacji, PHP oraz Nginx jest spójny z 50 MiB.

Biblioteka systemowa jest wybierana po `audio_libraries.system_id` i `setting_id`; wartość `NULL` oznacza pozycję wspólną. MG widzi pasujące utwory automatycznie, lecz nie może modyfikować globalnej biblioteki z panelu kampanii.

Administrator zarządza globalnym katalogiem w zakładce „Biblioteka audio”
panelu administratora. Tworzy tam bibliotekę dla istniejącej pary
system/setting, może ją włączyć lub wyłączyć, zmienić przypisanie oraz dodawać,
edytować i usuwać pliki lub dozwolone linki YouTube. Wyłączenie biblioteki
natychmiast usuwa ją z katalogu dostępnego przy Stołach.

Każdy MG ma jedną prywatną bibliotekę powiązaną z jego kontem, a nie z
pojedynczą kampanią. Utwór można przypinać do wielu Stołów tego MG. Dopiero
jawne przypięcie prywatnego utworu daje członkom danej kampanii dostęp do jego
pliku; trwałe usunięcie z biblioteki MG usuwa również wszystkie takie
przypięcia.

## Synchronizacja

Node `ws` obsługuje komunikaty `JUKEBOX_LOAD`, `JUKEBOX_PLAY`, `JUKEBOX_PAUSE`, `JUKEBOX_STOP`, `JUKEBOX_SEEK`, `JUKEBOX_VOLUME`, `JUKEBOX_MUTE`, `JUKEBOX_LOOP`, `JUKEBOX_FADE_IN`, `JUKEBOX_FADE_OUT`, `JUKEBOX_DEVICE`, `JUKEBOX_STATE` i `JUKEBOX_SYNC`.

Klient nie przesyła `campaignId`, `userId` ani roli. Serwer bierze je z istniejącego biletu realtime i odrzuca komendy sterujące gracza. Każda zmiana jest zapisywana w backendzie przed broadcastem. `executeAt` jest normalizowane do kontrolowanego okna czasu serwera; nie istnieje tryb „play now”.

Klient wyznacza offset zegara z połowy RTT, preloaduje źródło i uruchamia timer względem `executeAt`. Drift poniżej 80 ms jest ignorowany, od 80 do 750 ms korygowany chwilową zmianą `playbackRate`, a od 750 ms przez seek. `JUKEBOX_STATE` i snapshot po reconnect zawierają `startedAt`, więc klient dołączający w trakcie utworu oblicza aktualną pozycję, łącznie z pętlą.

## Urządzenia i prywatny miks

W panelu Stołu Jukebox i rozmowa głosowa są dwiema niezależnymi pozycjami
prawego paska. Pojedynczy klik otwiera wybrany moduł w sidebarze, a dwuklik
otwiera go we własnym pływającym oknie. Wybór mikrofonu, wyjścia audio oraz
dwóch niezależnych wejść MG znajduje się w pozycji „Ustawienia” tego samego
paska. Zamknięcie drawera lub okna nie zatrzymuje aktywnego Jukeboxa ani
rozmowy.

Po udzieleniu zgody przeglądarka pokazuje urządzenia `audioinput` i `audiooutput`. Mikrofon używa echo cancellation, noise suppression oraz automatic gain control. Dwa zapisane źródła zewnętrzne MG wyłączają te filtry i po uruchomieniu są publikowane jako osobny track `jukebox:{channelId}` wybranego kanału; nie są automatycznie łączone z mikrofonem.

Panel Szafy grającej używa wspólnej szerokości pozostałych modułów stołu i ma trzy zwarte zakładki: „Odtwarzanie”, „Biblioteka” oraz „Playlisty”. Przełączenie kanału na urządzenie zatrzymuje jego lokalny utwór, ale nie usuwa trwałej kolejki. Utrata urządzenia jest pokazywana jako utrata sygnału, a ponowne podłączenie odtwarza wyłącznie ten kanał.

Głosy zdalne przechodzą lokalnie przez `MediaStreamAudioSourceNode -> GainNode -> StereoPannerNode -> destination`. Głośność i panorama uczestnika są prywatne i zapisywane w `localStorage`. Każde z sześciu źródeł Jukeboxa ma własny lokalny regulator bezpośrednio w karcie kanału. Ducking obniża Music o 6 dB i Ambient o 4 dB podczas aktywnej mowy, bez zmiany kodeka ani jakości LiveKit.

Wybór wyjścia pojawia się tylko w przeglądarkach udostępniających `AudioContext.setSinkId()`. Po utracie urządzenia `devicechange` odświeża listę, mikrofon wraca na urządzenie domyślne i jest publikowany ponownie bez opuszczania pokoju. LiveKit realizuje reconnect transportu, a aplikacja wykonuje pełne ponowne wejście z nowym krótkotrwałym tokenem po terminalnym rozłączeniu.

## Kontrole

```bash
docker compose exec php composer test
docker compose exec websocket npm test
docker compose exec frontend npm test
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
```
