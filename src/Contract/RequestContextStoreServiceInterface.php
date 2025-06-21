<?php

namespace Drupal\taxonomy_section_paths\Contract;

/**
 * Provides a request-scoped key-value store for transient contextual data.
 */
interface RequestContextStoreServiceInterface {

  public const GROUP_INPUT = 'input';
  public const GROUP_OUTPUT = 'output';

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
  public function set(string $group, string|int $subkey, mixed $value): void;

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
  public function get(string $group, string|int|null $subkey = NULL): mixed;

  /**
   * Deletes a stored value.
   *
   * @param string $group
   *   The group name.
   * @param string|int $subkey
   *   The identifier.
   */
  public function delete(string $group, string|int $subkey): void;

  /**
   * Checks whether a value exists.
   */
  public function has(string $group, string|int $subkey): bool;

  /**
   * Moves an item from one group to another.
   */
  public function transition(string $from, string $to, string|int $subkey): bool;

  /**
   * Returns the number of items in a group.
   */
  public function countInGroup(string $group): int;

  /**
   * Returns TRUE if the group has exactly one element.
   */
  public function isLastInGroup(string $group): bool;

  /**
   * Removes all data from a group.
   */
  public function clearGroup(string $group): void;

}
