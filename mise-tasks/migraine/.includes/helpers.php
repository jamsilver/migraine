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

function _execGetJson(string $path, string $command, int|NULL &$resultCode = -1): array|string {
    $lines = _exec($path, $command, $resultCode);

    if ($resultCode) {
        return implode('', $lines);
    }

    return json_decode(implode('', $lines), TRUE);
}

function _exec(string $path, string $command, int|NULL &$resultCode = -1): array {

    $exitOnError = $resultCode === -1;
    $resultCode = NULL;

    $lastWorkingDirectory = getcwd();
    chdir($path);
    $result = exec($command, $outputLines, $resultCode);
    chdir($lastWorkingDirectory);

    if ($resultCode && $exitOnError) {
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
            $success = $success && rmdir($path);
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

function _move_migraine_scripts_to_site(string $drupalRoot) {
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

function _validateMigrations(array $migrations, array $sourceTypes, array $destTypes): bool {
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
                "migrations.json error: Migration %s source '%s.%s' does not match any type found in .migraine/source/types.json.",
                $id, $sourceType, $sourceBundle,
            );
            $success = FALSE;
        }

        if (!is_string($destType) || !is_string($destBundle) || !isset($destTypes[$destType][$destBundle])) {
            _error(
                "migrations.json error: Migration %s destination '%s.%s' does not match any type found in .migraine/dest/types.json.",
                $id, $destType, $destBundle,
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

function _drupal_major_versions_match(string $version1, string $version2) {
    [$major1] = explode('.', $version1, 1);
    [$major2] = explode('.', $version1, 1);
    return $major1 !== NULL && $major1 === $major2;
}

function _format_time_diff_since($timestamp, $granularity = 2) {
    return _format_date_diff($timestamp, time(), $granularity);
}

/**
 * Adapted from Drupal\Core\Datetime\DateFormatter::formatDiff().
 */
function _format_date_diff($from, $to, $granularity = 2): string {
    if ($from > $to) {
        return '0 seconds';
    }

    $date_time_from = new \DateTime();
    $date_time_from->setTimestamp($from);

    $date_time_to = new \DateTime();
    $date_time_to->setTimestamp($to);

    $interval = $date_time_to->diff($date_time_from);

    $output = '';

    // We loop over the keys provided by \DateInterval explicitly. Since we
    // don't take the "invert" property into account, the resulting output value
    // will always be positive.
    $max_age = 1e99;
    foreach (['y', 'm', 'd', 'h', 'i', 's'] as $value) {
        if ($interval->$value > 0) {
            switch ($value) {
                case 'y':
                    $interval_output = $interval->y . ($interval->y === 1 ? ' year' : ' years');
                    $max_age = min($max_age, 365 * 86400);
                    break;

                case 'm':
                    $interval_output = $interval->m . ($interval->m === 1 ? ' month' : ' months');
                    $max_age = min($max_age, 30 * 86400);
                    break;

                case 'd':
                    // \DateInterval doesn't support weeks, so we need to calculate them
                    // ourselves.
                    $interval_output = '';
                    $days = $interval->d;
                    $weeks = floor($days / 7);
                    if ($weeks) {
                        $interval_output = $weeks . ($weeks == 1 ? ' week' : ' weeks');
                        $days -= $weeks * 7;
                        $granularity--;
                        $max_age = min($max_age, 7 * 86400);
                    }

                    if ((!$output || $weeks > 0) && $granularity > 0 && $days > 0) {
                        $interval_output = ($interval_output ? ' ' : '') . $days . ($days == 1 ? ' day' : ' days');
                        $max_age = min($max_age, 86400);
                    }
                    else {
                        // If we did not output days, set the granularity to 0 so that we
                        // will not output hours and get things like "@count week @count hour".
                        $granularity = 0;
                    }
                    break;

                case 'h':
                    $interval_output = $interval->h . ($interval->h == 1 ? ' hour' : ' hours');
                    $max_age = min($max_age, 3600);
                    break;

                case 'i':
                    $interval_output = $interval->i . ($interval->i == 1 ? ' minute' : ' minutes');
                    $max_age = min($max_age, 60);
                    break;

                case 's':
                    $interval_output = $interval->s . ($interval->s == 1 ? ' second' : ' seconds');
                    $max_age = min($max_age, 1);
                    break;
            }
            $output .= ($output && $interval_output ? ' ' : '') . $interval_output;
            $granularity--;
        }
        elseif ($output) {
            // Break if there was previous output but not any output at this level,
            // to avoid skipping levels and getting output like "@count year @count second".
            break;
        }

        if ($granularity <= 0) {
            break;
        }
    }

    return empty($output) ? '0 seconds' : $output;
}
