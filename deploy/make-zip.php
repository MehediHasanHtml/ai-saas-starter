<?php

/**
 * Zips a prepared Laravel app directory for upload to cPanel.
 * Usage: php make-zip.php <app-dir> <output.zip>
 *
 * Leaves out secrets, local data, caches and dev-only files, and makes sure
 * the writable folders Laravel needs exist in the archive (empty).
 */

[$script, $source, $target] = $argv + [null, null, null];
if (! $source || ! $target || ! is_dir($source)) {
    fwrite(STDERR, "Usage: php make-zip.php <app-dir> <output.zip>\n");
    exit(1);
}

$source = rtrim(realpath($source), '/');

// Relative paths (prefix match) that must never ship.
$exclude = [
    '.env',                 // real secrets live only on the server
    '.git',
    '.github',
    '.phpunit.cache',
    '.phpunit.result.cache',
    '.dockerignore',
    'Dockerfile',
    'docker',
    'node_modules',
    'tests',
    'phpunit.xml',
    'storage/logs/',
    'storage/framework/cache/data/',
    'storage/framework/sessions/',
    'storage/framework/views/',
    'storage/framework/testing/',
    'storage/app/private/',
    'storage/app/public/',
];
// .env.example and .env.production.example are fine to ship.
$allow = ['.env.example', '.env.production.example'];

// Writable folders Laravel expects to exist.
$emptyDirs = [
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];

@mkdir(dirname($target), 0777, true);
@unlink($target);

$zip = new ZipArchive;
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot write {$target}\n");
    exit(1);
}

$files = 0;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

foreach ($it as $path => $info) {
    $rel = substr($path, strlen($source) + 1);

    if (! in_array($rel, $allow, true)) {
        foreach ($exclude as $prefix) {
            if ($rel === rtrim($prefix, '/') || str_starts_with($rel, rtrim($prefix, '/').'/')) {
                continue 2;
            }
        }
    }

    if ($info->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($path, $rel);
        $files++;
    }
}

foreach ($emptyDirs as $dir) {
    $zip->addEmptyDir($dir);
    $zip->addFromString("{$dir}/.gitignore", "*\n!.gitignore\n");
}

$zip->close();

printf("Wrote %s (%d files, %.1f MB)\n", $target, $files, filesize($target) / 1048576);
