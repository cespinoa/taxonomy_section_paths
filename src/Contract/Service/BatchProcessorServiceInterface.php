<?php

namespace Drupal\taxonomy_section_paths\Contract\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy_section_paths\Contract\Service\RelatedNodesServiceInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy_section_paths\Contract\Utility\BatchRunnerInterface;

/**
 * Servicio para crear y ejecutar operaciones en batch.
 */
interface BatchProcessorServiceInterface {

  /**
   * Añade términos a una cola batch para actualizar sus nodos relacionados.
   *
   * @param string $action
   *   Action to do: update or delete.
   * @param array $terms_data
   *   Term ID and alias to process.
   */
  public function queueTermsForNodeUpdate(string $action, array $terms_data ): void;

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
  public static function processTerm(string $action, string $term_id, ?string $term_alias, array &$context): void;

  /**
   * Método de instancia que contiene la lógica real.
   */
  public function processTermInstance(string $action, string $term_id, ?string $term_alias, array &$context): void;

}
