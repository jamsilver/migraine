<?php

namespace MigraineComposerScripts;

use Composer\Script\Event;
use function Migraine\_copy;
use function Migraine\_rmrf;

final class ComposerScripts {
  public static function copyVendor(Event $event) {
    $migraineTasksDir = realpath(join(DIRECTORY_SEPARATOR, [__DIR__, '..', 'mise-tasks', 'migraine']));
    $helpersPath = realpath(join(DIRECTORY_SEPARATOR, [$migraineTasksDir, '.includes', 'helpers.php']));

    $sourceVendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
    $destVendorDir = join(DIRECTORY_SEPARATOR, [$migraineTasksDir, '.includes', 'vendor']);

    if (!$migraineTasksDir || !$sourceVendorDir || !$helpersPath) {
      fprintf(STDERR, "Error finding directory one of these directories:\n");
      fprintf(STDERR, "  migraineTasksDir: %s\n", $migraineTasksDir);
      fprintf(STDERR, "  sourceVendorDir: %s\n", $sourceVendorDir);
      fprintf(STDERR, "  helpersPath: %s\n", $helpersPath);
      exit(1);
    }

    require_once $helpersPath;

    if (is_dir($destVendorDir) && !_rmrf($destVendorDir)) {
      fprintf(STDERR, "Error deleting directory: %s\n", $destVendorDir);
      exit(1);
    }

    if (!_copy($sourceVendorDir, $destVendorDir)) {
      fprintf(STDERR, "Failed copying vendor directory into migraine\n");
      exit(1);
    }

    fprintf(STDERR, "Successfully copied vendor into %s\n", $destVendorDir);

    $vendorSubPathsToRemove = [
      ['bin'],
      //['composer'],
    ];

    foreach ($vendorSubPathsToRemove as $vendorSubPathToRemove) {
      $pathToRemove = realpath(join(DIRECTORY_SEPARATOR, array_merge([$destVendorDir], $vendorSubPathToRemove)));

      if (!$pathToRemove) {
        continue;
      }

      if (!_rmrf($pathToRemove)) {
        fprintf(STDERR, "Failed removing vendor sub-path %s\n", $pathToRemove);
        exit(1);
      }

      fprintf(STDERR, "Deleted vendor sub-path %s\n", $pathToRemove);
    }
  }
}
