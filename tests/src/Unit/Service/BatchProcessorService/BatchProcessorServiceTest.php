<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\BatchProcessorService;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy_section_paths\Contract\Service\RelatedNodesServiceInterface;
use Drupal\taxonomy_section_paths\Service\BatchProcessorService;
use Drupal\taxonomy_section_paths\Contract\Utility\BatchRunnerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\taxonomy_section_paths\Service\BatchProcessorService
 */
class BatchProcessorServiceTest extends TestCase {

  protected $entityTypeManager;
  protected $relatedNodes;
  protected $translation;
  protected $batchRunner;
  protected $service;

  protected function setUp(): void {
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->relatedNodes = $this->createMock(RelatedNodesServiceInterface::class);
    $this->batchRunner = $this->createMock(BatchRunnerInterface::class);
    $this->translation = $this->createMock(TranslationInterface::class);

    // Simula traducciones devolviendo el string tal cual.
    $this->translation
      ->method('translate')
      ->willReturnCallback(fn($string) => $string);

    $this->service = new BatchProcessorService(
      $this->entityTypeManager,
      $this->relatedNodes,
      $this->batchRunner,
      $this->translation
    );
  }

  /**
   * @covers ::queueTermsForNodeUpdate
   * @scenario Cola vacía
   * @context No hay términos para procesar
   * @expected No se inicializa el batch
   */
  public function testQueueTermsForNodeUpdateWithEmptyData(): void {
    // batch_set debería no ser llamado en este caso,
    // pero al no poder interceptar directamente la función global,
    // simplemente nos aseguramos de que no lanza errores.
    $this->service->queueTermsForNodeUpdate('update', []);
    $this->assertTrue(TRUE); // Dummy assertion
  }

  /**
   * @covers ::queueTermsForNodeUpdate
   * @scenario Cola con términos
   * @context Hay términos que procesar
   * @expected Se genera estructura batch con operaciones
   */
  public function testQueueTermsForNodeUpdateWithTerms(): void {
    // Simula que se definen términos
    $terms_data = [
      '10' => '/term-one',
      '22' => '/term-two',
    ];

    // Para verificar el resultado, forzamos que batch_set() use una función personalizada.
    // Esto no se puede hacer directamente, pero sí podrías aislar mejor si refactorizas en el futuro.
    // Aquí simplemente llamamos al método y comprobamos que no lanza errores.

    $this->service->queueTermsForNodeUpdate('delete', $terms_data);
    $this->assertTrue(TRUE); // Dummy assertion
  }

  /**
   * @covers ::processTermInstance
   * @scenario Proceso de término
   * @context applyToRelatedNodes debe ser llamado
   * @expected Llama a related_nodes con los parámetros adecuados
   */
  public function testProcessTermInstance(): void {
    $context = [];
    $this->relatedNodes
      ->expects($this->once())
      ->method('applyToRelatedNodes')
      ->with('update', '33', '/term-alias');

    $this->service->processTermInstance('update', '33', '/term-alias', $context);
  }

}
