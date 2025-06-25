<?php

namespace Drupal\taxonomy_section_paths\Utility;

use Drupal\taxonomy_section_paths\Contract\Utility\BatchRunnerInterface;

class BatchRunner implements BatchRunnerInterface {

  /**
   * {@inheritdoc}
   */
  public function setBatch(array $batch): void {
    batch_set($batch);
  }

}
