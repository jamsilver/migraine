<?php

namespace Migraine;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

abstract class ConfigBase {

    const string MIGRAINE_DIRECTORY = '.migraine';

    private static array $instances = [];

    private bool $changed = FALSE;

    private function __construct(
        public readonly string $projectRoot,
        protected array $data = [],
    ) {}

    /**
     * Return an instance of config appropriate for the given directory.
     *
     * If $directoryPath is inside a git repository, this method uses the root
     * of that git repository instead. This config is backed by a
     * .migraine/config.yml file in the resolved directory. It is automatically
     * created if it does not exist.
     *
     * The purpose of all the singleton/static caching in this method is to
     * offer a high degree of protection from finding ourselves in a situation
     * where there are two instances reading/writing to the same underlying
     * file.
     *
     * @param string $directoryPath
     *
     * @return \Migraine\Config
     */
    public static function forDirectory(string $directoryPath): Config {
        $realDirectoryPath = realpath($directoryPath);

        $singletonKeys = [$directoryPath, $realDirectoryPath];

        foreach ($singletonKeys as $singletonKey) {
            if (isset(static::$instances[$singletonKey])) {
                return static::$instances[$singletonKey];
            }
        }

        // If we're nested inside a git repo, use the root of it.
        $gitRootPath = trim(implode(_exec($directoryPath, 'git rev-parse --show-toplevel', $resultCode)));

        if (!$resultCode && is_dir($gitRootPath) && $gitRootPath !== '/') {
            foreach ([$gitRootPath, realpath($gitRootPath)] as $newSingletonKey) {
                if (!$newSingletonKey) continue;

                if (isset(static::$instances[$newSingletonKey])) {
                    return static::$instances[$newSingletonKey];
                }

                $singletonKeys[] = $newSingletonKey;
            }
            $directoryPath = $gitRootPath;
        }

        $migraineFolder = $directoryPath . DIRECTORY_SEPARATOR . static::MIGRAINE_DIRECTORY;
        $realMigraineFolder = realpath($migraineFolder);

        if (($realMigraineFolder === FALSE && !_mkdir($migraineFolder)) || !($realMigraineFolder = realpath($migraineFolder))) {
            fprintf(STDERR, "Could not create %s. Check permissions.\n", $migraineFolder);
            exit(1);
        }

        $instance = Config::createFromDirectory($realMigraineFolder, $directoryPath);

        // Let's do a magic write on shutdown so callers don't need to care.
        register_shutdown_function([
            $instance,
            'writeIfChanged',
        ], $migraineFolder);

        foreach ($singletonKeys as $singletonKey) {
            static::$instances[$singletonKey] = $instance;
        }

        _log('Using migraine configuration at %s', $realMigraineFolder);

        return $instance;
    }

    private static function createFromDirectory(string $directoryPath, string $projectRoot) {
        $file = static::makeFilePath($directoryPath);

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

        return new static($projectRoot, $data);
    }

    public function writeIfChanged(string $directoryPath) {
        if (!$this->changed) {
            return;
        }

        $this->write($directoryPath);
    }

    public function write(string $directoryPath): void {
        $file = static::makeFilePath($directoryPath);

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

    public function set(string $key, mixed $value): static {
        $this->changed = TRUE;
        $this->data[$key] = $value;
        return $this;
    }

    abstract protected static function makeFilePath(string $directoryPath): string;

    abstract protected static function defaultConfig(): string;

    protected function validate(): void {}

}
