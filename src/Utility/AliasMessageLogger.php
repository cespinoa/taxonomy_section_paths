<?php

namespace Drupal\taxonomy_section_paths\Utility;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy_section_paths\Contract\AliasMessageLoggerInterface;
use Psr\Log\LoggerInterface;

/**
 * Logs alias operations and displays user messages if configured to do so.
 */
class AliasMessageLogger implements AliasMessageLoggerInterface {

  /**
   * The loggerInterface.
   */
  protected LoggerInterface $logger;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected MessengerInterface $messenger,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('taxonomy_section_paths');
  }

  /**
   * {@inheritdoc}
   */
  public function logOperation(
    string $operation,
    string $entity_type,
    string $entity_id,
    string $entity_label,
    ?string $new_alias,
    ?string $old_alias,
  ): void {
    $strings_array = [
      '@entity_type' => $entity_type,
      '@entity_id' => $entity_id,
      '@entity_label' => $entity_label,
      '@old_alias' => $old_alias ?? '',
      '@new_alias' => $new_alias ?? '',
    ];

    $event_logging = $this->configFactory
      ->get('taxonomy_section_paths.settings')
      ->get('enable_event_logging');

    $display_messages = !$this->configFactory
      ->get('taxonomy_section_paths.settings')
      ->get('silent_messages');

    $msg = match ($operation) {
      'delete' => t('Alias removed for @entity_type <strong>@entity_label</strong> (@entity_id).', $strings_array),
      'delete_without_new_alias' => t('Alias <strong>@old_alias</strong> removed for @entity_type <strong>@entity_label</strong> (@entity_id).', $strings_array),
      'update' => t('Alias <strong>@old_alias</strong> updated to <strong>@new_alias</strong> for @entity_type <strong>@entity_label</strong> (@entity_id).', $strings_array),
      'insert' => t('Alias <strong>@new_alias</strong> created for @entity_type <strong>@entity_label</strong> (@entity_id).', $strings_array),
      default => '',
    };

    if ($display_messages && $msg) {
      $this->messenger->addStatus($msg);
    }

    if ($event_logging && $msg) {
      $this->logger->notice((string) $msg);
    }
  }

}
