<?php

namespace Migraine;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides helpers for locating and testing facts about a Drupal root.
 *
 * Parts of this are adapted from webflo/drupal-finder.
 *
 * @see https://github.com/webflo/drupal-finder/blob/master/src/DrupalFinder.php
 */
final class DrupalRoot {

  /**
   * Drupal web public directory.
   *
   * @var string
   */
  private readonly string $drupalRoot;

  /**
   * The major Drupal version number of the found root.
   *
   * @var int
   */
  private readonly int $majorDrupalVersion;

  /**
   * Initialize Drupal Root.
   *
   * @param string $startPath
   *   The path to start looking for a Drupal root.
   */
  public function __construct(string $startPath) {
    [$drupalRoot, $version] = $this->findRootAndVersion($startPath);

    if (!isset($drupalRoot) || !isset($version)) {
      throw new \Exception('Could not find Drupal root at ' . $startPath);
    }

    $this->drupalRoot = $drupalRoot;
    $this->majorDrupalVersion = $version;
  }

  /**
   * Get the Drupal root.
   *
   * @return string
   *   The path to the Drupal root.
   */
  public function getDrupalRoot(): string
  {
    return $this->drupalRoot;
  }

  /**
   * Get the detected version of Drupal in use at the root.
   *
   * @return int
   *   The major Drupal version number.
   */
  public function getMajorDrupalVersion(): int {
    return $this->majorDrupalVersion;
  }

  private function findRootAndVersion(string $startPath): array {
    foreach (array(TRUE, FALSE) as $followSymlinks) {
      foreach (self::yieldCandidatePaths($startPath) as $path) {
        if ($followSymlinks && is_link($path)) {
          $path = realpath($path);
        }

        [$drupalRoot, $version] = $this->checkForRootAndVersion($path);

        if (isset($drupalRoot)) {
          return [$drupalRoot, $version];
        }
      }
    }

    return [NULL, NULL];
  }

  private function yieldCandidatePaths(string $startPath): \Generator {
    $startPathPrefix = rtrim($startPath, '/\\') . DIRECTORY_SEPARATOR;

    $literalCandidates = [
      $startPathPrefix,
      $startPathPrefix . 'web',
      $startPathPrefix . 'public',
      $startPathPrefix . 'www',
      $startPathPrefix . 'docroot',
      $startPathPrefix . 'webroot',
    ];

    foreach ($literalCandidates as $literalCandidate) {
      // Try literal candidate sub-path.
      yield $literalCandidate;

      $literalCandidatePrefix = $literalCandidate . DIRECTORY_SEPARATOR;

      // Try looking in candidate sub-path for a DDEV root.
      $ddev = $literalCandidatePrefix . '.ddev';
      if (is_dir($ddev)) {
        foreach (glob("$literalCandidate/*.yaml") as $ddevYamlPath) {
          try {
            if (!($raw = file_get_contents($ddevYamlPath))) {
              continue;
            }

            $data = Yaml::parse($raw);

            if (empty($data['docroot']) || !is_string($data['docroot'])) {
              continue;
            }

            yield Path::isRelative($data['docroot'])
              ? Path::canonicalize($literalCandidatePrefix . $data['docroot'])
              : $data['docroot'];
          }
          catch (\Exception) {
          }
        }
      }

      // Try looking in candidate sub-path for a Lando root.
      // .lando.*.yml.config.webroot
      $landoYamlPaths = glob("$literalCandidate/.lando.*.yml");
      if (!empty($landoYamlPaths)) {
        foreach ($landoYamlPaths as $landoYamlPath) {
          try {
            if (!($raw = file_get_contents($landoYamlPath))) {
              continue;
            }

            $data = Yaml::parse($raw);

            if (empty($data['config']['docroot']) || !is_string($data['config']['docroot'])) {
              continue;
            }

            yield Path::isRelative($data['config']['docroot'])
              ? Path::canonicalize($literalCandidatePrefix . $data['config']['docroot'])
              : $data['config']['docroot'];
          }
          catch (\Exception) {
          }
        }
      }
    }
  }

  private function checkForRootAndVersion(string $path): array {
    if (empty($path) || !is_dir($path)) {
      return [NULL, NULL];
    }

    $path = $this->getDrupalRootFromComposerInstallerPath($path) ?? $path;

    // All versions of Drupal have this.
    if (!file_exists($path . '/index.php')) {
      return [NULL, NULL];
    }

    // Drupal >= 8.
    if (file_exists($path . '/core/core.services.yml') && file_exists($path . '/core/lib/Drupal.php')) {
      $drupalClassContents = file_get_contents($path . '/core/lib/Drupal.php');

      if (!$drupalClassContents
        || !preg_match('/\n *const VERSION ?= ?[\'"]([0-9.a-z-]+)[\'"];\n/', $drupalClassContents, $matches)
        || !($majorVersionNumber = (int) explode('.', $matches[1], 1)[0])
        || $majorVersionNumber < 8
      ) {
        fprintf(STDERR, "Could not distinguish Drupal 8+ version, error parsing version number from: %s\n", $path . '/core/lib/Drupal.php');
        return [NULL, NULL];
      }

      return [$path, $majorVersionNumber];
    }

    if (!file_exists($path . '/includes/bootstrap.inc')) {
      return [NULL, NULL];
    }

    // Drupal <= 7.
    $bootstrapContents = file_get_contents($path . '/includes/bootstrap.inc');

    if (!$bootstrapContents) {
      fprintf(STDERR, "Could not distinguish Drupal 6/7 root: error reading %s\n", $path . '/includes/bootstrap.inc');
      return [NULL, NULL];
    }

    if (str_contains($bootstrapContents, "define('VERSION', '7.")) {
      return [$path, 7];
    }

    if (str_contains($bootstrapContents, 'drupal')) {
      return [$path, 6];
    }

    return [NULL, NULL];
  }

  private function getDrupalRootFromComposerInstallerPath(string $path): ?string {
    $composerFilePath = $path . '/' . $this->getComposerFileName();

    if (!file_exists($composerFilePath)) {
      return NULL;
    }

    $json = json_decode($composerFilePath, TRUE);

    if (is_null($json)) {
      return NULL;
    }

    if (!is_array($json) || !isset($json['extra']['installer-paths']) || !is_array($json['extra']['installer-paths'])) {
      return NULL;
    }

    foreach ($json['extra']['installer-paths'] as $install_path => $items) {
      if (in_array('type:drupal-core', $items) ||
        in_array('drupal/core', $items) ||
        in_array('drupal/drupal', $items)) {
        if (($install_path == 'core') || ((isset($json['name'])) && ($json['name'] == 'drupal/drupal'))) {
          $install_path = '';
        } elseif (substr($install_path, -5) == '/core') {
          $install_path = substr($install_path, 0, -5);
        }
        return rtrim($path . '/' . $install_path, '/');
      }
    }

    return NULL;
  }

  private function getComposerFileName()
  {
    return trim(getenv('COMPOSER')) ?: 'composer.json';
  }

}
