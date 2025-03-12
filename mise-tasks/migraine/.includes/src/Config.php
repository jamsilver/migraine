<?php

namespace Migraine;

/**
 * Represents and reads and writes .migraine/migraine.yml data.
 */
final class Config extends ConfigBase {

  protected static function makeFilePath(string $directoryPath): string {
    return join(
      DIRECTORY_SEPARATOR,
      [$directoryPath, 'migraine.yml'],
    );
  }

  protected static function defaultConfig(): string {
    return <<<YAML
    version: '1.0'
    YAML;
  }

}
