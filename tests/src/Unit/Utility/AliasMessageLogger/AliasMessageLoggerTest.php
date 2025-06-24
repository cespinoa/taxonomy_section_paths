<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Utility\AliasMessageLogger;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\taxonomy_section_paths\Spy\SpyLogger;
use Drupal\Tests\taxonomy_section_paths\Spy\SpyMessenger;
use Drupal\taxonomy_section_paths\Utility\AliasMessageLogger;
use PHPUnit\Framework\TestCase;

/**
 * @group taxonomy_section_paths
 * @covers \Drupal\taxonomy_section_paths\Utility\AliasMessageLogger
 */
class AliasMessageLoggerTest extends TestCase {

  protected ConfigFactoryInterface $configFactory;
  protected SpyMessenger $messenger;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected SpyLogger $logger;

  protected function setUp(): void {
    parent::setUp();

    // Setup minimal container with string_translation service to avoid deprecation.
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $translation = $this->createMock(\Drupal\Core\StringTranslation\TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      fn($string, array $args = [], array $options = []) =>
        new TranslatableMarkup($string, $args, $options, $translation)
    );
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    // Stub config to return expected config values.
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(fn($key) => match ($key) {
      'enable_event_logging' => TRUE,
      'silent_messages'      => FALSE,
      default                => NULL,
    });
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($config);

    // Use spies instead of mocks for messenger and logger.
    $this->messenger = new SpyMessenger();
    $this->logger    = new SpyLogger();
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')
      ->with('taxonomy_section_paths')
      ->willReturn($this->logger);
  }

  /**
   * @covers ::logOperation
   * @dataProvider operationCases
   * @scenario Log different alias operations
   * @context Various alias changes like insert, update, delete
   * @expected Logs messages correctly both to messenger and logger
   */
  public function testLogOperationVariants(
    string $operation,
    string $expectedTemplate,
    array $expectedArgs
  ): void {
    $service = new AliasMessageLogger(
      $this->configFactory,
      $this->messenger,
      $this->loggerFactory
    );

    $service->logOperation(
      $operation,
      'node',
      '123',
      'Sample Node',
      '/alias',
      '/old-alias'
    );

    // Check messenger message.
    $entry = $this->messenger->messages[0];
    $this->assertSame('status', $entry['type']);
    $msgObject = $entry['message'];
    $this->assertInstanceOf(TranslatableMarkup::class, $msgObject);

    $ref = new \ReflectionClass($msgObject);
    $propString = $ref->getProperty('string');
    $propString->setAccessible(true);
    $actualTemplate = $propString->getValue($msgObject);

    $propArgs = $ref->getProperty('arguments');
    $propArgs->setAccessible(true);
    $actualArgs = $propArgs->getValue($msgObject);

    $this->assertSame($expectedTemplate, $actualTemplate);
    $this->assertEqualsCanonicalizing($expectedArgs, $actualArgs);

    // Check logger record.
    $log = $this->logger->records[0];
    $this->assertSame('notice', $log['level']);
    $this->assertEquals($msgObject, $log['message']);
  }

  /**
   * Data provider for testLogOperationVariants.
   */
  public static function operationCases(): array {
    return [
      'insert' => [
        'insert',
        'Alias <strong>@new_alias</strong> created for @entity_type <strong>@entity_label</strong> (@entity_id).',
        [
          '@new_alias'    => '/alias',
          '@entity_type'  => 'node',
          '@entity_label' => 'Sample Node',
          '@entity_id'    => '123',
          '@old_alias'    => '/old-alias',
        ],
      ],
      'update' => [
        'update',
        'Alias <strong>@old_alias</strong> updated to <strong>@new_alias</strong> for @entity_type <strong>@entity_label</strong> (@entity_id).',
        [
          '@old_alias'    => '/old-alias',
          '@new_alias'    => '/alias',
          '@entity_type'  => 'node',
          '@entity_label' => 'Sample Node',
          '@entity_id'    => '123',
        ],
      ],
      'delete' => [
        'delete',
        'Alias removed for @entity_type <strong>@entity_label</strong> (@entity_id).',
        [
          '@entity_type'  => 'node',
          '@entity_label' => 'Sample Node',
          '@entity_id'    => '123',
          '@old_alias'    => '/old-alias',
          '@new_alias'    => '/alias',
        ],
      ],
      'delete_without_new_alias' => [
        'delete_without_new_alias',
        'Alias <strong>@old_alias</strong> removed for @entity_type <strong>@entity_label</strong> (@entity_id).',
        [
          '@old_alias'    => '/old-alias',
          '@entity_type'  => 'node',
          '@entity_label' => 'Sample Node',
          '@entity_id'    => '123',
          '@new_alias'    => '/alias',
        ],
      ],
    ];
  }

}
