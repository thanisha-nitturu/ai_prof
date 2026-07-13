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
 * Player for filter_videolesson plugin
 *
 * @module     filter_videolesson/player
 * @copyright  2022-2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {initializePlayer} from 'mod_videolesson/script';

export const init = () => {
    // Support both old and new class names for backward compatibility
    const placeholders = document.querySelectorAll('.videolesson-placeholder, .videoaws-placeholder');
    placeholders.forEach(placeholder => {
        const videos = placeholder.querySelectorAll('video');
        videos.forEach(video => {
            if (video.dataset.initialized) {
                return;
            }

            const hash = video.dataset.hash;
            const player_container = placeholder.querySelector(`#player-container-div-${hash}`);
            const player_placeholder = placeholder.querySelector(`#player-placeholder-${hash}`);

            if (!player_container || !player_placeholder) {
                return;
            }

            const params = {
                ishls: true,
                video: video,
            };

            initializePlayer(params)
                .then(() => {
                    video.dataset.initialized = true;
                    player_container.classList.remove('d-none');
                    player_placeholder.classList.add('d-none');
                    return {initialized: true};
                })
                .catch(() => {
                    player_container.classList.add('d-none');
                    player_placeholder.classList.remove('d-none');
                });
        });
    });
};
