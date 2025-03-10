<?php

namespace Migraine;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

abstract class ConfigBase {

  private static array $instances = [];

  private bool $changed = FALSE;

  private function __construct(
    protected array $data = [],
  ) { }

  public static function forDirectory(string $directoryPath): Config {
    $key = realpath($directoryPath);

    if (($key === FALSE && !_mkdir($directoryPath)) || !($key = realpath($directoryPath))) {
      fprintf(STDERR, "Could not create %s. Check permissions.\n", $directoryPath);
      exit(1);
    }

    if (isset(static::$instances[$key])) {
      return static::$instances[$key];
    }

    static::$instances[$key] = Config::createFromDirectory($directoryPath);

    register_shutdown_function([static::$instances[$key], 'writeIfChanged'], $directoryPath);

    return static::$instances[$key];
  }

  private static function createFromDirectory(string $directoryPath) {
    $file = static::makeFileName($directoryPath);

    if (!is_file($file)) {
      fprintf(STDERR, "Creating %s\n", $file);
      if (!file_put_contents($file, static::defaultConfig())) {
        fprintf(STDERR, "Failed to write to %s\n", $file);
        exit(1);
      }
    }

    $raw = file_get_contents($file);

    if ($raw === FALSE) {
      fprintf(STDERR, "Failed to read %s. Check file permissions.\n", $file);
      exit(1);
    }

    try {
      $data = (new Parser())->parse(
        $raw,
        Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_CUSTOM_TAGS
      );
    }
    catch (\Exception $e) {
      fprintf(STDERR, "Config file %s contains invalid Yaml. Please fix: %s\n", $file, $e->getMessage());
      exit(1);
    }

    return new static($data);
  }

  public function writeIfChanged(string $directoryPath) {
    if (!$this->changed) {
      return;
    }

    $this->write($directoryPath);
  }

  public function write(string $directoryPath): void {
    $file = self::makeFileName($directoryPath);

    try {
      // Same settings as Drupal for consistency with migration ymls.
      $raw = (new Dumper(2))->dump($this->data, PHP_INT_MAX, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
    catch (\Exception $e) {
      fprintf(STDERR, "Unexpected error encoding %s yaml: %s.\n", $file, $e->getMessage());
      exit(1);
    }

    if (!file_put_contents($file, $raw)) {
      fprintf(STDERR, "Failed to write %s. Check file permissions.\n", $file);
    }
  }

  public function get(string $key, mixed $default = NULL): mixed {
    return $this->data[$key] ?? $default;
  }

  public function set(string $key, mixed $value): self {
    $this->changed = TRUE;
    $this->data[$key] = $value;
    return $this;
  }

  abstract protected static function makeFileName(string $directoryPath): string;

  abstract protected static function defaultConfig(): string;

  protected function validate(): void { }
}
