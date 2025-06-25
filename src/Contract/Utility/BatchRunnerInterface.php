<?php

namespace Drupal\taxonomy_section_paths\Contract\Utility;

/**
 * Permite configurar un batch programáticamente.
 */
interface BatchRunnerInterface {
  /**
   * Set a batch.
   *
   * @param array $batch
   *   The batch array.
   */
  public function setBatch(array $batch): void;
}
