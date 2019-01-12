<?php

namespace Drupal\date_recur\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates RRULE strings.
 *
 * @Constraint(
 *   id = "DateRecurRrule",
 *   label = @Translation("Validates RRULEs", context = "Validation"),
 * )
 */
class DateRecurRruleConstraint extends Constraint {

  public $invalidRrule = 'Invalid RRULE.';

}