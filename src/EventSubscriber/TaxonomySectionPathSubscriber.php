<?php

namespace Drupal\taxonomy_section_paths\EventSubscriber;

use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy_section_paths\Contract\Service\ProcessorServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\AliasActionsServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\NodeChangeDetectorInterface;
use Drupal\taxonomy_section_paths\Contract\Service\TermChangeDetectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EventSubscriber\EntityEventSubscriber;
use Drupal\taxonomy_section_paths\Contract\Service\RequestContextStoreServiceInterface;
use Drupal\taxonomy_section_paths\Helper\EntityHelper;

/**
 * Suscriptor de eventos para generar alias de términos de taxonomía y nodos.
 */
class TaxonomySectionPathSubscriber extends EntityEventSubscriber {

  public function __construct(
    protected ProcessorServiceInterface $processor,
    protected NodeChangeDetectorInterface $nodeChangeDetector,
    protected TermChangeDetectorInterface $termChangeDetector,
    protected AliasActionsServiceInterface $aliasActions,
    protected ConfigFactoryInterface $configFactory,
    protected RequestContextStoreServiceInterface $contextService,
  ) {}

  /**
   * Reacts to entity insertion.
   *
   * @param \Drupal\entity_events\Event\EntityEvent $event
   *   The event that triggers the action.
   */
  public function onEntityInsert(EntityEvent $event): void {
    $entity = $event->getEntity();

    if ($entity instanceof TermInterface) {
      if ($this->termChangeDetector->needsAliasUpdate($entity, FALSE)) {
        $this->processor->setTermAlias($entity, FALSE);
      }
    }
    elseif ($entity instanceof NodeInterface) {
      if ($this->nodeChangeDetector->needsAliasUpdate($entity, FALSE)) {
        $this->processor->setNodeAlias($entity, FALSE);
      }
    }
  }

  /**
   * Reacts to entity update.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    $entity = $event->getEntity();

    if ($entity instanceof TermInterface) {
      $entity_original = EntityHelper::getSecureOriginalEntity($entity);
      if ($entity->label() === $entity_original->label()) {
        return;
      }

      if ($this->termChangeDetector->needsAliasUpdate($entity, TRUE)) {
        $this->processor->setTermAlias($entity, TRUE);
      }

    }
    elseif ($entity instanceof NodeInterface) {

      if ($this->nodeChangeDetector->needsAliasUpdate($entity, TRUE)) {
        $this->processor->setNodeAlias($entity, TRUE);
      }

    }
  }

  /**
   * Reacts to entity predelete.
   */
  public function onEntityPredelete(EntityEvent $event): void {
    $continue = FALSE;
    $entity = $event->getEntity();
    $entity_id = $entity->id();
    if ($entity instanceof TermInterface) {
      if ($this->termChangeDetector->needsAliasUpdate($entity, FALSE)) {
        $path = '/taxonomy/term/' . $entity_id;
        $continue = TRUE;
      }
    }
    elseif ($entity instanceof NodeInterface) {
      if ($this->nodeChangeDetector->needsAliasUpdate($entity, TRUE)) {
        $path = '/node/' . $entity_id;
        $continue = TRUE;
      }
    }
    if ($continue) {
      $langcode = $entity->language()->getId();
      $old_alias = $this->aliasActions->getOldAlias($path, $langcode) ?? NULL;
      $this->contextService->set(RequestContextStoreServiceInterface::GROUP_INPUT, $entity_id, [
        'original' => $entity,
        'old_alias' => $old_alias,
      ]);
    }
  }

  /**
   * Reacts to entity delete.
   */
  public function onEntityDelete(EntityEvent $event): void {
    $entity = $event->getEntity();
    $entity_id = $entity->id();
    if ($entity instanceof TermInterface) {
      if ($this->termChangeDetector->needsAliasUpdate($entity, FALSE)) {
        $this->processor->deleteTermAlias($entity);
      }
    }
  }

}
