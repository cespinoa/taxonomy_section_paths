<?php

namespace Drupal\taxonomy_section_paths\Contract;

use Drupal\path_alias\Entity\PathAlias;

interface AliasFactoryInterface {

  /**
   * Creates a PathAlias entity from an array of values.
   *
   * @param array $values
   *   An associative array of values for the new alias.
   *
   * @return \Drupal\path_alias\Entity\PathAlias
   *   The created PathAlias entity (unsaved).
   */
  public function create(array $values): PathAlias;

}
