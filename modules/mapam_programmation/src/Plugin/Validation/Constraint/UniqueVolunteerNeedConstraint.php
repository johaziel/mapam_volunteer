<?php

namespace Drupal\mapam_programmation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that a timeslot is the volunteer need of one session only.
 */
#[Constraint(
  id: 'MapamProgrammationUniqueVolunteerNeed',
  label: new TranslatableMarkup('Volunteer need of one session only', [], ['context' => 'Validation']),
)]
class UniqueVolunteerNeedConstraint extends SymfonyConstraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public $message = 'The timeslot %timeslot is already the volunteer need of %session.';

}
