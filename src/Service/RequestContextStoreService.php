<?php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy_section_paths\Contract\RequestContextStoreServiceInterface;

/**
 * Provides a request-scoped key-value store for transient contextual data.
 */
class RequestContextStoreService implements RequestContextStoreServiceInterface {

  public const GROUP_INPUT = 'input';
  public const GROUP_OUTPUT = 'output';

  /**
   * Internal data container.
   *
   * @var array
   */
  protected array $data = [];

  /**
   * Stores a value in a group under a subkey.
   *
   * @param string $group
   *   The group name (e.g., 'input').
   * @param string|int $subkey
   *   The identifier (e.g., entity ID).
   * @param mixed $value
   *   The value to store.
   */
  public function set(string $group, string|int $subkey, mixed $value): void {
    $this->data[$group][$subkey] = $value;
  }

  /**
   * Gets a stored value or all group data.
   *
   * @param string $group
   *   The group name.
   * @param string|int|null $subkey
   *   (optional) The identifier to retrieve.
   *
   * @return mixed
   *   The stored value, or the full group, or NULL.
   */
  public function get(string $group, string|int|null $subkey = NULL): mixed {
    if ($subkey !== NULL) {
      return $this->data[$group][$subkey] ?? NULL;
    }
    return $this->data[$group] ?? [];
  }

  /**
   * Deletes a stored value.
   *
   * @param string $group
   *   The group name.
   * @param string|int $subkey
   *   The identifier.
   */
  public function delete(string $group, string|int $subkey): void {
    unset($this->data[$group][$subkey]);
  }

  /**
   * Checks whether a value exists.
   */
  public function has(string $group, string|int $subkey): bool {
    return isset($this->data[$group][$subkey]);
  }

  /**
   * Moves an item from one group to another.
   */
  public function transition(string $from, string $to, string|int $subkey): bool {
    if (!isset($this->data[$from][$subkey])) {
      return FALSE;
    }
    $this->data[$to][$subkey] = $this->data[$from][$subkey];
    unset($this->data[$from][$subkey]);
    return TRUE;
  }

  /**
   * Returns the number of items in a group.
   */
  public function countInGroup(string $group): int {
    return isset($this->data[$group]) && is_array($this->data[$group])
      ? count($this->data[$group])
      : 0;
  }

  /**
   * Returns TRUE if the group has exactly one element.
   */
  public function isLastInGroup(string $group): bool {
    return $this->countInGroup($group) === 0;
  }

  /**
   * Removes all data from a group.
   */
  public function clearGroup(string $group): void {
    unset($this->data[$group]);
  }

}
