<?php

namespace Drupal\Tests\taxonomy_section_paths\Spy;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Messenger espía para tests que almacena mensajes sin emitirlos.
 */
class SpyMessenger implements MessengerInterface {

  /**
   * Mensajes almacenados.
   *
   * Cada mensaje es un array con keys:
   * - 'message' => string|TranslatableMarkup
   * - 'type' => string
   */
  public array $messages = [];

  /**
   * {@inheritdoc}
   */
  public function addMessage($message, $type = self::TYPE_STATUS, $repeat = FALSE): void {
    $this->messages[] = [
      'message' => $message,
      'type' => $type,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function addStatus($message, $repeat = FALSE): void {
    $this->addMessage($message, self::TYPE_STATUS, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addError($message, $repeat = FALSE): void {
    $this->addMessage($message, self::TYPE_ERROR, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addWarning($message, $repeat = FALSE): void {
    $this->addMessage($message, self::TYPE_WARNING, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return $this->messages;
  }

  /**
   * {@inheritdoc}
   */
  public function messagesByType($type = NULL): array {
    if ($type === NULL) {
      return $this->messages;
    }
    return array_filter($this->messages, fn($msg) => $msg['type'] === $type);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->messages = [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByType($type): void {
    $this->messages = array_filter($this->messages, fn($msg) => $msg['type'] !== $type);
  }
}
