<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extends the primary navigation to reorder items and add custom links.
 *
 * Order: Home -> My courses -> Calender (Dashboard)
 *
 * @param navigation_node $nav The navigation node to extend.
 */
function local_placement_extend_navigation_primary(navigation_node $nav) {
    // 1. Find the existing nodes.
    $home = $nav->find('home', navigation_node::TYPE_SYSTEM);
    $mycourses = $nav->find('mycourses', navigation_node::TYPE_ROOTNODE);
    $dashboard = $nav->find('myhome', navigation_node::TYPE_SYSTEM);

    // 2. Remove them from their current positions.
    if ($home) {
        $home->remove();
    }
    if ($mycourses) {
        $mycourses->remove();
    }
    if ($dashboard) {
        $dashboard->remove();
    }

    // 3. Re-add them in the order: Home -> My courses -> Dashboard.
    if ($home) {
        $nav->add_node($home);
    }
    if ($mycourses) {
        $nav->add_node($mycourses);
    }
    if ($dashboard) {
        $nav->add_node($dashboard);
    }
}
