<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy_section_paths\Contract\Service\BatchRegenerationServiceInterface;
/**
 * Servicio para preparar y ejecutar la regeneración de alias de términos.
 */
class BatchRegenerationService implements BatchRegenerationServiceInterface {

  /**
   * El gestor de entidades.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Prepara el batch para regenerar los alias de los términos.
   *
   * @param array $vocabularies
   *   Array asociativo de [bundle => vocabulary].
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   El objeto BatchBuilder listo para ejecutar.
   */
  public function prepareBatch(array $vocabularies): BatchBuilder {
    $all_term_ids = [];

    foreach ($vocabularies as $bundle => $vocab) {
      if (!$vocab) {
        continue;
      }

      $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', $vocab);

      $ids = $query->execute();
      $all_term_ids += $ids;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $loaded_terms = $term_storage->loadMultiple($all_term_ids);

    $term_ids = [];
    foreach ($loaded_terms as $term) {
      $parent_field = $term->get('parent');
      $parent_target_id = $parent_field->isEmpty() ? null : $parent_field->target_id;

      if (!$parent_target_id) {
        $term_ids[] = $term->id();
      }
    }


    $batch = (new BatchBuilder())
      ->setTitle(t('Regenerando alias de términos'))
      ->setInitMessage(t('Inicializando regeneración de alias...'))
      ->setProgressMessage(t('Procesando término @current de @total...'))
      ->setErrorMessage(t('La regeneración de alias falló.'))
      ->addOperation([static::class, 'processTerms'], [
    // Aseguramos que sea un array indexado.
        array_values($term_ids),
      ]);

    return $batch;
  }

  /**
   * Callback batch que procesa los términos y genera sus alias.
   *
   * @param array $term_ids
   *   IDs de términos a procesar.
   * @param array &$context
   *   Contexto de ejecución del batch.
   */
  public static function processTermsInstance(array $term_ids, array &$context) {
    // Inicialización del contexto (solo la primera vez).
    if (!isset($context['sandbox']['total'])) {
      $context['sandbox']['term_ids'] = $term_ids;
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['total'] = count($term_ids);

      if ($context['sandbox']['total'] === 0) {
        $context['message'] = t('No hay términos para procesar.');
        $context['finished'] = 1;
        return;
      }
    }

    $limit = max(1, min(20, (int) ceil(count($term_ids) / 10)));
    $offset = $context['sandbox']['progress'];
    $batch_ids = array_slice($context['sandbox']['term_ids'], $offset, $limit);

    /** @var \Drupal\taxonomy_section_paths\Contract\Service\ProcessorServiceInterface $aliasGenerator */
    $aliasGenerator = \Drupal::service('taxonomy_section_paths.processor');

    foreach ($batch_ids as $tid) {
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
      if ($term) {
        $aliasGenerator->setTermAlias($term, TRUE);
      }
      $context['sandbox']['progress']++;
    }

    // Mensaje actual de progreso.
    $context['message'] = t('Procesando término @current de @total...', [
      '@current' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['total'],
    ]);

    // Progreso global.
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
  }

  public static function processTerms(array $term_ids, array &$context): void {
    \Drupal::service('taxonomy_section_paths.regenerate_alias')
      ->processTermsInstance($term_ids, $context);
  }




}
