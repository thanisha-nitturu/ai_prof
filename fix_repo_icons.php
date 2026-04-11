<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/clilib.php');

$svgsource = __DIR__ . '/ai.prof_logo.svg';
$pngsource = __DIR__ . '/ai.prof_logo.png';

echo "Building High-Quality SVG Branding...\n";

if (!file_exists($svgsource)) {
    die("Error: ai.prof_logo.svg not found in Moodle root.\n");
}

// Distribute to Repositories
$reposdir = __DIR__ . '/repository';
$repos = new DirectoryIterator($reposdir);

foreach ($repos as $repo) {
    if ($repo->isDir() && !$repo->isDot()) {
        $pixdir = $repo->getPathname() . '/pix';
        if (is_dir($pixdir)) {
            echo "Updating High-Res icons for " . $repo->getFilename() . "...\n";
            
            // 1. Update SVG version (the sharpest version)
            copy($svgsource, $pixdir . "/icon.svg");
            chmod($pixdir . "/icon.svg", 0664);

            // 2. Update PNG version (the fallback version)
            // We use the PNG copy we made earlier to keep things official
            if (file_exists($pngsource)) {
                copy($pngsource, $pixdir . "/icon.png");
                chmod($pixdir . "/icon.png", 0664);
            }
        }
    }
}

// Purge Caches
echo "Purging Moodle caches for high-res icons...\n";
purge_all_caches();

echo "High-Res Branding Complete!\n";
