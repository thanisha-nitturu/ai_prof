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

namespace tiny_videolesson;

use context;
use editor_tiny\plugin;
use editor_tiny\plugin_with_buttons;
use editor_tiny\plugin_with_menuitems;
use editor_tiny\plugin_with_configuration;

/**
 * Tiny Video Lesson plugin for Moodle.
 *
 * @package    tiny_videolesson
 * @author     BitKea Technologies LLP
 * @copyright  2024 BitKea Technologies LLP (https://www.bitkea.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugininfo extends plugin implements
    plugin_with_buttons,
    plugin_with_configuration,
    plugin_with_menuitems{
    /**
     * Check if the plugin is enabled.
     *
     * @param context $context The context.
     * @param array $options The options.
     * @param array $fpoptions The filepicker options.
     * @param \editor_tiny\editor|null $editor The editor.
     * @return bool
     */
    public static function is_enabled(
        context $context,
        array $options,
        array $fpoptions,
        ?\editor_tiny\editor $editor = null
    ): bool {
        // Users must have permission to embed content.
        return has_capability('tiny/videolesson:addembed', $context);
    }

    /**
     * Get the available buttons.
     *
     * @return array
     */
    public static function get_available_buttons(): array {
        return [
            'tiny_videolesson/videolesson',
        ];
    }

    /**
     * Get the available menu items.
     *
     * @return array
     */
    public static function get_available_menuitems(): array {
        return [
            'tiny_videolesson/videolesson',
        ];
    }

    /**
     * Get the plugin configuration for the context.
     *
     * @param context $context The context.
     * @param array $options The options.
     * @param array $fpoptions The filepicker options.
     * @param \editor_tiny\editor|null $editor The editor.
     * @return array
     */
    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?\editor_tiny\editor $editor = null
    ): array {
        $permissions = [
            'embed' => has_capability('tiny/videolesson:addembed', $context),
            'upload' => has_capability('mod/videolesson:manage', $context),
        ];
        $permissions['uploadandembed'] = $permissions['embed'] && $permissions['upload'];

        return [
            'permissions' => $permissions,
            'storeinrepo' => true,
        ];
    }
}
