<?php

namespace Drupal\date_recur\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks the time zone is a recognized zone.
 *
 * @Constraint(
 *   id = "DateRecurTimeZone",
 *   label = @Translation("Valid Time Zone", context = "Validation"),
 *   type = "string"
 * )
 */
class DateRecurTimeZoneConstraint extends Constraint {

  public $invalidTimeZone = '%value is not a valid time zone.';

}
