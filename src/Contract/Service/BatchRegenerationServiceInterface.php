<?php

namespace Drupal\taxonomy_section_paths\Contract\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Servicio para preparar y ejecutar la regeneración de alias de términos.
 */
interface BatchRegenerationServiceInterface {
  
  /**
   * Prepara el batch para regenerar los alias de los términos.
   *
   * @param array $vocabularies
   *   Array asociativo de [bundle => vocabulary].
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   El objeto BatchBuilder listo para ejecutar.
   */
  public function prepareBatch(array $vocabularies): BatchBuilder;

  /**
   * Callback batch que procesa los términos y genera sus alias.
   *
   * @param array $term_ids
   *   IDs de términos a procesar.
   * @param array &$context
   *   Contexto de ejecución del batch.
   */
  public static function processTermsInstance(array $term_ids, array &$context);

  public static function processTerms(array $term_ids, array &$context): void;




}
