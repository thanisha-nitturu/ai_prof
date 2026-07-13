<?php
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
 * local lib
 * @package    filter_videolesson
 * @author     BitKea Technologies LLP
 * @copyright  2024 BitKea Technologies LLP (https://www.bitkea.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generate the HTML for rendering the video player.
 *
 * @param string $text The text to process.
 * @param array $options The options to pass to the filter.
 * @return string The HTML for rendering the video player.
 */
function filter_videolesson_generate_player($text, array $options = []) {
    global $PAGE;

    // Check if filter is enabled (support both old and new filter names for backward compatibility).
    if (!filter_is_enabled('videolesson') && !filter_is_enabled('videoaws')) {
        return $text;
    }

    // Process video player tags - support both [videoaws...] and [videolesson...] for backward compatibility.
    $hasvideotags = (strpos($text, '[videoaws') !== false) || (strpos($text, '[videolesson') !== false);

    if ($hasvideotags) {
        // Process both old [videoaws...] and new [videolesson...] patterns.
        $patterns = [
            '/\[videoaws(?: title="([^"]+)")? hash="([a-f0-9]{40})"\]/', // Backward compatibility.
            '/\[videolesson(?: title="([^"]+)")? hash="([a-f0-9]{40})"\]/', // New syntax.
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace_callback(
                $pattern,
                function ($matches) use ($PAGE) {
                    // Handle missing title.
                    $title = isset($matches[1]) ? $matches[1] : null;
                    $hash = $matches[2];
                    $videosource = new \mod_videolesson\videosource();
                    $videodata = $videosource->get_video_data(
                        $hash
                    );

                    $subs = '';
                    foreach ($videodata['subtitles'] as $subtitle) {
                        $subs .= '<track kind="subtitles" label="' . $subtitle['language'] . '"'
                            . ' srclang="' . $subtitle['code'] . '" src="' . $subtitle['url'] . '">';
                    }

                    // Generate the HTML for rendering.
                    return '<div id="player-placeholder-' . $hash . '">'
                        . '<div class="bg-pulse-grey my-2" style="height: 200px;width: 100%; border-radius: 10px;"></div>'
                        . '</div>'
                        . '<div id="player-container-div-' . $hash . '" class="d-none">'
                        . '<video id="player-' . $hash . '" data-hash="' . $hash . '" data-source="'
                        . $videodata['sourceurl'] . '" playsinline controls>' . $subs . '</video>'
                        . '</div>';
                },
                $text
            );
        }
    }

    return $text;
}
