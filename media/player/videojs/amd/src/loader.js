// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Video JS loader.
 *
 * This takes care of applying the filter on content which was dynamically loaded.
 *
 * @module     media_videojs/loader
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Config from 'core/config';
import { eventTypes } from 'core_filters/events';
import LocalStorage from 'core/localstorage';
import Notification from 'core/notification';
import jQuery from 'jquery';

/** @var {bool} Whether this is the first load of videojs module */
let firstLoad;

/** @var {string} The language that is used in the player */
let language;

/** @var {object} List of languages and translations for the current page */
let langStringCache;

/**
 * Initialisei teh videojs Loader.
 *
 * Adds the listener for the event to then notify video.js.
 *
 * @method
 * @param {string} lang Language to be used in the player
 * @listens event:filterContentUpdated
 */
export const setUp = (lang) => {
    language = lang;
    firstLoad = true;

    // Notify Video.js about the nodes already present on the page.
    notifyVideoJS({
        detail: {
            nodes: document.body,
        }
    });

    // We need to call popover automatically if nodes are added to the page later.
    document.addEventListener(eventTypes.filterContentUpdated, notifyVideoJS);
};

/**
 * Notify video.js of new nodes.
 *
 * @param {Event} e The event.
 */
const notifyVideoJS = e => {
    const nodes = jQuery(e.detail.nodes);
    const selector = '.mediaplugin_videojs';
    const langStrings = getLanguageJson();

    // Find the descendants matching the expected parent of the audio and video
    // tags. Then also addBack the nodes matching the same selector. Finally,
    // we find the audio and video tags contained in those parents. Kind thanks
    // to jQuery for the simplicity.
    nodes.find(selector)
        .addBack(selector)
        .find('audio, video').each((index, element) => {
            const id = jQuery(element).attr('id');
            const config = jQuery(element).data('setup-lazy');
            const modulePromises = [import('media_videojs/video-lazy')];

            if (config.techOrder && config.techOrder.indexOf('youtube') !== -1) {
                // Add YouTube to the list of modules we require.
                modulePromises.push(import('media_videojs/Youtube-lazy'));
            }
            if (config.techOrder && config.techOrder.indexOf('OgvJS') !== -1) {
                config.ogvjs = {
                    worker: true,
                    wasm: true,
                    base: Config.wwwroot + '/media/player/videojs/ogvloader.php/' + Config.jsrev + '/'
                };
                // Add Ogv.JS to the list of modules we require.
                modulePromises.push(import('media_videojs/videojs-ogvjs-lazy'));
            }

            // Override hotkeys to implement 10s seek and standard controls.
            config.userActions = config.userActions || {};
            config.userActions.hotkeys = function (event) {
                const p = this;
                const key = event.which || event.keyCode;

                if (key === 32 || key === 75) { // Space or K
                    event.preventDefault();
                    if (p.paused()) {
                        p.play();
                    } else {
                        p.pause();
                    }
                    return true; // TELL VIDEOJS WE HANDLED IT
                } else if (key === 37) { // Left
                    event.preventDefault();
                    p.currentTime(Math.max(0, p.currentTime() - 10));
                    return true;
                } else if (key === 39) { // Right
                    event.preventDefault();
                    let dur = p.duration();
                    if (!isFinite(dur) || isNaN(dur)) {
                        dur = p.currentTime() + 100;
                    }
                    p.currentTime(Math.min(dur, p.currentTime() + 10));
                    return true;
                } else if (key === 38) { // Up
                    event.preventDefault();
                    p.volume(Math.min(1, p.volume() + 0.1));
                    return true;
                } else if (key === 40) { // Down
                    event.preventDefault();
                    p.volume(Math.max(0, p.volume() - 0.1));
                    return true;
                } else if (key === 77) { // M
                    event.preventDefault();
                    p.muted(!p.muted());
                    return true;
                } else if (key === 70) { // F
                    event.preventDefault();
                    if (p.isFullscreen()) {
                        p.exitFullscreen();
                    } else {
                        p.requestFullscreen();
                    }
                    return true;
                }
                // Return false to let VideoJS handle any other keys
                return false;
            };

            Promise.all([langStrings, ...modulePromises])
                .then(([langJson, videojs]) => {
                    if (firstLoad) {
                        videojs.addLanguage(language, langJson);

                        firstLoad = false;
                    }
                    const player = videojs(id, config);
                    window.console.log(
                        '%c[media_videojs/loader] ✅ PATCHED v2 — audio track switcher active',
                        'background:#1a1a2e;color:#00ff99;font-weight:bold;padding:3px 8px;border-radius:4px'
                    );



                    // ── Seek Buttons (10s Forward/Backward) ──────────────────────────────
                    const Button = videojs.getComponent('Button');

                    /** Seek Backward 10s */
                    class SeekBackwardButton extends Button {
                        constructor(p, opt) {
                            super(p, opt);
                            this.controlText('Back 10s');
                        }
                        buildCSSClass() {
                            return 'vjs-skip-backward vjs-control vjs-button vjs-icon-replay-10';
                        }
                        handleClick() {
                            const p = this.player();
                            const now = p.currentTime();
                            const seekTarget = Math.max(0, now - 10);
                            try {
                                p.currentTime(seekTarget);
                            } catch (e) {
                                window.console.error('[SeekDebug] Backward seek failed:', e);
                            }
                        }
                    }

                    /** Seek Forward 10s */
                    class SeekForwardButton extends Button {
                        constructor(p, opt) {
                            super(p, opt);
                            this.controlText('Forward 10s');
                        }
                        buildCSSClass() {
                            return 'vjs-skip-forward vjs-control vjs-button vjs-icon-forward-10';
                        }
                        handleClick() {
                            const p = this.player();
                            const now = p.currentTime();
                            let duration = p.duration();
                            if (!isFinite(duration) || isNaN(duration)) {
                                duration = now + 100;
                            }
                            const seekTarget = Math.min(duration, now + 10);
                            try {
                                p.currentTime(seekTarget);
                            } catch (e) {
                                window.console.error('[SeekDebug] Forward seek failed:', e);
                            }
                        }
                    }

                    if (!videojs.getComponent('SeekBackwardButton')) {
                        videojs.registerComponent('SeekBackwardButton', SeekBackwardButton);
                    }
                    if (!videojs.getComponent('SeekForwardButton')) {
                        videojs.registerComponent('SeekForwardButton', SeekForwardButton);
                    }

                    player.controlBar.addChild('SeekBackwardButton', {}, 1);
                    player.controlBar.addChild('SeekForwardButton', {}, 2);

                    // ── Audio Track Switcher ──────────────────────────────────────────────
                    // Strategy:
                    //  1. PRIMARY: Wait for 'loadedmetadata', check player.audioTracks().
                    //     VHS populates this automatically for valid HLS multi-audio streams.
                    //  2. FALLBACK: If tracks.length is still 0 AND the source is HLS (.m3u8),
                    //     fetch the master playlist directly, parse EXT-X-MEDIA AUDIO lines,
                    //     and build the UI ourselves using the parsed track list.
                    //     Switching in fallback mode enables the track via the standard API;
                    //     if VHS honours that, great — otherwise it falls through  to source-swap.
                    let audioButtonInjected = false;

                    /**
                     * Builds the AudioMenuButton component and adds it to the control bar.
                     * Works with both VHS-managed tracks (trackList = player.audioTracks())
                     * and manually-parsed tracks (trackList = plain array of {label, enabled}).
                     *
                     * @param {AudioTrackList|Array} trackList  Track objects to display.
                     * @param {boolean}              isManual   True when using fetch-parsed tracks.
                     * @param {string}               baseUrl    Base URL of the HLS manifest folder.
                     * @param {string}               videoUrl   Absolute URL of the video-only playlist.
                     */
                    function buildAudioUI(trackList, isManual, baseUrl, videoUrl) {
                        if (audioButtonInjected) {
                            return;
                        }
                        audioButtonInjected = true;

                        window.console.log(
                            '[AudioTrack] ✅ Building UI for', trackList.length,
                            'tracks. Manual mode =', isManual
                        );

                        const MenuButton = videojs.getComponent('MenuButton');
                        const MenuItem = videojs.getComponent('MenuItem');

                        /**
                         * Custom VideoJS MenuButton that lists every audio track.
                         * Uses ES6 class syntax — videojs.extend() was removed in Video.js 8.
                         */
                        class AudioMenuButton extends MenuButton {

                            /**
                             * @param {Player}  p        The Video.js player instance.
                             * @param {Object}  options  VideoJS component options.
                             */
                            constructor(p, options) {
                                super(p, options);
                                this.controlText('Audio Track');
                                this.addClass('vjs-audio-button');
                            }

                            /**
                             * Builds the list of MenuItem children, one per audio track.
                             *
                             * @returns {Array} MenuItem instances.
                             */
                            createItems() {
                                const items = [];

                                for (let i = 0; i < trackList.length; i++) {
                                    const label = trackList[i].label || ('Track ' + (i + 1));

                                    const item = new MenuItem(player, {
                                        label: label,
                                        selectable: true,
                                        selected: !!trackList[i].enabled,
                                    });

                                    // IIFE to capture loop variable `i` correctly.
                                    (function (trackIndex) {
                                        item.on('click', function () {
                                            if (isManual) {
                                                // ── Manual / Fallback Mode ─────────────────────────
                                                // VHS doesn't expose its audio API, so we build a
                                                // single-audio HLS master in-memory (Blob URL) and
                                                // swap the player source, seeking back to saved time.
                                                const savedTime = player.currentTime();
                                                const wasPlaying = !player.paused();
                                                const selected = trackList[trackIndex];

                                                const audioAbsUri = selected.uri.startsWith('http')
                                                    ? selected.uri
                                                    : baseUrl + selected.uri;

                                                // Minimal single-audio HLS master with absolute URIs
                                                // so VHS can resolve all segments correctly.
                                                const miniMaster = [
                                                    '#EXTM3U',
                                                    '#EXT-X-VERSION:3',
                                                    '#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID="audio"' +
                                                    ',LANGUAGE="' + selected.language + '"' +
                                                    ',NAME="' + selected.label + '"' +
                                                    ',DEFAULT=YES,AUTOSELECT=YES' +
                                                    ',URI="' + audioAbsUri + '"',
                                                    '#EXT-X-STREAM-INF:BANDWIDTH=500000' +
                                                    ',CODECS="avc1.640029,mp4a.40.2"' +
                                                    ',AUDIO="audio"',
                                                    videoUrl
                                                ].join('\n');

                                                window.console.log(
                                                    '[AudioTrack] Switching to:', selected.label,
                                                    '| savedTime =', savedTime,
                                                    '\nMini-master:\n', miniMaster
                                                );

                                                const blob = new Blob(
                                                    [miniMaster],
                                                    { type: 'application/vnd.apple.mpegurl' }
                                                );
                                                const blobUrl = URL.createObjectURL(blob);

                                                player.src({
                                                    src: blobUrl,
                                                    type: 'application/x-mpegURL'
                                                });

                                                player.one('loadedmetadata', function () {
                                                    player.currentTime(savedTime);
                                                    if (wasPlaying) {
                                                        player.play();
                                                    }
                                                    window.console.log(
                                                        '[AudioTrack] ✅ Switched to', selected.label,
                                                        '— resumed at', savedTime, 's'
                                                    );
                                                });
                                            } else {
                                                // ── VHS Mode ──────────────────────────────────────
                                                // VHS manages AudioTrackList directly; enabling a
                                                // track causes VHS to fetch the correct audio segment.
                                                for (let j = 0; j < trackList.length; j++) {
                                                    trackList[j].enabled = false;
                                                }
                                                trackList[trackIndex].enabled = true;
                                            }

                                            // Update the visual selection in the menu.
                                            items.forEach(function (it, idx) {
                                                it.selected(idx === trackIndex);
                                            });
                                        });
                                    })(i);

                                    items.push(item);
                                }

                                return items;
                            }
                        }

                        // Required so Video.js doesn't try to render default children.
                        AudioMenuButton.prototype.options_ = { children: [] };

                        // Use a unique name per player to avoid re-registration errors
                        // when multiple video players appear on the same page.
                        const componentName = 'AudioMenuButton_' + id;
                        if (!videojs.getComponent(componentName)) {
                            videojs.registerComponent(componentName, AudioMenuButton);
                        }
                        player.controlBar.addChild(
                            componentName, {}, player.controlBar.children().length - 2
                        );
                        window.console.log('[AudioTrack] ✅ AudioMenuButton injected into control bar!');
                    }

                    /**
                     * Fetches and parses the HLS master playlist to find audio track definitions.
                     * Used as a fallback when VHS does not populate player.audioTracks() — which
                     * can happen if the master.m3u8 is malformed or VHS initialises late.
                     *
                     * @param {string} srcUrl  Full URL of the master.m3u8 file.
                     */
                    function fallbackHlsAudioDetect(srcUrl) {
                        window.console.log('[AudioTrack] VJS tracks = 0. Trying HLS manifest fetch fallback...');

                        const baseUrl = srcUrl.substring(0, srcUrl.lastIndexOf('/') + 1);

                        // ISO 639 language code → human-readable display name.
                        // Used as fallback when the HLS NAME field is auto-generated (e.g. "audio_1").
                        const langMap = {
                            'ger': 'German', 'deu': 'German', 'de': 'German',
                            'eng': 'English', 'en': 'English',
                            'spa': 'Spanish', 'esp': 'Spanish', 'es': 'Spanish',
                            'fra': 'French', 'fre': 'French', 'fr': 'French',
                            'hin': 'Hindi', 'hi': 'Hindi',
                            'tel': 'Telugu', 'te': 'Telugu',
                            'tam': 'Tamil', 'ta': 'Tamil',
                            'ara': 'Arabic', 'ar': 'Arabic',
                            'zho': 'Chinese', 'zh': 'Chinese',
                            'jpn': 'Japanese', 'ja': 'Japanese',
                            'kor': 'Korean', 'ko': 'Korean',
                            'por': 'Portuguese', 'pt': 'Portuguese',
                            'rus': 'Russian', 'ru': 'Russian',
                            'mal': 'Malayalam', 'ml': 'Malayalam',
                        };

                        fetch(srcUrl, { credentials: 'include' })
                            .then(function (r) {
                                if (!r.ok) {
                                    throw new Error('HTTP ' + r.status);
                                }
                                return r.text();
                            })
                            .then(function (text) {
                                const parsedTracks = [];
                                let videoUri = '';

                                const lines = text.split('\n');
                                lines.forEach(function (line, idx) {
                                    const trimmed = line.trim();

                                    // Extract audio track definitions.
                                    if (trimmed.startsWith('#EXT-X-MEDIA:TYPE=AUDIO')) {
                                        let name = (trimmed.match(/NAME="([^"]+)"/) || [])[1];
                                        const lang = (trimmed.match(/LANGUAGE="([^"]+)"/) || [])[1] || '';
                                        const uri = (trimmed.match(/URI="([^"]+)"/) || [])[1] || '';
                                        const isDef = trimmed.includes('DEFAULT=YES');

                                        // If the name is generic (audio_1, etc.), try to look up a better name via langMap.
                                        if (name && name.startsWith('audio_') && lang && langMap[lang.toLowerCase()]) {
                                            name = langMap[lang.toLowerCase()];
                                        }

                                        if (name) {
                                            parsedTracks.push({
                                                label: name,
                                                language: lang,
                                                uri: uri,
                                                enabled: isDef
                                            });
                                        }
                                    }

                                    // Find the video rendition (the EXT-X-STREAM-INF that has RESOLUTION=).
                                    if (trimmed.startsWith('#EXT-X-STREAM-INF') &&
                                        trimmed.includes('RESOLUTION=') && !videoUri) {
                                        const nextLine = (lines[idx + 1] || '').trim();
                                        if (nextLine && !nextLine.startsWith('#')) {
                                            videoUri = nextLine;
                                        }
                                    }
                                });

                                window.console.log(
                                    '[AudioTrack] Manifest parsed. Found tracks:',
                                    parsedTracks.map(function (t) { return t.label; }),
                                    '| videoUri =', videoUri
                                );

                                if (parsedTracks.length <= 1) {
                                    window.console.warn(
                                        '[AudioTrack] ⛔ Only', parsedTracks.length,
                                        'audio track(s) in manifest. Need >1 to show switcher.'
                                    );
                                    return;
                                }

                                // Convert the video variant URI to an absolute URL.
                                const videoUrl = videoUri.startsWith('http')
                                    ? videoUri
                                    : baseUrl + videoUri;

                                buildAudioUI(parsedTracks, true, baseUrl, videoUrl);
                            })
                            .catch(function (e) {
                                window.console.warn('[AudioTrack] Manifest fetch failed:', e);
                            });
                    }

                    /**
                     * Primary entry point. Called on 'loadedmetadata' and 'addtrack'.
                     * Tries VHS-managed tracks first; falls back to HLS manifest fetch.
                     */
                    function injectAudioButton() {
                        if (audioButtonInjected) {
                            return;
                        }

                        const tracks = player.audioTracks();
                        window.console.log(
                            '[AudioTrack] injectAudioButton called | VJS tracks.length =', tracks ? tracks.length : 0,
                            '| readyState =', player.readyState()
                        );

                        if (tracks && tracks.length > 1) {
                            // ✅ VHS correctly populated the track list — use it directly.
                            buildAudioUI(tracks, false);
                            return;
                        }

                        // ── Fallback: parse the HLS manifest ourselves ──────────────────
                        const srcUrl = player.currentSrc() || '';
                        if (srcUrl.includes('.m3u8') || srcUrl.includes('mpegURL') || srcUrl.includes('x-mpegurl')) {
                            fallbackHlsAudioDetect(srcUrl);
                        } else {
                            window.console.warn(
                                '[AudioTrack] ⛔ Skipping — need >1 VJS track or HLS source. Got:',
                                tracks ? tracks.length : 0, 'tracks, src:', srcUrl
                            );
                        }
                    }

                    // Primary: loadedmetadata fires when HLS manifest is parsed / media header read.
                    player.one('loadedmetadata', injectAudioButton);

                    // Fallback: some browsers fire addtrack asynchronously after loadedmetadata.
                    player.audioTracks().on('addtrack', injectAudioButton);
                    // ── End Audio Track Switcher ──────────────────────────────────────────

                    return;
                })
                .catch(Notification.exception);
        });
};

/**
 * Returns the json object of the language strings to be used in the player.
 *
 * @returns {Promise}
 */
const getLanguageJson = () => {
    if (langStringCache) {
        return Promise.resolve(langStringCache);
    }

    const cacheKey = `media_videojs/${language}`;

    const rawCacheContent = LocalStorage.get(cacheKey);
    if (rawCacheContent) {
        const cacheContent = JSON.parse(rawCacheContent);

        langStringCache = cacheContent;

        return Promise.resolve(langStringCache);
    }

    const request = {
        methodname: 'media_videojs_get_language',
        args: {
            lang: language,
        },
    };

    return Ajax.call([request])[0]
        .then(langStringData => {
            LocalStorage.set(cacheKey, langStringData);

            return langStringData;
        })
        .then(result => JSON.parse(result))
        .then(langStrings => {
            langStringCache = langStrings;

            return langStrings;
        });
};
