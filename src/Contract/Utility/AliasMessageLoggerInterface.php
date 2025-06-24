<?php

namespace Drupal\taxonomy_section_paths\Contract\Utility;

/**
 * Interface for logging alias operations and showing user messages.
 */
interface AliasMessageLoggerInterface {

  /**
   * Logs and optionally displays a message related to an alias operation.
   *
   * @param string $operation
   *   Types: 'insert', 'update', 'delete', 'delete_without_new_alias'.
   * @param string $entity_type
   *   The entity type (e.g., 'taxonomy_term' or 'node').
   * @param string $entity_id
   *   The entity ID.
   * @param string $entity_label
   *   The entity label.
   * @param string|null $new_alias
   *   The new alias (if applicable).
   * @param string|null $old_alias
   *   The old alias (if applicable).
   */
  public function logOperation(
    string $operation,
    string $entity_type,
    string $entity_id,
    string $entity_label,
    ?string $new_alias,
    ?string $old_alias,
  ): void;

}
