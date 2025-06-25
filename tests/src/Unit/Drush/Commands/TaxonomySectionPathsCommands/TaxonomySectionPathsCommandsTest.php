<?php

namespace Drupal\taxonomy_section_paths\Drush\Commands;

if (!function_exists(__NAMESPACE__ . '\drush_backend_batch_process')) {
  function drush_backend_batch_process() {
    // Stub para evitar error en PHPUnit.
  }
}

namespace Drupal\Tests\taxonomy_section_paths\Unit\Drush\Commands\TaxonomySectionPathsCommands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy_section_paths\Contract\Service\ProcessorServiceInterface;
use Drupal\taxonomy_section_paths\Contract\Service\BatchRegenerationServiceInterface;
use Drupal\taxonomy_section_paths\Drush\Commands\TaxonomySectionPathsCommands;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;
use Drupal\taxonomy_section_paths\Contract\Utility\BatchRunnerInterface;


class TaxonomySectionPathsCommandsTest extends UnitTestCase {

  protected $termStorage;
  protected $entityTypeManager;
  protected $configFactory;
  protected $processor;
  protected $command;
  protected $batchRunner;



  protected function setUp(): void {
    parent::setUp();

    // Mocks básicos.
    $this->termStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->termStorage);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->processor = $this->createMock(ProcessorServiceInterface::class);

