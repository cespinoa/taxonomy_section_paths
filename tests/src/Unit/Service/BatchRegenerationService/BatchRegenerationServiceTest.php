<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\BatchRegenerationService;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy_section_paths\Service\BatchRegenerationService;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Drupal\Core\Entity\EntityInterface;





class BatchRegenerationServiceTest extends UnitTestCase {

  protected $termStorage;
  protected $entityTypeManager;
  protected $service;

  protected function setUp(): void {
    parent::setUp();

    // Mock del almacenamiento de términos.
    $this->termStorage = $this->createMock(\stdClass::class);

    // Mock del entityTypeManager para devolver el termStorage.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->termStorage);

    $this->termStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('taxonomy_term')
      ->willReturn($this->termStorage);

    // Instancia del servicio a probar, inyectando el entityTypeManager mockeado.
    $this->service = new \Drupal\taxonomy_section_paths\Service\BatchRegenerationService($this->entityTypeManager);
  }

  public function testPrepareBatch() {
    // Arrange: mocks para queries (dos vocabularios).
    $mockQueryTags = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['accessCheck', 'condition', 'execute'])
      ->getMock();
    $mockQueryTags->method('accessCheck')->willReturnSelf();
    $mockQueryTags->method('condition')->with('vid', 'tags')->willReturnSelf();
    $mockQueryTags->method('execute')->willReturn(['10' => 10]);

    $mockQueryTopics = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['accessCheck', 'condition', 'execute'])
      ->getMock();
    $mockQueryTopics->method('accessCheck')->willReturnSelf();
    $mockQueryTopics->method('condition')->with('vid', 'topics')->willReturnSelf();
    $mockQueryTopics->method('execute')->willReturn(['20' => 20]);

    $this->termStorage
      ->method('getQuery')
      ->willReturnOnConsecutiveCalls($mockQueryTags, $mockQueryTopics);

    // Arrange: mocks para términos cargados.
    $term1 = $this->createMock(TermInterface::class);
    $term1->method('get')->with('parent')
      ->willReturn(new FieldValueStub(['target_id' => 0], ['isEmpty' => fn() => TRUE]));
    $term1->method('id')->willReturn(10);

    $term2 = $this->createMock(TermInterface::class);
    $term2->method('get')->with('parent')
      ->willReturn(new FieldValueStub(['target_id' => 5], ['isEmpty' => fn() => FALSE]));
    $term2->method('id')->willReturn(20);

    $this->termStorage
      ->method('loadMultiple')
      ->with(['10' => 10, '20' => 20])
      ->willReturn([
        10 => $term1,
        20 => $term2,
      ]);

    // Act: llamar al método real.
    $batch = $this->service->prepareBatch([
      'article' => 'tags',
      'page' => 'topics',
    ]);

    // Assert: batch solo con término raíz (ID 10).
    $batch_array = $batch->toArray();
        
    $operations = $batch_array['operations'];
    $this->assertCount(1, $operations);

    [$callback, $args] = $operations[0];
    $this->assertSame([10], $args[0]);
  }
}
