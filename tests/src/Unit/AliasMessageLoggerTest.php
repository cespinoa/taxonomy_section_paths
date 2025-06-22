<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit;

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
 */
class AliasMessageLoggerTest extends TestCase {

  protected ConfigFactoryInterface $configFactory;
  protected SpyMessenger $messenger;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected SpyLogger $logger;

  protected function setUp(): void {
    parent::setUp();

    // 1) Boot a minimal container for t() to avoid deprecation in FormattableMarkup.
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $translation = $this->createMock(\Drupal\Core\StringTranslation\TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      fn($string, array $args = [], array $options = []) =>
        new TranslatableMarkup($string, $args, $options, $translation)
    );

    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    // 2) Stub configuration.
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

    // 3) Spies instead of mocks.
    $this->messenger = new SpyMessenger();
    $this->logger    = new SpyLogger();
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')
      ->with('taxonomy_section_paths')
      ->willReturn($this->logger);
  }

  //~ public function testLogOperations(): void {
    //~ $operations = [
      //~ 'insert' => [
        //~ 'expectedTemplate' => 'Alias <strong>@new_alias</strong> created for @entity_type <strong>@entity_label</strong> (@entity_id).',
        //~ 'args' => [
          //~ '@new_alias' => '/alias',
          //~ '@entity_type' => 'node',
          //~ '@entity_label' => 'Sample Node',
          //~ '@entity_id' => '123',
          //~ '@old_alias' => '',
        //~ ],
        //~ 'new_alias' => '/alias',
        //~ 'old_alias' => NULL,
      //~ ],
      //~ 'update' => [
        //~ 'expectedTemplate' => 'Alias <strong>@old_alias</strong> updated to <strong>@new_alias</strong> for @entity_type <strong>@entity_label</strong> (@entity_id).',
        //~ 'args' => [
          //~ '@new_alias' => '/alias-updated',
          //~ '@old_alias' => '/alias',
          //~ '@entity_type' => 'node',
          //~ '@entity_label' => 'Sample Node',
          //~ '@entity_id' => '123',
        //~ ],
        //~ 'new_alias' => '/alias-updated',
        //~ 'old_alias' => '/alias',
      //~ ],
      //~ 'delete' => [
        //~ 'expectedTemplate' => 'Alias removed for @entity_type <strong>@entity_label</strong> (@entity_id).',
        //~ 'args' => [
          //~ '@new_alias' => '',
          //~ '@old_alias' => '',
          //~ '@entity_type' => 'node',
          //~ '@entity_label' => 'Sample Node',
          //~ '@entity_id' => '123',
        //~ ],
        //~ 'new_alias' => NULL,
        //~ 'old_alias' => NULL,
      //~ ],
      //~ 'delete_without_new_alias' => [
        //~ 'expectedTemplate' => 'Alias <strong>@old_alias</strong> removed for @entity_type <strong>@entity_label</strong> (@entity_id).',
        //~ 'args' => [
          //~ '@new_alias' => '',
          //~ '@old_alias' => '/alias',
          //~ '@entity_type' => 'node',
          //~ '@entity_label' => 'Sample Node',
          //~ '@entity_id' => '123',
        //~ ],
        //~ 'new_alias' => NULL,
        //~ 'old_alias' => '/alias',
      //~ ],
    //~ ];

    //~ foreach ($operations as $operation => $data) {
      //~ // Reset spy logs.
      //~ $this->messenger->messages = [];
      //~ $this->logger->records = [];

      //~ $service = new AliasMessageLogger(
        //~ $this->configFactory,
        //~ $this->messenger,
        //~ $this->loggerFactory
      //~ );

      //~ $service->logOperation(
        //~ $operation,
        //~ 'node',
        //~ '123',
        //~ 'Sample Node',
        //~ $data['new_alias'],
        //~ $data['old_alias'],
      //~ );

      //~ // Messenger assertions.
      //~ $this->assertCount(1, $this->messenger->messages, "Messenger: [$operation] should record 1 message.");
      //~ $msgObject = $this->messenger->messages[0]['message'];
      //~ $this->assertInstanceOf(TranslatableMarkup::class, $msgObject);

      //~ // Extraemos plantilla y argumentos.
      //~ $ref = new \ReflectionClass($msgObject);
      //~ $propString = $ref->getProperty('string');
      //~ $propString->setAccessible(TRUE);
      //~ $template = $propString->getValue($msgObject);

      //~ $propArgs = $ref->getProperty('arguments');
      //~ $propArgs->setAccessible(TRUE);
      //~ $args = $propArgs->getValue($msgObject);

      //~ $this->assertSame($data['expectedTemplate'], $template, "[$operation] template match.");
      //~ $this->assertEqualsCanonicalizing($data['args'], $args, "[$operation] args match.");

      //~ // Logger assertions.
      //~ $this->assertCount(1, $this->logger->records, "Logger: [$operation] should record 1 log entry.");
      //~ $record = $this->logger->records[0];
      //~ $this->assertSame('notice', $record['level'], "[$operation] log level is notice.");
      //~ $this->assertInstanceOf(TranslatableMarkup::class, $record['message'], "[$operation] log message is TranslatableMarkup.");
      //~ $refLog = new \ReflectionClass($record['message']);
      //~ $logString = $refLog->getProperty('string');
      //~ $logString->setAccessible(TRUE);
      //~ $this->assertSame($data['expectedTemplate'], $logString->getValue($record['message']), "[$operation] log template match.");
    //~ }

    //~ $this->addToAssertionCount(1);
  //~ }

/**
 * @dataProvider operationCases
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

  // Verifica el mensaje del messenger.
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

  // Verifica el mensaje del logger.
  $log = $this->logger->records[0];
  $this->assertSame('notice', $log['level']);
  $this->assertEquals($msgObject, $log['message']);
}

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
