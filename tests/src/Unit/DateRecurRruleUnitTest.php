<?php

namespace Drupal\Tests\date_recur\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\date_recur\DateRecurRRule;
use Drupal\Tests\UnitTestCase;

/**
 * Date recur tests.
 *
 * @coversDefaultClass \Drupal\date_recur\DateRecurRRule
 * @group date_recur
 */
class DateRecurRruleUnitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // DrupalDateTime wants to access the language manager.
    $languageManager = $this->getMockForAbstractClass(LanguageManagerInterface::class);
    $languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->will($this->returnValue(new Language(['id' => 'en'])));

    $container = new ContainerBuilder();
    $container->set('language_manager', $languageManager);
    \Drupal::setContainer($container);
  }

  /**
   * Test timezone.
   *
   * @param \DateTimeZone $tz
   *   A timezone for testing.
   *
   * @dataProvider providerTimezone
   */
  public function testTz(\DateTimeZone $tz) {
    $start = new \DateTime('11pm 7 June 2005', $tz);

    $rrule = 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;INTERVAL=1';

    $rule = new DateRecurRRule(
      $rrule,
      $start
    );

    $results = $rule->getOccurrences(
      $start,
      NULL,
      1
    );

    // The first result must be the same as the first day.
    // \DateTime objects are comparable in PHP.
    $this->assertTrue($start == $results[0]["value"]);
  }

  /**
   * Data provider for ::testTz.
   */
  public function providerTimezone() {
    $data[] = [new \DateTimeZone('America/Los_Angeles')];
    $data[] = [new \DateTimeZone('UTC')];
    $data[] = [new \DateTimeZone('Australia/Sydney')];
    return $data;
  }

}
