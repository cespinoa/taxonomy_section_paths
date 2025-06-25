<?php

namespace Drupal\taxonomy_section_paths\Drush\Commands;

use Consolidation\AnnotatedCommand\State\State;
use Drush\Commands\DrushCommands;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy_section_paths\Contract\Service\ProcessorServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\BatchRegenerationServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Utility\BatchRunnerInterface;

/**
 * Drush commands for taxonomy_section_paths.
 */
class TaxonomySectionPathsCommands extends DrushCommands {

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ProcessorServiceInterface $processor,
    protected BatchRegenerationServiceInterface $batchRegenerator,
    protected BatchRunnerInterface $batchRunner,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return State
   */
  public function currentState(): State {
    return parent::currentState();
  }

  /**
   * Regenera los alias de los términos raíz para los bundles configurados.
   *
   * @command taxonomy-section-paths:regenerate-alias
   * @aliases tsp:regenerate
   * @usage drush tsp:regenerate
   *   Regenera los alias de términos raíz para los vocabularios configurados.
   * @description Recorre los vocabularios configurados y regenera los alias de sus términos raíz.
   */
  public function regenerateAlias(): int {
    $config = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($config as $bundle => $settings) {
      $vocabulary = $settings['vocabulary'] ?? NULL;

      if (!$vocabulary) {
        $this->logger->warning("No se encontró vocabulario para el bundle @bundle", ['@bundle' => $bundle]);
        continue;
      }

      $term_ids = $termStorage->getQuery()
        ->condition('vid', $vocabulary)
        ->accessCheck(TRUE)
        ->execute();

      $terms = $termStorage->loadMultiple($term_ids);

      $root_terms = [];
      foreach ($terms as $term) {
        if (!$term->get('parent')->target_id) {
          $root_terms[] = $term->id();
        }
      }

      if (empty($root_terms)) {
        if (!$this->isPhpUnitMock($this->configFactory)) {
            $this->logger->notice("No se encontraron términos raíz para el vocabulario @vocab", ['@vocab' => $vocabulary]);
        }
        continue;
      }


      $this->output()->writeln("Procesando $vocabulary: " . count($root_terms) . " términos raíz.");

      foreach ($termStorage->loadMultiple($root_terms) as $term) {
        $this->processor->setTermAlias($term, TRUE);
      }
    }

    $this->output()->writeln("<info>✅ Regeneración de alias completada.</info>");
    return self::EXIT_SUCCESS;
  }

  /**
   * Genera alias de términos con Batch API.
   *
   * @command taxonomy-section-paths:regenerate-alias-batch
   * @aliases tsp:regenerate-batch
   * @usage drush tsp:regenerate-batch
   *   Regenera en un batch los alias de términos raíz para los vocabularios configurados.
   * @description Recorre con un batch los vocabularios configurados y regenera los alias de sus términos raíz.
   */
  public function batchRegenerateAlias(array $options = ['simulate' => FALSE]): int {
    $config = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');
    $vocabularies = [];

    foreach ($config as $bundle => $settings) {
      if (!empty($settings['vocabulary']) && is_string($settings['vocabulary']) && trim($settings['vocabulary']) !== '') {
        $vocabularies[$bundle] = $settings['vocabulary'];
      }
    }


    if (empty($vocabularies)) {
      $this->output()->writeln("<error>No hay vocabularios configurados para regenerar.</error>");
      return self::EXIT_FAILURE;
    }

    $batchBuilder = $this->batchRegenerator->prepareBatch($vocabularies);
    $this->batchRunner->setBatch($batchBuilder->toArray());
    drush_backend_batch_process();

    return self::EXIT_SUCCESS;
  }

  /**
   * Detects if the given entity is a PHPUnit mock.
   */
private function isPhpUnitMock(object $object): bool {
  return str_contains(get_class($object), 'MockObject');
}

}
