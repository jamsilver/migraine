<?php

namespace Drupal\Tests\migrate_manifest\Unit;

use Drupal\migrate_manifest\DrushLogMigrateMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ColinODell\PsrTestLogger\TestLogger;

/**
 * Class DrushLogMigrateMessageTest
 *
 * @coversDefaultClass \Drupal\migrate_manifest\DrushLogMigrateMessage
 */
class DrushLogMigrateMessageTest extends TestCase {

  /**
   * @covers ::display
   */
  public function testDisplay() {
    $logger = new TestLogger();
    $migrate_message = new DrushLogMigrateMessage($logger);
    $migrate_message->display('my message');
    $this->assertTrue($logger->hasRecord('my message', 'info'), 'status logged correctly');

    $migrate_message->display('my error', 'error');
    $this->assertTrue($logger->hasRecord('my error', 'error'), 'error logged correctly');

    $migrate_message->display('my broken message', 'broken');
    $this->assertTrue($logger->hasRecord('my broken message', 'info'), 'broken type handled');
    $this->assertTrue($logger->hasRecord([
      'message' => 'Logger called with unknown type: @type',
      'context' => ['@type' => 'broken'],
    ], 'warning'), 'broken warning logged.');
  }

}
