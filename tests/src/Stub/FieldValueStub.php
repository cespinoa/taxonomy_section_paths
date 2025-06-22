<?php

namespace Drupal\Tests\taxonomy_section_paths\Stub;

/**
 * Stub para simular un objeto campo con propiedades dinámicas, como target_id.
 */
class FieldValueStub {

  /**
   * Propiedades simuladas.
   *
   * @var array<string,mixed>
   */
  protected array $properties;

  /**
   * Constructor.
   *
   * @param array<string,mixed> $properties
   *   Array asociativo con nombre => valor de propiedades simuladas.
   */
  public function __construct(array $properties = []) {
    $this->properties = $properties;
  }

  /**
   * Magic getter para devolver propiedades simuladas.
   *
   * @param string $name
   *   Nombre de la propiedad.
   *
   * @return mixed
   *   Valor de la propiedad si existe, NULL en caso contrario.
   */
  public function __get(string $name) {
    return $this->properties[$name] ?? NULL;
  }
}
