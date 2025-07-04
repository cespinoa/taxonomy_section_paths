<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy_section_paths\Contract\Service\RelatedNodesServiceInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy_section_paths\Contract\Utility\BatchRunnerInterface;
use Drupal\taxonomy_section_paths\Contract\Service\BatchProcessorServiceInterface;
/**
 * Servicio para crear y ejecutar operaciones en batch.
 */
class BatchProcessorService implements BatchProcessorServiceInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RelatedNodesServiceInterface $relatedNodes,
    protected BatchRunnerInterface $batchRunner,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Añade términos a una cola batch para actualizar sus nodos relacionados.
   *
   * @param string $action
   *   Action to do: update or delete.
   * @param array $terms_data
   *   Term ID and alias to process.
   */
  public function queueTermsForNodeUpdate(string $action, array $terms_data ): void {
    if (empty($terms_data)) {
      return;
    }

    $batch = (new BatchBuilder())
      ->setTitle($this->t('Updating related nodes for taxonomy terms...'))
      ->setInitMessage($this->t('Starting term-based node update...'))
      ->setProgressMessage($this->t('Processing @current of @total taxonomy terms...'))
      ->setErrorMessage($this->t('An error occurred during term-based node update.'));

    foreach ($terms_data as $term_id=>$term_alias) {
      $batch->addOperation([self::class, 'processTerm'], [$action,$term_id, $term_alias]);
    }

    $this->batchRunner->setBatch($batch->toArray());
  }

  /**
   * Callback del batch para aplicar la actualización a un término.
   *
   * @param string $action
   *   Action to do: update or delete.
   * @param string $term_id
   *   Term ID to process.
   * @param string|null $term_alias
   *   The taxonomy term alias.
   * @param array|\ArrayAccess $context
   *   Batch context.
   */
  public static function processTerm(string $action, string $term_id, ?string $term_alias, array &$context): void {
    \Drupal::service('taxonomy_section_paths.batch_processor')
      ->processTermInstance($action, $term_id, $term_alias, $context);
  }

  /**
   * Método de instancia que contiene la lógica real.
   */
  public function processTermInstance(string $action, string $term_id, ?string $term_alias, array &$context): void {
    $this->relatedNodes->applyToRelatedNodes($action, $term_id, $term_alias);
  }


}
