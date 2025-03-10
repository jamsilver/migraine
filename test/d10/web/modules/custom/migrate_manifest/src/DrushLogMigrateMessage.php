<?php

namespace Drupal\migrate_manifest;

use Drupal\migrate\MigrateMessageInterface;
use Drush\Drush;
use Psr\Log\LoggerInterface;

/**
 * Simple Migrate Message implementation that uses drush to output.
 *
 * @package Drupal\migrate_manifest
 */
class DrushLogMigrateMessage implements MigrateMessageInterface {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(LoggerInterface $logger = NULL) {
    if (!isset($logger)) {
      $logger = Drush::logger();
    }
    $this->logger = $logger;
  }

  /**
   * @inheritdoc
   */
  public function display($message, $type = 'status') {
    if (method_exists($this->logger, $type)) {
      $this->logger->$type($message);
    }
    elseif ($type == 'status') {
      $this->logger->info($message);
    }
    else {
      $this->logger->warning('Logger called with unknown type: @type', ['@type' => $type]);
      $this->logger->info($message);
    }
  }

}
