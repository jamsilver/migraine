<?php

namespace Migraine;

/**
 * Represents and reads and writes .migraine/config.yml data.
 */
final class Config extends ConfigBase {

  protected static function makeFileName(string $directoryPath): string {
    return join(
      DIRECTORY_SEPARATOR,
      [$directoryPath, 'config.yml'],
    );
  }

  protected static function defaultConfig(): string {
    return <<<YAML
    version: '1.0'
    YAML;
  }

}
