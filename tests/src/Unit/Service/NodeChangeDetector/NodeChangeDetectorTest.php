<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\NodeChangeDetector;

use Drupal\Tests\UnitTestCase;
use Drupal\taxonomy_section_paths\Service\NodeChangeDetector;
use Drupal\node\NodeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;

/**
 * Tests NodeChangeDetector.
 *
 * @group taxonomy_section_paths
 */
class NodeChangeDetectorTest extends UnitTestCase {

  protected ConfigFactoryInterface $configFactoryMock;
  protected NodeChangeDetector $nodeChangeDetector;
  
  protected function setUp(): void {
    parent::setUp();

    // Mock ConfigInterface que devuelve el array bundles.
    $configSettingsMock = $this->createMock(ConfigFactoryInterface::class);
    $configSettingsMock->method('get')
      ->with('bundles')
      ->willReturn([
        'article' => ['field' => 'field_tags'],
        // Puedes añadir más bundles si quieres probar otros casos.
      ]);

    // Mock ConfigFactoryInterface que devuelve el ConfigInterface mock.
    $this->configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configSettingsMock);

    // Instancia del servicio con la configuración mock.
    $this->nodeChangeDetector = new NodeChangeDetector($this->configFactoryMock);
  }
  
  /**
   * @covers \Drupal\taxonomy_section_paths\Service\NodeChangeDetector::needsAliasUpdate
   * @scenario New node creation
   * @context Node bundle is configured and field exists
   * @expected Should always require alias update on new node creation
   */
  public function testNeedsAliasUpdateForNewNode() {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->with('field_tags')->willReturn(true);

    // Para un nuevo nodo (no update), siempre devuelve TRUE.
    $result = $this->nodeChangeDetector->needsAliasUpdate($node, FALSE);
    $this->assertTrue($result);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\NodeChangeDetector::needsAliasUpdate
   * @scenario Node update without changes in relevant fields
   * @context Node bundle is configured and field exists, original entity has same values
   * @expected Should NOT require alias update
   */
  public function testNeedsAliasUpdateForUpdateNoChange() {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->with('field_tags')->willReturn(true);

    $original = $this->createMock(NodeInterface::class);
    $original->method('label')->willReturn('Same Label');
    $original->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 123])],
    ]);

    $node->method('label')->willReturn('Same Label');

    $node->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 123])],
      ['original', $original],
    ]);

    $result = $this->nodeChangeDetector->needsAliasUpdate($node, TRUE);
    $this->assertFalse($result);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\NodeChangeDetector::needsAliasUpdate
   * @scenario Node update with changes in relevant fields
   * @context Node bundle is configured and field exists, original entity has different values
   * @expected Should require alias update
   */
  public function testNeedsAliasUpdateForUpdateChanged() {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->with('field_tags')->willReturn(true);

    $original = $this->createMock(NodeInterface::class);
    $original->method('label')->willReturn('Old Label');
    $original->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 123])],
    ]);

    $node->method('label')->willReturn('New Label');

    $node->method('get')->willReturnMap([
      ['field_tags', new FieldValueStub(['target_id' => 456])],
      ['original', $original],
    ]);

    $result = $this->nodeChangeDetector->needsAliasUpdate($node, TRUE);
    $this->assertTrue($result);
  }

}
