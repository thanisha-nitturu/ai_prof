<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/clilib.php');

$svgsource = __DIR__ . '/ai.prof.svg';
$pngsource = __DIR__ . '/ai.prof.png';

echo "Updating MoodleNet Branding...\n";

if (!file_exists($svgsource) || !file_exists($pngsource)) {
    die("Error: Official logo files (SVG/PNG) not found in Moodle root.\n");
}

$logo_map = [
    // core
    __DIR__ . '/pix/moodlelogo.svg' => $svgsource,
    __DIR__ . '/pix/moodlelogo.png' => $pngsource,
    __DIR__ . '/pix/moodlelogo_grayhat.svg' => $svgsource,
    __DIR__ . '/pix/moodlelogo_grayhat.png' => $pngsource,
    
    // moodlenet (Additional)
    __DIR__ . '/pix/moodlenet.svg' => $svgsource,
    __DIR__ . '/pix/moodlenet.png' => $pngsource,

    // theme moove
    __DIR__ . '/theme/moove/pix/moodle-logo-white.png' => $pngsource,
    __DIR__ . '/theme/moove/pix/moodle-logo-blue.png' => $pngsource,
    
    // admin tool
    __DIR__ . '/admin/tool/brickfield/pix/moodle-logo.png' => $pngsource
];

foreach ($logo_map as $target => $source) {
    if (file_exists($target)) {
        echo "Updating " . basename($target) . " with High-Res logo...\n";
        
        // Backup
        $backup = $target . ".bak";
        if (!file_exists($backup)) {
            copy($target, $backup);
        }

        copy($source, $target);
        chmod($target, 0664);
    }
}

// Update REPOSITORY icons (SVG + PNG)
echo "Updating Repository icons...\n";
$reposdir = __DIR__ . '/repository';
$repos = new DirectoryIterator($reposdir);
foreach ($repos as $repo) {
    if ($repo->isDir() && !$repo->isDot()) {
        $pixdir = $repo->getPathname() . '/pix';
        if (is_dir($pixdir)) {
            copy($pngsource, $pixdir . "/icon.png");
            copy($svgsource, $pixdir . "/icon.svg");
            chmod($pixdir . "/icon.png", 0664);
            chmod($pixdir . "/icon.svg", 0664);
        }
    }
}

// Purge Caches
echo "Purging all caches...\n";
purge_all_caches();

echo "MoodleNet Branding Complete!\n";
