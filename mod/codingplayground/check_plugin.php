<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$pluginman = core_plugin_manager::instance();
$plugin = $pluginman->get_plugin_info('mod_codingplayground');

if ($plugin) {
    echo "Plugin mod_codingplayground found!\n";
    echo "Status: " . $plugin->get_status() . "\n";
    echo "Release: " . $plugin->release . "\n";
    echo "Version: " . $plugin->versiondb . " (DB) / " . $plugin->versiondisk . " (Disk)\n";
} else {
    echo "Plugin mod_codingplayground NOT found in Moodle registry.\n";
    
    // Check if the directory exists and has version.php
    $path = $CFG->dirroot . '/mod/codingplayground';
    if (is_dir($path)) {
        echo "Directory exists: $path\n";
        if (file_exists($path . '/version.php')) {
            echo "version.php exists.\n";
            include($path . '/version.php');
            echo "Component in version.php: " . ($plugin->component ?? 'NOT SET') . "\n";
        } else {
            echo "version.php MISSING.\n";
        }
    } else {
        echo "Directory MISSING: $path\n";
    }
}
