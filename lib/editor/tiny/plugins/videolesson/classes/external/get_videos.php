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
 * External functions for Video Lesson
 *
 * @package    tiny_videolesson
 * @author     BitKea Technologies LLP
 * @copyright  2024 BitKea Technologies LLP (https://www.bitkea.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_videolesson\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_multiple_structure;
use context_system;
/**
 * External function for fetching videos.
 *
 * @package    tiny_videolesson
 * @author     BitKea Technologies LLP
 * @copyright  2024 BitKea Technologies LLP (https://www.bitkea.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_videos extends external_api {
    /**
     * Define the parameters for the external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * The function logic.
     *
     * @return array
     */
    public static function execute() {
        $context = context_system::instance();
        if (!has_capability('tiny/videolesson:addembed', $context)) {
            return [
                'list' => [],
            ];
        }
        $videosource = new \mod_videolesson\videosource();
        $items = $videosource->get_items(null, true);

        // Add placeholder URL to each video item.
        $placeholder = (new \moodle_url('/mod/videolesson/pix/monologo.svg'))->out(false);
        foreach ($items as $key => $item) {
            $items[$key]['placeholder'] = $placeholder;
        }

        return [
            'list' => $items,
        ];
    }

    /**
     * Define the return structure of the external function.
     * @return external_multiple_structure
     */
    public static function execute_returns() {

        return new external_single_structure([
            'list' => new external_multiple_structure(
                new external_single_structure([
                    'selected' => new external_value(PARAM_BOOL, 'Indicates if the video is selected'),
                    'name' => new external_value(PARAM_TEXT, 'Name of the video'),
                    'contenthash' => new external_value(PARAM_ALPHANUMEXT, 'Content hash of the video'),
                    'src' => new external_value(PARAM_URL, 'Source URL of the video'),
                    'type' => new external_value(PARAM_TEXT, 'MIME type of the video'),
                    'duration' => new external_value(PARAM_TEXT, 'Formatted duration of the video'),
                    'timeadded' => new external_value(PARAM_TEXT, 'Time when the video was added, formatted'),
                    'thumbnail' => new external_value(PARAM_URL, 'Thumbnail URL of the video'),
                    'transcodingstatus' => new external_value(PARAM_TEXT, 'Video transcoding status'),
                    'transcodingstatustext' => new external_value(PARAM_TEXT, 'Video transcoding status'),
                    'transcodingstatusbadgeclass' => new external_value(PARAM_TEXT, 'Video transcoding status'),
                    'foldername' => new external_value(PARAM_TEXT, 'Folder name', VALUE_OPTIONAL, null),
                    'folderid' => new external_value(PARAM_INT, 'Folder ID', VALUE_OPTIONAL, null),
                    'placeholder' => new external_value(PARAM_URL, 'Placeholder image URL for fallback', VALUE_OPTIONAL, null),
                ])
            ),
        ]);
    }
}
