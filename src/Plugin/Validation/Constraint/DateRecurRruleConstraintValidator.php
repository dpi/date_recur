<?php

namespace Drupal\date_recur\Plugin\Validation\Constraint;

use Drupal\date_recur\DateRecurHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DateRecurRruleConstraint constraint.
 */
class DateRecurRruleConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\date_recur\Plugin\Validation\Constraint\DateRecurRruleConstraint $constraint */
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $value */

    // Validator do not apply to field values without RRULE.
    if (empty($value->rrule)) {
      return;
    }

    try {
      // Use a fake start time as there may be an empty or invalid start date.
      DateRecurHelper::create($value->rrule, new \DateTime());
    }
    catch (\Exception $e) {
      $this->context->addViolation($constraint->invalidRrule);
    }
  }

}