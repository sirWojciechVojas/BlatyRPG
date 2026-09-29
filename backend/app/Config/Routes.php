<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ========================================
// BASE ROUTER CONFIG
// ========================================
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

// ========================================
// ROOT
// API-only app entrypoint
// ========================================
$routes->get('/', 'Api\StatusController::app');

// ========================================
// API
// Everything under /api
// ========================================
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes) {
    // ----------------------------------------
    // STATUS / HEALTH
    // ----------------------------------------
    $routes->get('/', 'StatusController::index');
    $routes->get('health', 'StatusController::health');
    $routes->get('public/subscription-plans', 'SubscriptionPlanController::index');
    // Public assets need no session; the controller still enforces campaign/private access.
    $routes->get('media/(:num)', 'MediaController::show/$1');
    $routes->post('internal/realtime/campaigns/(:num)/chat/sync', 'InternalRealtimeChatController::sync/$1');
    $routes->post('internal/realtime/campaigns/(:num)/chat/send', 'InternalRealtimeChatController::send/$1');
    $routes->post('internal/realtime/campaigns/(:num)/handouts/delivery', 'InternalRealtimeHandoutController::delivery/$1');
    $routes->post('internal/realtime/campaigns/(:num)/tokens/move', 'InternalRealtimeTokenController::move/$1');
    $routes->post('internal/realtime/campaigns/(:num)/tokens/move-group', 'InternalRealtimeTokenController::moveGroup/$1');
    $routes->post('internal/realtime/campaigns/(:num)/tokens/change', 'InternalRealtimeTokenController::change/$1');
    $routes->post('internal/realtime/campaigns/(:num)/token-sync/command', 'InternalRealtimeTokenSyncController::command/$1');
    $routes->post('internal/realtime/campaigns/(:num)/tokens/movement-requests', 'InternalRealtimeTokenController::requestMovement/$1');
    $routes->post('internal/realtime/campaigns/(:num)/tokens/movement-requests/(:num)/resolve', 'InternalRealtimeTokenController::resolveMovement/$1/$2');
    $routes->post('internal/realtime/campaigns/(:num)/scenes/(:num)/snapshot', 'InternalRealtimeSceneSnapshotController::show/$1/$2');
    $routes->post('internal/realtime/campaigns/(:num)/combat/commands', 'InternalRealtimeCombatController::command/$1');
    $routes->post('internal/realtime/campaigns/(:num)/walls/change', 'InternalRealtimeWallController::change/$1');
    $routes->post('internal/realtime/campaigns/(:num)/walls/audio-state', 'InternalRealtimeWallAudioController::state/$1');
    $routes->post('internal/realtime/campaigns/(:num)/walls/audio-cue', 'InternalRealtimeWallAudioController::cue/$1');
    $routes->post('internal/realtime/campaigns/(:num)/lights/change', 'InternalRealtimeLightController::change/$1');
    $routes->post('internal/realtime/campaigns/(:num)/regions/change', 'InternalRealtimeRegionController::change/$1');
    $routes->post('internal/realtime/campaigns/(:num)/tiles/change', 'InternalRealtimeTileController::change/$1');
    $routes->post('internal/realtime/campaigns/(:num)/jukebox/state', 'InternalRealtimeJukeboxController::state/$1');
    $routes->post('internal/realtime/campaigns/(:num)/jukebox/save', 'InternalRealtimeJukeboxController::save/$1');
    $routes->post('internal/realtime/campaigns/(:num)/sound-effects/state', 'InternalRealtimeSoundEffectController::state/$1');
    $routes->post('internal/realtime/campaigns/(:num)/sound-effects/command', 'InternalRealtimeSoundEffectController::command/$1');

    // ----------------------------------------
    // AUTH (PUBLIC)
    // Canonical: /api/auth/*
    // Legacy aliases: /api/login, /api/register
    // ----------------------------------------
    $routes->group('auth', static function (RouteCollection $routes) {
        $routes->post('login', 'AuthController::login');
        $routes->post('register', 'AuthController::register');
        $routes->post('password-reset/request', 'AuthController::requestPasswordReset');
        $routes->post('password-reset/confirm', 'AuthController::resetPassword');
        $routes->get('oauth/providers', 'OAuthController::providers');
        $routes->post('oauth/(:segment)/start', 'OAuthController::start/$1');
        $routes->get('oauth/(:segment)/callback', 'OAuthController::callback/$1');
        $routes->post('oauth/exchange', 'OAuthController::exchange');
    });

    // $routes->post('login', 'AuthController::login');
    // $routes->post('register', 'AuthController::register');

    // ----------------------------------------
    // PRIVATE ENDPOINTS
    // ----------------------------------------
    $routes->group('', ['filter' => 'auth', 'namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes) {
        // AUTH (PRIVATE)
        // Canonical: /api/auth/me
        // Legacy alias: /api/me
        $routes->get('auth/me', 'AuthSessionController::me');
        $routes->post('auth/logout', 'AuthSessionController::logout');
        $routes->patch('auth/profile', 'AuthSessionController::updateProfile');
        $routes->get('auth/audio-device-settings', 'AuthSessionController::audioDeviceSettings');
        $routes->put('auth/audio-device-settings', 'AuthSessionController::updateAudioDeviceSettings');
        $routes->post('auth/change-password', 'AuthSessionController::changePassword');
        $routes->get('auth/sessions', 'AuthSessionController::sessions');
        $routes->delete('auth/sessions/(:num)', 'AuthSessionController::revokeSession/$1');
        $routes->post('auth/sessions/revoke-others', 'AuthSessionController::revokeOtherSessions');
        $routes->get('auth/oauth/identities', 'OAuthController::identities');
        $routes->post('auth/oauth/(:segment)/link', 'OAuthController::link/$1');
        $routes->get('me', 'AuthSessionController::me');

        // Central media registry and provider-neutral direct uploads.
        $routes->post('media/uploads', 'MediaController::createUpload');
        $routes->post('media/uploads/(:num)/complete', 'MediaController::completeUpload/$1');

        // ----------------------------------------
        // CAMPAIGNS
        // ----------------------------------------
        $routes->get('campaigns', 'CampaignController::index');
        $routes->post('campaigns', 'CampaignController::create');
        $routes->get('campaigns/(:num)/settings', 'CampaignSettingsController::show/$1');
        $routes->patch('campaigns/(:num)/settings', 'CampaignSettingsController::update/$1');
        $routes->post('campaigns/(:num)/enter', 'CampaignSettingsController::enter/$1');
        $routes->get('campaigns/(:num)/members', 'CampaignMemberController::index/$1');
        $routes->patch('campaigns/(:num)/members/(:num)', 'CampaignMemberController::update/$1/$2');
        $routes->delete('campaigns/(:num)/members/(:num)', 'CampaignMemberController::delete/$1/$2');
        $routes->get('campaigns/(:num)/invitations', 'CampaignInvitationController::index/$1');
        $routes->post('campaigns/(:num)/invitations', 'CampaignInvitationController::create/$1');
        $routes->delete('campaigns/(:num)/invitations/(:num)', 'CampaignInvitationController::revoke/$1/$2');
        $routes->get('campaign-invitations', 'CampaignInvitationController::mine');
        $routes->post('campaign-invitations/(:num)/accept', 'CampaignInvitationController::accept/$1');
        $routes->post('campaign-invitations/(:num)/reject', 'CampaignInvitationController::reject/$1');
        $routes->post('campaigns/(:num)/realtime-ticket', 'RealtimeTicketController::create/$1');
        $routes->post('campaigns/(:num)/voice/token', 'LiveKitController::token/$1');
        $routes->get('campaigns/(:num)/audio/tracks', 'CampaignAudioController::index/$1');
        $routes->post('campaigns/(:num)/audio/tracks/upload', 'CampaignAudioController::upload/$1');
        $routes->post('campaigns/(:num)/audio/tracks/external', 'CampaignAudioController::external/$1');
        $routes->put('campaigns/(:num)/audio/tracks/(:num)', 'CampaignAudioController::attach/$1/$2');
        $routes->delete('campaigns/(:num)/audio/tracks/(:num)', 'CampaignAudioController::remove/$1/$2');
        $routes->patch('campaigns/(:num)/audio/library/tracks/(:num)', 'CampaignAudioController::updatePersonal/$1/$2');
        $routes->delete('campaigns/(:num)/audio/library/tracks/(:num)', 'CampaignAudioController::deletePersonal/$1/$2');
        $routes->post('campaigns/(:num)/audio/playlists', 'CampaignAudioController::createPlaylist/$1');
        $routes->patch('campaigns/(:num)/audio/playlists/(:num)', 'CampaignAudioController::renamePlaylist/$1/$2');
        $routes->delete('campaigns/(:num)/audio/playlists/(:num)', 'CampaignAudioController::deletePlaylist/$1/$2');
        $routes->post('campaigns/(:num)/audio/playlists/(:num)/items', 'CampaignAudioController::addPlaylistItem/$1/$2');
        $routes->patch('campaigns/(:num)/audio/playlists/(:num)/items/(:num)', 'CampaignAudioController::movePlaylistItem/$1/$2/$3');
        $routes->delete('campaigns/(:num)/audio/playlists/(:num)/items/(:num)', 'CampaignAudioController::removePlaylistItem/$1/$2/$3');
        $routes->post('campaigns/(:num)/audio/queues/(:segment)/tracks', 'CampaignAudioController::addQueueTrack/$1/$2');
        $routes->post('campaigns/(:num)/audio/queues/(:segment)/playlists/(:num)', 'CampaignAudioController::addQueuePlaylist/$1/$2/$3');
        $routes->post('campaigns/(:num)/audio/queues/(:segment)/playlists/(:num)/start', 'CampaignAudioController::startPlaylist/$1/$2/$3');
        $routes->patch('campaigns/(:num)/audio/queues/(:segment)/items/(:num)', 'CampaignAudioController::moveQueueItem/$1/$2/$3');
        $routes->delete('campaigns/(:num)/audio/queues/(:segment)/items/(:num)', 'CampaignAudioController::removeQueueItem/$1/$2/$3');
        $routes->delete('campaigns/(:num)/audio/queues/(:segment)', 'CampaignAudioController::clearQueue/$1/$2');
        $routes->get('campaigns/(:num)/audio/tracks/(:num)/file', 'CampaignAudioController::file/$1/$2');
        $routes->get('campaigns/(:num)/jukebox', 'CampaignAudioController::jukeboxState/$1');
        $routes->get('campaigns/(:num)/sound-effects', 'CampaignSoundEffectController::index/$1');
        $routes->post('campaigns/(:num)/sound-effects/screens', 'CampaignSoundEffectController::createScreen/$1');
        $routes->patch('campaigns/(:num)/sound-effects/screens/(:num)', 'CampaignSoundEffectController::updateScreen/$1/$2');
        $routes->post('campaigns/(:num)/sound-effects/screens/(:num)/duplicate', 'CampaignSoundEffectController::duplicateScreen/$1/$2');
        $routes->delete('campaigns/(:num)/sound-effects/screens/(:num)', 'CampaignSoundEffectController::deleteScreen/$1/$2');
        $routes->put('campaigns/(:num)/sound-effects/screens/(:num)/slots/(:num)', 'CampaignSoundEffectController::saveSlot/$1/$2/$3');
        $routes->delete('campaigns/(:num)/sound-effects/screens/(:num)/slots/(:num)', 'CampaignSoundEffectController::deleteSlot/$1/$2/$3');
        $routes->get('campaigns/(:num)/token-movement-requests', 'TokenMovementRequestController::index/$1');
        $routes->get('campaigns/(:num)/chat/messages', 'CampaignChatController::index/$1');
        $routes->post('campaigns/(:num)/chat/messages', 'CampaignChatController::create/$1');
        $routes->get('campaigns/(:num)/calendar', 'CampaignCalendarController::show/$1');
        $routes->put('campaigns/(:num)/calendar/state', 'CampaignCalendarController::setState/$1');
        $routes->post('campaigns/(:num)/calendar/advance', 'CampaignCalendarController::advance/$1');
        $routes->get('campaigns/(:num)/calendar/events', 'CampaignCalendarController::events/$1');
        $routes->post('campaigns/(:num)/calendar/events', 'CampaignCalendarController::createEvent/$1');
        $routes->patch('campaigns/(:num)/calendar/events/(:num)', 'CampaignCalendarController::updateEvent/$1/$2');
        $routes->delete('campaigns/(:num)/calendar/events/(:num)', 'CampaignCalendarController::deleteEvent/$1/$2');
        $routes->put('campaigns/(:num)/calendar/morrslieb', 'CampaignCalendarController::setMorrslieb/$1');

        // ----------------------------------------
        // HANDOUT LIBRARY AND CAMPAIGN HANDOUTS
        // ----------------------------------------
        $routes->get('handout-library', 'HandoutLibraryController::index');
        $routes->post('handout-library/folders', 'HandoutLibraryController::createFolder');
        $routes->patch('handout-library/folders/(:num)', 'HandoutLibraryController::updateFolder/$1');
        $routes->delete('handout-library/folders/(:num)', 'HandoutLibraryController::deleteFolder/$1');
        $routes->post('handout-library/tags', 'HandoutLibraryController::createTag');
        $routes->delete('handout-library/tags/(:num)', 'HandoutLibraryController::deleteTag/$1');
        $routes->post('handout-library/assets', 'HandoutLibraryController::uploadAsset');
        $routes->post('handout-library/entries', 'HandoutLibraryController::createEntry');
        $routes->get('handout-library/entries/(:num)', 'HandoutLibraryController::show/$1');
        $routes->patch('handout-library/entries/(:num)', 'HandoutLibraryController::updateEntry/$1');
        $routes->delete('handout-library/entries/(:num)', 'HandoutLibraryController::deleteEntry/$1');
        $routes->post('handout-library/entries/(:num)/restore', 'HandoutLibraryController::restoreEntry/$1');
        $routes->get('handout-assets/(:num)/file', 'HandoutLibraryController::assetFile/$1');
        $routes->get('campaigns/(:num)/handouts', 'CampaignHandoutController::index/$1');
        $routes->post('campaigns/(:num)/handouts/publish', 'CampaignHandoutController::publish/$1');
        $routes->get('campaigns/(:num)/handouts/(:num)', 'CampaignHandoutController::show/$1/$2');
        $routes->patch('campaigns/(:num)/handouts/(:num)', 'CampaignHandoutController::update/$1/$2');
        $routes->delete('campaigns/(:num)/handouts/(:num)', 'CampaignHandoutController::delete/$1/$2');
        $routes->post('campaigns/(:num)/handouts/(:num)/restore', 'CampaignHandoutController::restore/$1/$2');
        $routes->post('campaigns/(:num)/handouts/(:num)/share', 'CampaignHandoutController::share/$1/$2');
        $routes->post('campaigns/(:num)/handouts/(:num)/transfer-author', 'CampaignHandoutController::transferAuthor/$1/$2');
        $routes->get('campaigns/(:num)/handout-notifications', 'CampaignHandoutController::notifications/$1');
        $routes->patch('campaigns/(:num)/handout-notifications/(:num)', 'CampaignHandoutController::readNotification/$1/$2');

        // ----------------------------------------
        // PLAYER CHARACTER JOURNAL
        // ----------------------------------------
        $routes->get('campaigns/(:num)/journal', 'HeroJournalController::index/$1');
        $routes->post('campaigns/(:num)/journal', 'HeroJournalController::create/$1');
        $routes->get('campaigns/(:num)/journal/(:num)', 'HeroJournalController::show/$1/$2');
        $routes->patch('campaigns/(:num)/journal/(:num)', 'HeroJournalController::update/$1/$2');
        $routes->delete('campaigns/(:num)/journal/(:num)', 'HeroJournalController::delete/$1/$2');
        $routes->patch('campaigns/(:num)/journal/(:num)/archive', 'HeroJournalController::archive/$1/$2');
        $routes->post('campaigns/(:num)/journal/(:num)/checklist', 'HeroJournalController::addChecklistItem/$1/$2');
        $routes->patch('campaigns/(:num)/journal/(:num)/checklist/(:num)', 'HeroJournalController::updateChecklistItem/$1/$2/$3');
        $routes->delete('campaigns/(:num)/journal/(:num)/checklist/(:num)', 'HeroJournalController::deleteChecklistItem/$1/$2/$3');
        $routes->post('campaigns/(:num)/journal/(:num)/relations', 'HeroJournalController::addRelation/$1/$2');
        $routes->delete('campaigns/(:num)/journal/(:num)/relations/(:num)', 'HeroJournalController::deleteRelation/$1/$2/$3');
        $routes->get('campaigns/(:num)/characters/(:num)/bestiary', 'CharacterBestiaryController::index/$1/$2');
        $routes->get('campaigns/(:num)/characters/(:num)/bestiary/(:num)', 'CharacterBestiaryController::show/$1/$2/$3');
        $routes->get('campaigns/(:num)/bestiary/entries/(:num)/assignments', 'CharacterBestiaryController::assignments/$1/$2');
        $routes->put('campaigns/(:num)/bestiary/entries/(:num)/assignments', 'CharacterBestiaryController::setAssignments/$1/$2');
        $routes->put('campaigns/(:num)/bestiary/entries/(:num)/characters/(:num)', 'CharacterBestiaryController::setAssignment/$1/$2/$3');

        // ----------------------------------------
        // WFRP 2E PLAYER CHARACTER SPELLBOOK
        // ----------------------------------------
        $routes->get('campaigns/(:num)/characters/(:num)/magic', 'HeroMagicController::show/$1/$2');
        $routes->patch('campaigns/(:num)/characters/(:num)/magic/preferences', 'HeroMagicController::preferences/$1/$2');
        $routes->patch('campaigns/(:num)/characters/(:num)/magic/profile', 'HeroMagicController::profile/$1/$2');
        $routes->put('campaigns/(:num)/characters/(:num)/magic/catalog/(:num)/reveal', 'HeroMagicController::reveal/$1/$2/$3');
        $routes->post('campaigns/(:num)/characters/(:num)/magic/learning', 'HeroMagicController::learn/$1/$2');
        $routes->post('campaigns/(:num)/magic/learning/(:num)/decision', 'HeroMagicController::decideLearning/$1/$2');
        $routes->post('campaigns/(:num)/characters/(:num)/magic/casts', 'HeroMagicController::createCast/$1/$2');
        $routes->post('campaigns/(:num)/magic/casts/(:num)/channel', 'HeroMagicController::channel/$1/$2');
        $routes->post('campaigns/(:num)/magic/casts/(:num)/advance', 'HeroMagicController::advanceCast/$1/$2');
        $routes->post('campaigns/(:num)/magic/casts/(:num)/resolve', 'HeroMagicController::resolveCast/$1/$2');
        $routes->post('campaigns/(:num)/magic/casts/(:num)/cancel', 'HeroMagicController::cancelCast/$1/$2');
        $routes->post('campaigns/(:num)/characters/(:num)/magic/rituals', 'HeroMagicController::ritual/$1/$2');
        $routes->get('campaigns/(:num)/characters/(:num)/magic/history', 'HeroMagicController::history/$1/$2');

        // ----------------------------------------
        // WORLD COMPENDIUM
        // ----------------------------------------
        $routes->get('compendiums/mine', 'CompendiumController::mine');
        $routes->get('campaigns/(:num)/compendium', 'CampaignCompendiumController::overview/$1');
        $routes->get('campaigns/(:num)/compendium/entries', 'CampaignCompendiumController::index/$1');
        $routes->get('campaigns/(:num)/compendium/timeline', 'CampaignCompendiumController::timeline/$1');
        $routes->get('campaigns/(:num)/compendium/entries/(:num)', 'CampaignCompendiumController::show/$1/$2');
        $routes->post('campaigns/(:num)/compendium/entries/(:num)/read', 'CampaignCompendiumController::recordRead/$1/$2');
        $routes->put('campaigns/(:num)/compendium/entries/(:num)/favorite', 'CampaignCompendiumController::favorite/$1/$2');
        $routes->post('campaigns/(:num)/compendium/entries/(:num)/reveal', 'CampaignCompendiumController::reveal/$1/$2');
        $routes->delete('campaigns/(:num)/compendium/entries/(:num)/reveal', 'CampaignCompendiumController::revokeReveal/$1/$2');
        $routes->post('campaigns/(:num)/compendium/entries/(:num)/pin', 'CampaignCompendiumController::pin/$1/$2');
        $routes->post('campaigns/(:num)/compendium/entries/(:num)/notes', 'CampaignCompendiumController::note/$1/$2');
        $routes->post('campaigns/(:num)/compendium/entries/(:num)/materialize', 'CampaignCompendiumController::materialize/$1/$2');
        $routes->get('universes/(:num)/compendium', 'CompendiumController::overview/$1');
        $routes->get('universes/(:num)/compendium/entries', 'CompendiumController::index/$1');
        $routes->post('universes/(:num)/compendium/entries', 'CompendiumController::create/$1');
        $routes->get('universes/(:num)/compendium/entries/(:num)', 'CompendiumController::show/$1/$2');
        $routes->patch('universes/(:num)/compendium/entries/(:num)', 'CompendiumController::update/$1/$2');
        $routes->delete('universes/(:num)/compendium/entries/(:num)', 'CompendiumController::archive/$1/$2');
        $routes->post('universes/(:num)/compendium/entries/(:num)/restore', 'CompendiumController::restore/$1/$2');
        $routes->post('universes/(:num)/compendium/entries/(:num)/publish', 'CompendiumController::publish/$1/$2');
        $routes->post('universes/(:num)/compendium/entries/(:num)/versions/(:num)/restore', 'CompendiumController::restoreVersion/$1/$2/$3');
        $routes->post('universes/(:num)/compendium/tags', 'CompendiumController::createTag/$1');
        $routes->delete('universes/(:num)/compendium/tags/(:num)', 'CompendiumController::deleteTag/$1/$2');
        $routes->post('universes/(:num)/compendium/types', 'CompendiumController::createType/$1');
        $routes->patch('universes/(:num)/compendium/types/(:num)', 'CompendiumController::updateType/$1/$2');
        $routes->delete('universes/(:num)/compendium/types/(:num)', 'CompendiumController::deleteType/$1/$2');
        $routes->put('universes/(:num)/compendium/calendar', 'CompendiumController::updateCalendar/$1');
        $routes->put('universes/(:num)/compendium/owner', 'CompendiumController::assignOwner/$1');
        $routes->post('universes/(:num)/compendium/editors', 'CompendiumController::addEditor/$1');
        $routes->delete('universes/(:num)/compendium/editors/(:num)', 'CompendiumController::removeEditor/$1/$2');
        $routes->post('universes/(:num)/compendium/assets', 'CompendiumController::uploadAsset/$1');
        $routes->get('universes/(:num)/compendium/assets', 'CompendiumController::assets/$1');
        $routes->delete('universes/(:num)/compendium/assets/(:num)', 'CompendiumController::deleteAsset/$1/$2');
        $routes->post('universes/(:num)/compendium/corpus-assets/(:num)/file', 'CompendiumController::uploadCorpusAsset/$1/$2');
        $routes->get('compendium-assets/(:num)/file', 'CompendiumController::assetFile/$1');
        $routes->get('compendium-corpus-assets/(:num)/file', 'CompendiumController::corpusAssetFile/$1');

        // ----------------------------------------
        // ADMINISTRATION
        // ----------------------------------------
        $routes->get('admin/media-assets', 'AdminMediaController::index');
        $routes->get('admin/media-audio-libraries', 'AdminMediaController::audioLibraries');
        $routes->post('admin/media-assets/upload', 'AdminMediaController::upload');
        $routes->post('admin/media-assets/external', 'AdminMediaController::registerExternal');
        $routes->post('admin/media-assets/audio-tracks/(:num)/publish', 'AdminMediaController::publishPersonalAudioTrack/$1');
        $routes->patch('admin/media-assets/bulk', 'AdminMediaController::bulk');
        $routes->post('admin/media-assets/character-sets', 'AdminMediaController::createCharacterSet');
        $routes->get('admin/media-assets/(:num)', 'AdminMediaController::show/$1');
        $routes->patch('admin/media-assets/(:num)', 'AdminMediaController::update/$1');
        $routes->delete('admin/media-assets/(:num)', 'AdminMediaController::delete/$1');
        $routes->post('admin/media-assets/(:num)/replacement', 'AdminMediaController::initiateReplacement/$1');
        $routes->post('admin/media-assets/(:num)/replacement/(:num)/complete', 'AdminMediaController::completeReplacement/$1/$2');
        $routes->post('admin/media-assets/(:num)/retry-purge', 'AdminMediaController::retryPurge/$1');
        $routes->get('admin/media-collections', 'AdminMediaController::collections');
        $routes->post('admin/media-collections', 'AdminMediaController::createCollection');
        $routes->patch('admin/media-collections/(:num)', 'AdminMediaController::updateCollection/$1');
        $routes->delete('admin/media-collections/(:num)', 'AdminMediaController::deleteCollection/$1');
        $routes->post('admin/media-collections/(:num)/assets', 'AdminMediaController::addCollectionAssets/$1');
        $routes->delete('admin/media-collections/(:num)/assets', 'AdminMediaController::removeCollectionAssets/$1');
        $routes->get('admin/overview', 'AdminController::overview');
        $routes->get('admin/audio', 'AdminController::audioOverview');
        $routes->post('admin/audio/libraries', 'AdminController::createAudioLibrary');
        $routes->patch('admin/audio/libraries/(:num)', 'AdminController::updateAudioLibrary/$1');
        $routes->delete('admin/audio/libraries/(:num)', 'AdminController::deleteAudioLibrary/$1');
        $routes->post('admin/audio/libraries/(:num)/tracks/upload', 'AdminController::uploadAudioTrack/$1');
        $routes->post('admin/audio/libraries/(:num)/tracks/external', 'AdminController::createExternalAudioTrack/$1');
        $routes->patch('admin/audio/tracks/(:num)', 'AdminController::updateAudioTrack/$1');
        $routes->delete('admin/audio/tracks/(:num)', 'AdminController::deleteAudioTrack/$1');
        $routes->get('admin/token-templates', 'AdminController::tokenTemplates');
        $routes->post('admin/token-templates', 'AdminController::createTokenTemplate');
        $routes->patch('admin/token-templates/(:num)', 'AdminController::updateTokenTemplate/$1');
        $routes->delete('admin/token-templates/(:num)', 'AdminController::deleteTokenTemplate/$1');
        $routes->get('admin/token-template-assets/(:num)/file', 'AdminController::tokenTemplateAssetFile/$1');
        $routes->get('admin/compendium', 'AdminController::compendiumOverview');
        $routes->get('admin/professions', 'AdminController::professions');
        $routes->post('admin/professions/decode', 'AdminController::decodeProfessionRequirements');
        $routes->post('admin/professions', 'AdminController::createProfession');
        $routes->patch('admin/professions/(:num)', 'AdminController::updateProfession/$1');
        $routes->delete('admin/professions/(:num)', 'AdminController::deleteProfession/$1');
        $routes->post('admin/professions/(:num)/images/(:segment)', 'AdminController::uploadProfessionImage/$1/$2');
        $routes->delete('admin/professions/(:num)/images/(:segment)', 'AdminController::deleteProfessionImage/$1/$2');
        $routes->get('profession-assets/(:num)/file', 'ProfessionController::assetFile/$1');
        $routes->post('admin/compendium/worlds', 'AdminController::createCompendiumWorld');
        $routes->patch('admin/compendium/worlds/(:num)', 'AdminController::updateCompendiumWorld/$1');
        $routes->patch('admin/compendium/worlds/(:num)/entries/(:num)/policy', 'AdminController::updateCompendiumEntryPolicy/$1/$2');
        $routes->patch('admin/compendium/worlds/(:num)/profiles/(:num)', 'AdminController::updateCompendiumProfile/$1/$2');
        $routes->patch('admin/compendium/sources/(:num)', 'AdminController::updateCompendiumSource/$1');
        $routes->post('admin/compendium/worlds/(:num)/imports/(:num)/rollback', 'AdminController::rollbackCompendiumImport/$1/$2');
        $routes->post('admin/compendium/worlds/(:num)/sync-wfrp2', 'AdminController::syncCompendiumWfrp2/$1');
        $routes->post('admin/users', 'AdminController::createUser');
        $routes->patch('admin/users/(:num)/role', 'AdminController::changeUserRole/$1');
        $routes->put('admin/characters/(:num)/campaigns/(:num)', 'AdminController::attachCharacterCampaign/$1/$2');
        $routes->delete('admin/characters/(:num)/campaigns/(:num)', 'AdminController::detachCharacterCampaign/$1/$2');
        $routes->put('admin/characters/(:num)/campaigns/(:num)/owners/(:num)', 'AdminController::attachCharacterOwner/$1/$2/$3');
        $routes->delete('admin/characters/(:num)/campaigns/(:num)/owners/(:num)', 'AdminController::detachCharacterOwner/$1/$2/$3');

        // ----------------------------------------
        // CHARACTERS
        // ----------------------------------------
        $routes->get('character-asset-sets/available', 'CharacterController::availableAssetSets');
        $routes->post('character-asset-sets', 'CharacterController::createAssetSet');
        $routes->get('campaigns/(:num)/characters', 'CharacterController::index/$1');
        $routes->get('campaigns/(:num)/professions', 'ProfessionController::campaign/$1');
        $routes->get('campaigns/(:num)/characters/(:num)/professions', 'ProfessionController::character/$1/$2');
        $routes->get('campaigns/(:num)/characters/(:num)/wallets', 'CharacterController::wallets/$1/$2');
        $routes->put('campaigns/(:num)/characters/(:num)/wallets', 'CharacterController::updateWallets/$1/$2');
        $routes->put('campaigns/(:num)/characters/(:num)/profession', 'ProfessionController::changeCharacterProfession/$1/$2');
        $routes->put('campaigns/(:num)/characters/(:num)/professions/(:num)/activate', 'ProfessionController::activateCharacterProfession/$1/$2/$3');
        $routes->put('campaigns/(:num)/characters/(:num)/professions/history-order', 'ProfessionController::reorderCharacterProfessions/$1/$2');
        $routes->delete('campaigns/(:num)/characters/(:num)/professions/(:num)', 'ProfessionController::deleteCharacterProfession/$1/$2/$3');
        $routes->get('campaigns/(:num)/resources/(:segment)/(:num)/permissions', 'ResourcePermissionController::index/$1/$2/$3');
        $routes->put('campaigns/(:num)/resources/(:segment)/(:num)/permissions/(:num)', 'ResourcePermissionController::update/$1/$2/$3/$4');
        $routes->patch('campaigns/(:num)/resources/(:segment)/(:num)/permissions/(:num)', 'ResourcePermissionController::update/$1/$2/$3/$4');
        $routes->delete('campaigns/(:num)/resources/(:segment)/(:num)/permissions/(:num)', 'ResourcePermissionController::delete/$1/$2/$3/$4');
        $routes->patch('campaigns/(:num)/characters/(:num)/visibility', 'ResourcePermissionController::characterVisibility/$1/$2');
        $routes->post('campaigns/(:num)/characters/(:num)/owners', 'ResourcePermissionController::assignCharacterOwner/$1/$2');
        $routes->post('characters/(:num)/purchase', 'CharacterController::purchase/$1');
        $routes->get('characters/(:num)/assets', 'CharacterController::assets/$1');
        $routes->put('characters/(:num)/asset-set', 'CharacterController::assignAssetSet/$1');
        $routes->get('characters', 'CharacterController::index');
        $routes->post('characters', 'CharacterController::create');
        $routes->get('characters/(:num)', 'CharacterController::show/$1');
        $routes->put('characters/(:num)', 'CharacterController::update/$1');
        $routes->patch('characters/(:num)', 'CharacterController::update/$1');
        $routes->delete('characters/(:num)', 'CharacterController::delete/$1');

        // ----------------------------------------
        // GAMES
        // ----------------------------------------
        $routes->get('games', 'RpgCatalogController::listGames');

        // ----------------------------------------
        // UNIVERSES
        // ----------------------------------------
        $routes->get('universes/(:num)', 'RpgCatalogController::showUniverse/$1');

        // ----------------------------------------
        // SYSTEMS
        // ----------------------------------------
        $routes->get('systems/(:num)/universes', 'RpgCatalogController::systemUniverses/$1');
        $routes->get('systems/(:num)/professions', 'ProfessionController::system/$1');
        $routes->get('systems/(:num)/categories', 'GameDataController::getCategories/$1');
        $routes->get('systems/(:num)/data', 'GameDataController::getDefinitions/$1');
        $routes->resource('systems', [
            'controller' => 'RpgCatalogController',
            'only'       => ['index', 'show'],
        ]);

        // ----------------------------------------
        // TRAITS
        // ----------------------------------------
        $routes->get('traits/(:num)', 'GameDataController::show/$1');

        // ----------------------------------------
        // VTT SCENES
        // /api/campaigns/{campaignId}/scenes
        // ----------------------------------------
        $routes->get('campaigns/(:num)/maps', 'MapBuilderController::index/$1');
        $routes->post('campaigns/(:num)/maps', 'MapBuilderController::create/$1');
        $routes->get('campaigns/(:num)/maps/ai/status', 'MapBuilderController::aiStatus/$1');
        $routes->get('campaigns/(:num)/maps/assets', 'MapBuilderController::assets/$1');
        $routes->post('campaigns/(:num)/maps/assets', 'MapBuilderController::uploadAsset/$1');
        $routes->get('campaigns/(:num)/maps/assets/(:num)/file', 'MapBuilderController::assetFile/$1/$2');
        $routes->post('campaigns/(:num)/maps/asset-packages', 'MapBuilderController::importPackage/$1');
        $routes->get('campaigns/(:num)/maps/(:num)', 'MapBuilderController::show/$1/$2');
        $routes->put('campaigns/(:num)/maps/(:num)', 'MapBuilderController::save/$1/$2');
        $routes->post('campaigns/(:num)/maps/(:num)/lock', 'MapBuilderController::acquireLock/$1/$2');
        $routes->delete('campaigns/(:num)/maps/(:num)/lock', 'MapBuilderController::releaseLock/$1/$2');
        $routes->get('campaigns/(:num)/maps/(:num)/revisions', 'MapBuilderController::revisions/$1/$2');
        $routes->post('campaigns/(:num)/maps/(:num)/revisions/(:num)/restore', 'MapBuilderController::restore/$1/$2/$3');
        $routes->post('campaigns/(:num)/maps/(:num)/render', 'MapBuilderController::uploadRender/$1/$2');
        $routes->post('campaigns/(:num)/maps/(:num)/publish', 'MapBuilderController::publish/$1/$2');
        $routes->post('campaigns/(:num)/maps/(:num)/ai-jobs', 'MapBuilderController::createAiJob/$1/$2');
        $routes->get('campaigns/(:num)/maps/(:num)/ai-jobs/(:segment)', 'MapBuilderController::aiJob/$1/$2/$3');
        $routes->delete('campaigns/(:num)/maps/(:num)/ai-jobs/(:segment)', 'MapBuilderController::cancelAiJob/$1/$2/$3');
        $routes->get('campaigns/(:num)/scene-assets', 'SceneController::assets/$1');
        $routes->post('campaigns/(:num)/scene-assets', 'SceneController::uploadAsset/$1');
        $routes->get('campaigns/(:num)/scene-assets/(:segment)/file', 'SceneController::assetFile/$1/$2');
        $routes->get('campaigns/(:num)/scenes', 'SceneController::index/$1');
        $routes->post('campaigns/(:num)/scenes', 'SceneController::create/$1');
        $routes->get('campaigns/(:num)/scenes/(:num)', 'SceneController::show/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)', 'SceneController::update/$1/$2');
        $routes->delete('campaigns/(:num)/scenes/(:num)', 'SceneController::delete/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/activate', 'SceneController::activate/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/duplicate', 'SceneController::duplicate/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/darkness-transition', 'SceneController::transitionDarkness/$1/$2');
        $routes->get('campaigns/(:num)/scenes/(:num)/fog', 'SceneFogController::show/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)/fog', 'SceneFogController::patch/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/fog/reset', 'SceneFogController::reset/$1/$2');
        $routes->get('campaigns/(:num)/scenes/(:num)/tokens', 'SceneTokenController::index/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/tokens', 'SceneTokenController::create/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)/tokens/(:num)', 'SceneTokenController::update/$1/$2/$3');
        $routes->delete('campaigns/(:num)/scenes/(:num)/tokens/(:num)', 'SceneTokenController::delete/$1/$2/$3');
        $routes->get('campaigns/(:num)/token-templates', 'TokenTemplateController::index/$1');
        $routes->post('campaigns/(:num)/scenes/(:num)/tokens/from-template', 'TokenTemplateController::instantiate/$1/$2');
        $routes->get('campaigns/(:num)/token-template-assets/(:num)/file', 'TokenTemplateController::assetFile/$1/$2');
        $routes->get('campaigns/(:num)/token-sync', 'TokenSyncController::index/$1');
        $routes->post('campaigns/(:num)/token-sync/preview', 'TokenSyncController::preview/$1');
        $routes->post('campaigns/(:num)/token-sync/transfer', 'TokenSyncController::transfer/$1');
        $routes->post('campaigns/(:num)/token-sync/links', 'TokenSyncController::createLinks/$1');
        $routes->patch('campaigns/(:num)/token-sync/links/(:num)', 'TokenSyncController::updateLink/$1/$2');
        $routes->post('campaigns/(:num)/token-sync/links/(:num)/apply', 'TokenSyncController::applyLink/$1/$2');
        $routes->delete('campaigns/(:num)/token-sync/links/(:num)', 'TokenSyncController::deleteLink/$1/$2');
        $routes->get('campaigns/(:num)/scenes/(:num)/combat', 'SceneCombatController::show/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/combat/commands', 'SceneCombatController::command/$1/$2');
        $routes->get('campaigns/(:num)/scenes/(:num)/walls', 'SceneWallController::index/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/walls', 'SceneWallController::create/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)/walls/(:num)', 'SceneWallController::update/$1/$2/$3');
        $routes->delete('campaigns/(:num)/scenes/(:num)/walls/(:num)', 'SceneWallController::delete/$1/$2/$3');
        $routes->post('campaigns/(:num)/scenes/(:num)/walls/(:num)/interact', 'SceneWallController::interact/$1/$2/$3');
        $routes->get('campaigns/(:num)/scenes/(:num)/lights', 'SceneLightController::index/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/lights', 'SceneLightController::create/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)/lights/(:num)', 'SceneLightController::update/$1/$2/$3');
        $routes->delete('campaigns/(:num)/scenes/(:num)/lights/(:num)', 'SceneLightController::delete/$1/$2/$3');
        $routes->get('campaigns/(:num)/scenes/(:num)/regions', 'SceneRegionController::index/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/regions', 'SceneRegionController::create/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)/regions/(:num)', 'SceneRegionController::update/$1/$2/$3');
        $routes->delete('campaigns/(:num)/scenes/(:num)/regions/(:num)', 'SceneRegionController::delete/$1/$2/$3');
        $routes->get('campaigns/(:num)/scenes/(:num)/tiles', 'SceneTileController::index/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/tiles', 'SceneTileController::create/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)/tiles/(:num)', 'SceneTileController::update/$1/$2/$3');
        $routes->delete('campaigns/(:num)/scenes/(:num)/tiles/(:num)', 'SceneTileController::delete/$1/$2/$3');

        // ----------------------------------------
        // SHOP MODULE
        // /api/shop/campaigns/{campaignId}/...
        // ----------------------------------------
        $routes->group('shop/campaigns/(:num)', [
            'filter' => 'shopCampaignAccess',
            'namespace' => 'App\\Controllers\\Api',
        ], static function (RouteCollection $routes) {
            // Bootstrap + reference data
            $routes->get('access/options', 'ShopModuleController::accessOptions/$1');
            $routes->get('bootstrap', 'ShopModuleController::bootstrap/$1');
            $routes->get('catalog/network', 'ShopModuleController::getCatalog/$1');
            $routes->get('catalog/item-dictionaries', 'ShopModuleController::getItemDictionaries/$1');
            $routes->get('catalog/consumption-profiles', 'ShopModuleController::consumptionProfiles/$1');
            $routes->post('catalog/item-dictionaries', 'ShopModuleController::createItemDictionaryEntry/$1');
            $routes->put('catalog/item-dictionaries/(:num)', 'ShopModuleController::updateItemDictionaryEntry/$1/$2');
            $routes->delete('catalog/item-dictionaries/(:num)', 'ShopModuleController::deleteItemDictionaryEntry/$1/$2');
            $routes->get('catalog/currencies', 'ShopModuleController::getCurrencies/$1');
            $routes->get('catalog/icon-metadata', 'ShopModuleController::getIconMetadata/$1');
            $routes->post('catalog/icon-metadata', 'ShopModuleController::uploadIcon/$1');
            $routes->post('catalog/icon-metadata/(:segment)/images', 'ShopModuleController::replaceIconImages/$1/$2');
            $routes->put('catalog/icon-metadata/(:segment)', 'ShopModuleController::putIconMetadata/$1/$2');
            $routes->delete('catalog/icon-metadata/(:segment)', 'ShopModuleController::deleteIconMetadata/$1/$2');
            $routes->get('world-profiles', 'ShopModuleController::getWorldProfiles/$1');

            // Shops
            $routes->get('shops', 'ShopModuleController::listShops/$1');
            $routes->post('shops', 'ShopModuleController::createShop/$1');
            $routes->get('shops/(:num)', 'ShopModuleController::showShop/$1/$2');
            $routes->patch('shops/(:num)', 'ShopModuleController::updateShop/$1/$2');
            $routes->delete('shops/(:num)', 'ShopModuleController::deleteShop/$1/$2');
            $routes->post('shops/(:num)/duplicate', 'ShopModuleController::duplicateShop/$1/$2');
            $routes->patch('shops/(:num)/activation', 'ShopModuleController::updateShopActivation/$1/$2');

            // Shop profile
            $routes->get('shops/(:num)/profile', 'ShopModuleController::getShopProfile/$1/$2');
            $routes->put('shops/(:num)/profile', 'ShopModuleController::putShopProfile/$1/$2');
            $routes->get('shops/(:num)/profile/history', 'ShopModuleController::getShopProfileHistory/$1/$2');
            $routes->get('shops/(:num)/profile/export', 'ShopModuleController::exportShopProfile/$1/$2');
            $routes->post('shops/(:num)/profile/import', 'ShopModuleController::importShopProfile/$1/$2');
            $routes->post('shops/(:num)/pricing/preview', 'ShopModuleController::previewShopPricing/$1/$2');

            // Templates
            $routes->get('templates', 'ShopModuleController::listTemplates/$1');
            $routes->post('templates', 'ShopModuleController::createTemplate/$1');
            $routes->put('templates/(:num)', 'ShopModuleController::updateTemplate/$1/$2');
            $routes->delete('templates/(:num)', 'ShopModuleController::deleteTemplate/$1/$2');
            $routes->post('templates/(:num)/restore', 'ShopModuleController::restoreTemplate/$1/$2');
            $routes->post('templates/(:num)/duplicate', 'ShopModuleController::duplicateTemplate/$1/$2');

            // Personalized item instances
            $routes->patch('item-instances/(:num)', 'ShopModuleController::updateItemInstance/$1/$2');
            $routes->post('item-instances', 'ShopModuleController::createItemInstance/$1');
            $routes->post('consumption/consume', 'ShopModuleController::consumeItem/$1');
            $routes->post('consumption/world-time', 'ShopModuleController::advanceConsumptionWorldTime/$1');

            // Containers
            $routes->get('containers', 'ShopModuleController::getContainers/$1');
            $routes->post('containers/move', 'ShopModuleController::moveContainer/$1');
            $routes->patch('containers/quantities', 'ShopModuleController::setContainerQuantities/$1');
            $routes->post('containers/buy', 'ShopModuleController::buyFromContainer/$1');
            $routes->post('containers/trash', 'ShopModuleController::trashContainerItem/$1');
            $routes->post('containers/restore', 'ShopModuleController::restoreContainerItem/$1');
            $routes->post('containers/merge', 'ShopModuleController::mergeContainerItems/$1');

            // Player trade
            $routes->post('trade/buy/quote', 'ShopModuleController::quoteTradeBuyPayment/$1');
            $routes->post('trade/buy', 'ShopModuleController::tradeBuy/$1');
            $routes->post('trade/sell', 'ShopModuleController::tradeSell/$1');
            $routes->get('trade/ledger', 'ShopModuleController::listTradeLedger/$1');
            $routes->post('trade/ledger/(:num)/reverse', 'ShopModuleController::reverseTradeLedger/$1/$2');
            $routes->post('trade/ledger/(:num)/redo', 'ShopModuleController::redoTradeLedger/$1/$2');
            $routes->post('trade/ledger/(:num)/correct', 'ShopModuleController::correctTradeLedger/$1/$2');

            // Assortment
            $routes->post('shops/(:num)/assortment/replace', 'ShopModuleController::replaceAssortment/$1/$2');
            $routes->post('shops/(:num)/assortment/transfer', 'ShopModuleController::transferAssortment/$1/$2');
            $routes->post('shops/(:num)/assortment/roll', 'ShopModuleController::rollAssortment/$1/$2');

            // Suggestions
            $routes->post('shops/(:num)/suggestions/generate', 'ShopModuleController::generateSuggestions/$1/$2');
            $routes->get('shops/(:num)/suggestions', 'ShopModuleController::getSuggestions/$1/$2');
            $routes->post('shops/(:num)/suggestions/promote', 'ShopModuleController::promoteSuggestions/$1/$2');
            $routes->post('shops/(:num)/suggestions/apply', 'ShopModuleController::applySuggestions/$1/$2');
            $routes->post('shops/(:num)/suggestions/materialize', 'ShopModuleController::materializeSuggestion/$1/$2');
        });
    });
});
