<?php

namespace Drupal\mapam_programmation\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MapamProgrammationUniqueVolunteerNeed constraint.
 *
 * The dates of a session are copied to its volunteer need: two sessions
 * sharing a timeslot would overwrite each other.
 */
class UniqueVolunteerNeedConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    $session = $items->getEntity();
    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($items as $delta => $item) {
      if (empty($item->target_id)) {
        continue;
      }
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $session->bundle())
        ->condition($items->getName(), $item->target_id)
        ->range(0, 1);
      if (!$session->isNew()) {
        $query->condition('nid', $session->id(), '<>');
      }
      $ids = $query->execute();
      if ($ids && ($other = $storage->load(reset($ids)))) {
        $this->context->buildViolation($constraint->message, [
          '%timeslot' => $item->entity ? $item->entity->label() : $item->target_id,
          '%session' => $other->label(),
        ])
          ->atPath((string) $delta)
          ->addViolation();
      }
    }
  }

}
