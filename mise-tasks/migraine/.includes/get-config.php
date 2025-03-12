<?php

namespace Migraine;

require_once __DIR__ . "/vendor/autoload.php";

if (empty($argv) || !isset($argv[1]) || empty($argv[1])) {
    exit(1);
}

$config = Config::forDirectory(getcwd());
$return = $config->get($argv[1]);

array_shift($argv);
array_shift($argv);

while ($key = array_shift($argv)) {
    if (!is_array($return) || !array_key_exists($key, $return)) {
        exit(1);
    }

    $return = $return[$key];
}

print $return;
exit;
