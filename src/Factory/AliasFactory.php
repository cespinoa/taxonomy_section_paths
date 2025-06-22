<?php

namespace Drupal\taxonomy_section_paths\Factory;

use Drupal\path_alias\Entity\PathAlias;
use Drupal\taxonomy_section_paths\Contract\AliasFactoryInterface;


/**
 * Default factory for creating PathAlias entities.
 */
class AliasFactory implements AliasFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function create(array $values): PathAlias {
    return PathAlias::create($values);
  }

}
