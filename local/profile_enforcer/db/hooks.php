<?php
/**
 * Hook callbacks for the profile enforcer placement
 *
 * @package    local_profile_enforcer
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_profile_enforcer\hook_callbacks::class . '::before_standard_head_html_generation',
        'priority' => 1,
    ],
];
