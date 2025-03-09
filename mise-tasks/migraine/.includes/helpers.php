<?php

namespace Migraine;

/**
 * @file
 * Helpers mainly copied from drush 8.
 */

function _log(string $message, ...$values) {
    fprintf(STDERR, "$message\n", ...$values);
}

function _error(string $message, ...$values) {
    fprintf(STDERR, "$message\n", ...$values);
}

function _exec(string $path, string $command): array {
    //_error('(cd "%s"; %s)', $path, $command);
    chdir($path);
    $result = exec($command, $outputLines, $resultCode);
    if ($resultCode) {
        _error("Command failed with output %s.", $result);
        exit(1);
    }
    return $outputLines;
}

function _rmrf(string $path, bool $contentsOnly = FALSE): bool {
    if (empty($path) || $path === '/') {
        _error("Attempted to delete root!!");
        return FALSE;
    }

    $success = FALSE;

    if (is_dir($path)) {
        $success = TRUE;
        foreach (glob($path . '/*') as $file) {
            $success = $success && _rmrf($file);
        }
        if (!$contentsOnly) {
            $success = $success && unlink($path);
        }
    } elseif (is_file($path)) {
        $success = unlink($path);
    }

    return $success;
}

function _mkdir($path) {
    if (!is_dir($path)) {
        if (_mkdir(dirname($path))) {
            if (@mkdir($path)) {
                return TRUE;
            }
            elseif (is_dir($path) && is_writable($path)) {
                // The directory was created by a concurrent process.
                return TRUE;
            }
            else {
                return FALSE;
            }
        }
        return FALSE;
    }
    else {
        if (!is_writable($path)) {
            return FALSE;
        }
        return TRUE;
    }
}

function _copy($src, $dest): bool {
    // all subdirectories and contents:
    if(is_dir($src)) {
        if (!_mkdir($dest, TRUE)) {
            return FALSE;
        }
        $dir_handle = opendir($src);
        while($file = readdir($dir_handle)) {
            if ($file != "." && $file != "..") {
                if (_copy("$src/$file", "$dest/$file") !== TRUE) {
                    return FALSE;
                }
            }
        }
        closedir($dir_handle);
    }
    elseif (is_link($src)) {
        symlink(readlink($src), $dest);
    }
    elseif (!copy($src, $dest)) {
        return FALSE;
    }

    // Preserve file modification time.
    touch($dest, filemtime($src));

    // Preserve execute permission.
    if (!is_link($src) && !_is_windows()) {
        // Get execute bits of $src.
        $execperms = fileperms($src) & 0111;
        // Apply execute permissions if any.
        if ($execperms > 0) {
            $perms = fileperms($dest) | $execperms;
            chmod($dest, $perms);
        }
    }

    return TRUE;
}

function _is_windows($os = NULL): bool {
    if (!$os || $os == "LOCAL") {
        $os = PHP_OS;
    }
    return strtoupper(substr($os, 0, 3)) === 'WIN';
}

function _find_drupal_root(string $path, string $type) {
    foreach ([$path, "$path/web", "$path/docroot", "$path/www"] as $candidateWebroot) {
        if (_is_drupal_root($candidateWebroot, $type)) {
            return $candidateWebroot;
        }
    }
    return FALSE;
}

function _is_drupal_root(string $path, string $type) {
    $has_index_php = is_dir($path) && is_file("$path/index.php");
    switch ($type) {
        case 'd10':
            return $has_index_php && is_dir("$path/core");
        case 'd7':
            return $has_index_php && !is_dir("$path/core");
        default:
            throw new \Exception(sprintf('Unexpected drupal type %s', $type));
    }
}

function _make_path_resolver(string $projectRoot) {
    return function ($path, $message, $autocreate = FALSE) use ($projectRoot) {
        if ($path[0] !== DIRECTORY_SEPARATOR) {
            $path = $projectRoot . DIRECTORY_SEPARATOR . $path;
        }
        if (!is_dir($path) && $autocreate) {
            _mkdir($path);
        }
        if (!is_dir($path)) {
            _error($message, $path);
            exit(1);
        }
        return rtrim(realpath($path), '/');
    };
}

function _move_migraine_scripts_to_site(string $drupalRoot, string $type) {
    $drupalRoot = _find_drupal_root($drupalRoot, $type);

    $drushScriptsPath = "$drupalRoot/.migraine-scripts";

    if (!is_dir($drushScriptsPath) && !_mkdir($drushScriptsPath)) {
        _error("Could not find, or create, .migraine-scripts directory at %s", $drushScriptsPath);
        exit(1);
    }

    file_put_contents("$drushScriptsPath/.gitignore", "*");

    foreach (glob(__DIR__ . '/migraine-scripts/*.php') as $migraineScriptPathSource) {
        $migraineScriptPathDest = $drushScriptsPath . '/' . basename($migraineScriptPathSource);

        if (!_copy($migraineScriptPathSource, $migraineScriptPathDest)) {
            _error("Could not move %s into .migraine-scripts directory at %s", basename($migraineScriptPathSource), $drushScriptsPath);
            exit(1);
        }
    }
}

function _move_migraine_files_to_output_dir(string $sourceGlob, string $destDir) {

    if (!is_dir($destDir) && !_mkdir($destDir)) {
        _error("Could not find, or create, .migraine directory at %s", $destDir);
        exit(1);
    }

    foreach (glob($sourceGlob) as $migrainePathSource) {
        $migrainePathDest = $destDir . '/' . basename($migrainePathSource);

        if (!is_file($migrainePathDest) && !_copy($migrainePathSource, $migrainePathDest)) {
            _error("Could not move %s into .migraine directory at %s", basename($migrainePathSource), $destDir);
            exit(1);
        }
    }
}

function _validateMigrations(array $migrations, string $sourceEnvType, array $sourceTypes, string $destEnvType, array $destTypes) {
    $success = TRUE;
    foreach ($migrations as $id => $row) {
        if (!is_array($row) || count($row) !== 4) {
            _error("migrations.json error: Migration %s value is not an array having 4 items.", $id);
            $success = FALSE;
            continue;
        }

        [$sourceType, $sourceBundle, $destType, $destBundle] = $row;

        if (!is_string($sourceType) || !is_string($sourceBundle) || !isset($sourceTypes[$sourceType][$sourceBundle])) {
            _error(
                "migrations.json error: Migration %s source '%s.%s' does not match any type found in .migraine/%s/types.json.",
                $id, $sourceType, $sourceBundle, $sourceEnvType,
            );
            $success = FALSE;
        }

        if (!is_string($destType) || !is_string($destBundle) || !isset($destTypes[$destType][$destBundle])) {
            _error(
                "migrations.json error: Migration %s destination '%s.%s' does not match any type found in .migraine/%s/types.json.",
                $id, $destType, $destBundle, $destEnvType,
            );
            $success = FALSE;
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $id)) {
            _error(
                "migrations.json error: Migration '%s' ID has characters unsuitable for a migration ID. Alphanumeric with underscores and hyphens only please.",
                $id,
            );
            $success = FALSE;
        }
    }
    return $success;
}
