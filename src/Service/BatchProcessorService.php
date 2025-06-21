<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy_section_paths\Contract\RelatedNodesServiceInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Servicio para crear y ejecutar operaciones en batch.
 */
class BatchProcessorService {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RelatedNodesServiceInterface $relatedNodes,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Añade términos a una cola batch para actualizar sus nodos relacionados.
   *
   * @param string $action
   *   Action to do: update or delete.
   * @param array $terms
   *   Terms array to process.
   */
  public function queueTermsForNodeUpdate(string $action, array $terms): void {
    if (empty($terms)) {
      return;
    }

    $batch = (new BatchBuilder())
      ->setTitle($this->t('Updating related nodes for taxonomy terms...'))
      ->setInitMessage($this->t('Starting term-based node update...'))
      ->setProgressMessage($this->t('Processing @current of @total taxonomy terms...'))
      ->setErrorMessage($this->t('An error occurred during term-based node update.'));

    foreach ($terms as $term) {
      $batch->addOperation([self::class, 'processTerm'], [$action, $term]);
    }

    batch_set($batch->toArray());
  }

  /**
   * Callback del batch para aplicar la actualización a un término.
   *
   * @param string $action
   *   Action to do.
   * @param \Drupal\taxonomy\TermInterface $term
   *   Term to process.
   * @param array|\ArrayAccess $context
   *   Batch context.
   */
  public static function processTerm(string $action, TermInterface $term, array &$context): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $related_nodes = \Drupal::service('taxonomy_section_paths.related_nodes');

    if ($term instanceof TermInterface) {
      $related_nodes->applyToRelatedNodes($action, $term);
    }
  }

}
