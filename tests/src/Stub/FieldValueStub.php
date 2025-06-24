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
   * Métodos simulados.
   *
   * @var array<string,mixed>
   */
  protected array $methods;

  /**
   * Constructor.
   *
   * @param array<string,mixed> $properties
   *   Array asociativo con nombre => valor de propiedades simuladas.
   */
  public function __construct(array $properties = [], array $methods = []) {
    $this->properties = $properties;
    $this->methods = $methods;
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

  public function __call(string $method, array $args) {
    if (isset($this->methods[$method])) {
      return call_user_func_array($this->methods[$method], $args);
    }
    throw new \BadMethodCallException("Method $method not stubbed.");
  }
  
}
