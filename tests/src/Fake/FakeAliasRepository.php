<?php

namespace Drupal\Tests\taxonomy_section_paths\Fake;

use Drupal\path_alias\AliasRepositoryInterface;

/**
 * Fake implementation of the AliasRepositoryInterface for testing purposes.
 */
class FakeAliasRepository implements AliasRepositoryInterface {

  /**
   * Stores aliases by alias + langcode.
   *
   * @var array<string, array{source: string}>
   */
  protected array $aliases = [];

  /**
   * Registers a fake alias in the repository.
   *
   * @param string $alias
   *   The alias path (e.g. "section/news").
   * @param string $langcode
   *   The language code (e.g. "en").
   * @param string $source
   *   The internal source path (e.g. "/node/1").
   */
  public function setAlias(string $alias, string $langcode, string $source): void {
    $this->aliases["$langcode:$alias"] = ['source' => $source];
  }

  /**
   * {@inheritdoc}
   */
  public function lookupByAlias($alias, $langcode = NULL) {
    $key = "$langcode:$alias";
    return $this->aliases[$key] ?? NULL;
  }

  /**
   * The following methods are not required for testing.
   */
  public function lookupBySystemPath($path, $langcode = NULL) {
    return NULL;
  }

  public function getAliasByPath($path, $langcode = NULL) {
    return NULL;
  }

  public function getPathByAlias($alias, $langcode = NULL) {
    return NULL;
  }

  public function preloadPathAlias($paths, $langcode = NULL) {
    // No-op for testing.
  }

  public function pathHasMatchingAlias($alias, $langcode = NULL): bool {
    // Just return whether an alias exists.
    $key = "$langcode:$alias";
    return isset($this->aliases[$key]);
  }
}