    // Instancia el comando con los mocks, mockeando el método logger().
    $this->command = $this->getMockBuilder(TaxonomySectionPathsCommands::class)
        ->setConstructorArgs([
          $this->configFactory,
          $this->entityTypeManager,
          $this->processor,
          $this->createMock(BatchRegenerationServiceInterface::class),
          $this->createMock(BatchRunnerInterface::class),
        ])
        ->onlyMethods(['logger'])
        ->getMock();

  }


  public function testRegenerateAlias(): void {

    // Configuración mock de bundles.
    $configMock = $this->getMockBuilder(\Drupal\Core\Config\Config::class)
      ->disableOriginalConstructor()
      ->getMock();
    $configMock->method('get')
      ->with('bundles')
      ->willReturn([
        'article' => ['vocabulary' => 'tags'],
        'page' => ['vocabulary' => 'topics'],
      ]);
    $this->configFactory->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    // Mock del query encadenado
    $queryMock = $this->getMockBuilder(\Drupal\Core\Entity\Query\QueryInterface::class)
      ->onlyMethods(['condition', 'accessCheck', 'execute'])
      ->getMockForAbstractClass();

    // Configuramos encadenamiento:
    $queryMock->method('condition')->willReturnSelf();
    $queryMock->method('accessCheck')->willReturnSelf();

    // Cuando execute() sea llamado, devolverá IDs simulados por vocabulario.
    $queryMock->method('execute')->willReturnCallback(function() {
      // Retornamos IDs para tags y topics en llamadas sucesivas
      static $calls = 0;
      $calls++;
      if ($calls === 1) {
        return [10, 20, 30];  // IDs para vocabulario 'tags'
      }
      elseif ($calls === 2) {
        return [40, 50];      // IDs para vocabulario 'topics'
      }
      return [];
    });

    // Mockeamos getQuery() para que devuelva nuestro queryMock
    $this->termStorage->method('getQuery')->willReturn($queryMock);

    // Términos mock.
    $parent_stub1 = new FieldValueStub(['target_id' => 0], ['isEmpty' => fn() => TRUE]);
    $term1 = $this->createMock(TermInterface::class);
    $term1->method('get')->with('parent')->willReturn($parent_stub1);
    $term1->method('id')->willReturn(10);

    $parent_stub2 = new FieldValueStub(['target_id' => 5], ['isEmpty' => fn() => FALSE]);
    $term2 = $this->createMock(TermInterface::class);
    $term2->method('get')->with('parent')->willReturn($parent_stub2);
    $term2->method('id')->willReturn(20);

    $parent_stub3 = new FieldValueStub(['target_id' => 0], ['isEmpty' => fn() => TRUE]);
    $term3 = $this->createMock(TermInterface::class);
    $term3->method('get')->with('parent')->willReturn($parent_stub3);
    $term3->method('id')->willReturn(30);

    // Mock flexible para loadMultiple.
    $this->termStorage->method('loadMultiple')
      ->willReturnCallback(function ($ids) use ($term1, $term2, $term3) {
        $terms = [
          10 => $term1,
          20 => $term2,
          30 => $term3,
        ];
        $result = [];
        foreach ($ids as $id) {
          if (isset($terms[$id])) {
            $result[$id] = $terms[$id];
          }
        }
        return $result;
      });

    $expectedCalls = [
      [$term1, TRUE],
      [$term3, TRUE],
    ];

    $callIndex = 0;

    $this->processor->expects($this->exactly(count($expectedCalls)))
      ->method('setTermAlias')
      ->willReturnCallback(function ($term, $flag) use (&$callIndex, $expectedCalls) {
        [$expectedTerm, $expectedFlag] = $expectedCalls[$callIndex];
        $this->assertSame($expectedTerm, $term);
        $this->assertSame($expectedFlag, $flag);
        $callIndex++;
      });

    // Ejecutar el comando.
    $result = $this->command->regenerateAlias();

    $this->assertSame(0, $result);
  }


  public function testBatchRegenerateAliasWithoutConfiguredVocabularies(): void {
    // Configuración mock de bundles.
    $configMock = $this->getMockBuilder(\Drupal\Core\Config\Config::class)
      ->disableOriginalConstructor()
      ->getMock();

    $configMock->method('get')
      ->with('bundles')
      ->willReturn([
        'article' => ['' => 'tags'],
        'page' => [],
      ]);
    $this->configFactory->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    $batchRegenerator = $this->createMock(BatchRegenerationServiceInterface::class);
    $batchRegenerator->expects($this->never())->method('prepareBatch');

    // Sobrescribir el mock dentro del objeto command.
    $reflection = new \ReflectionClass($this->command);
    $property = $reflection->getProperty('batchRegenerator');
    $property->setAccessible(true);
    $property->setValue($this->command, $batchRegenerator);

    // Ahora sí: este `->never()` se aplica sobre el mock realmente usado.


    // Captura de salida.
    ob_start();
    $exitCode = $this->command->batchRegenerateAlias();
    $output = ob_get_clean();

    $this->assertEquals(TaxonomySectionPathsCommands::EXIT_FAILURE, $exitCode);
    //~ $this->assertStringContainsString('No hay vocabularios configurados', $output);
  }

  public function testBatchRegenerateAliasWithConfiguredVocabularies(): void {
    // Configuración mock de vocabularios válidos.
    $configMock = $this->getMockBuilder(\Drupal\Core\Config\Config::class)
      ->disableOriginalConstructor()
      ->getMock();

    $configMock->method('get')
      ->with('bundles')
      ->willReturn([
        'article' => ['vocabulary' => 'tags'],
        'page' => ['vocabulary' => 'topics'],
      ]);
    $this->configFactory
      ->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configMock);

    // Creamos el mock de BatchRegenerationService con expectativa.
    $batchRegenerator = $this->createMock(BatchRegenerationServiceInterface::class);
    $batchRegenerator->expects($this->once())
      ->method('prepareBatch')
      ->with([
        'article' => 'tags',
        'page' => 'topics',
      ])
      ->willReturn(new \Drupal\Core\Batch\BatchBuilder());

    // Reemplazamos el batchRegenerator en el objeto $this->command.
    $reflection = new \ReflectionClass($this->command);
    $property = $reflection->getProperty('batchRegenerator');
    $property->setAccessible(true);
    $property->setValue($this->command, $batchRegenerator);

    // Ejecutamos el comando.
    $exitCode = $this->command->batchRegenerateAlias();

    // Aserción final.
    $this->assertEquals(TaxonomySectionPathsCommands::EXIT_SUCCESS, $exitCode);
  }



  

}
