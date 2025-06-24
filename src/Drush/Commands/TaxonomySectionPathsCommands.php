<?php

namespace Drupal\taxonomy_section_paths\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taxonomy_section_paths\Contract\Service\ProcessorServiceInterface;
use Drupal\taxonomy_section_paths\Service\BatchRegenerationService;
use Drupal\taxonomy\Entity\Term;

/**
 * Drush commands for taxonomy_section_paths.
 */
final class TaxonomySectionPathsCommands extends DrushCommands {

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ProcessorServiceInterface $aliasGenerator,
    protected BatchRegenerationService $regenerateAlias,
  ) {}

  /**
   * Regenera los alias de los términos raíz para los bundles configurados.
   *
   * @command taxonomy-section-paths:regenerate
   * @aliases tpt:regenerate
   * @usage drush tpt:regenerate
   *   Regenera los alias de términos raíz para los vocabularios configurados.
   * @description Recorre los vocabularios configurados y regenera los alias de sus términos raíz.
   */
  public function regenerate(): int {
    $config = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');
    $logger = \Drupal::logger('taxonomy_section_paths');

    foreach ($config as $bundle => $settings) {
      $vocabulary = $settings['vocabulary'] ?? NULL;

      if (!$vocabulary) {
        $logger->warning("No se encontró vocabulario para el bundle @bundle", ['@bundle' => $bundle]);
        continue;
      }

      $term_ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $vocabulary)
        ->accessCheck(TRUE)
        ->execute();

      $root_terms = [];
      foreach (Term::loadMultiple($term_ids) as $term) {
        if (!$term->parent->target_id) {
          $root_terms[] = $term->id();
        }
      }

      if (empty($root_terms)) {
        $logger->notice("No se encontraron términos raíz para el vocabulario @vocab", ['@vocab' => $vocabulary]);
        continue;
      }

      $this->output()->writeln("Procesando $vocabulary: " . count($root_terms) . " términos raíz.");

      foreach (Term::loadMultiple($root_terms) as $term) {
        $alias = $this->aliasGenerator->setTermAlias($term, TRUE);

        if ($alias) {
          $this->output()->writeln("✔ Término ID {$term->id()} → alias: $alias");
          $logger->info("Alias generado para término ID @id: @alias", ['@id' => $term->id(), '@alias' => $alias]);
        }
        else {
          $this->output()->writeln("✖ No se pudo generar alias para término ID {$term->id()}.");
          $logger->warning("No se pudo generar alias para término ID @id", ['@id' => $term->id()]);
        }
      }
    }

    $this->output()->writeln("<info>✅ Regeneración de alias completada.</info>");
    return self::EXIT_SUCCESS;
  }

  /**
   * Genera alias de términos con Batch API.
   *
   * @command taxonomy-section-paths:generate-aliases
   */
  public function generateAliases(array $options = ['simulate' => FALSE]) {
    $config = $this->configFactory->get('taxonomy_section_paths.settings')->get('bundles');
    $vocabularies = [];

    foreach ($config as $bundle => $settings) {
      if (!empty($settings['vocabulary'])) {
        $vocabularies[$bundle] = $settings['vocabulary'];
      }
    }

    if (empty($vocabularies)) {
      $this->output()->writeln("<error>No hay vocabularios configurados para regenerar.</error>");
      return self::EXIT_FAILURE;
    }

    $batchBuilder = $this->regenerateAlias->prepareBatch($vocabularies);
    batch_set($batchBuilder->toArray());
    drush_backend_batch_process();
  }

}
