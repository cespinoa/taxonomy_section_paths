<?php

namespace Drupal\taxonomy_section_paths\Contract\Drush\Commands;


/**
 * Insert and delete taxonomy terms and nodes for tests purposes.
 */
interface TaxonomySectionPathsTestDataCommandsInterface {

  

  /**
   * Genera datos de prueba (términos y nodos).
   *
   */
  public function generateTestData(): int ;

  /**
   * Elimina los datos de prueba generados anteriormente.
   *
   */
  public function deleteTestData(): int;

}
