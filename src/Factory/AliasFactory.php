<?php

namespace Drupal\taxonomy_section_paths\Factory;

use Drupal\path_alias\Entity\PathAlias;
use Drupal\taxonomy_section_paths\Contract\AliasFactoryInterface;


/**
 * Default factory for creating PathAlias entities.
 */
class AliasFactory implements AliasFactoryInterface {
  protected $entityCreator;

  public function __construct(?callable $entityCreator = null) {
    $this->entityCreator = $entityCreator ?: [PathAlias::class, 'create'];
  }

  public function create(array $values): PathAlias {
    return call_user_func($this->entityCreator, $values);
  }
}


