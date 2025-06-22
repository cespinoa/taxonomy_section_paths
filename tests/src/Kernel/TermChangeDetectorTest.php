<?php

namespace Drupal\Tests\taxonomy_section_paths\Kernel;

use Drupal\KernelTests\KernelTestBase;

use Drupal\taxonomy_section_paths\Service\TermChangeDetector;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;


/**
 * Test the TermChangeDetector service.
 *
 * @group taxonomy_section_paths
 */
class TermChangeDetectorTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * Debes habilitar 'taxonomy' porque usas TermInterface y config propia.
   */
  protected static $modules = [
    'taxonomy',
    'taxonomy_section_paths',
    'path_alias',
  ];

  protected TermChangeDetector $termChangeDetector;

  protected function setUp(): void {
    parent::setUp();

    // Inicia la configuración de tu módulo si es necesario:
    // $this->installConfig(['taxonomy_section_paths']);

    $this->termChangeDetector = $this->container->get('taxonomy_section_paths.term_change_detector');
  }

  public function testNeedsAliasUpdateForNewTerm() {
    // Mock ConfigInterface
    $configMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configMock->method('get')
      ->with('bundles')
      ->willReturn([
        'some_bundle' => ['vocabulary' => 'vocab1', 'field' => 'field_tags'],
      ]);

    // Mock ConfigFactoryInterface
    $configFactoryMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configMock);

    // Instancia el servicio con la configuración mock
    $termChangeDetector = new TermChangeDetector($configFactoryMock);

    // Mock TermInterface con bundle vocab1
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('vocab1');

    // Ejecutar y comprobar que devuelve true para nuevo término
    $result = $termChangeDetector->needsAliasUpdate($term, FALSE);
    $this->assertTrue($result);
  }

  public function testNeedsAliasUpdateForUpdateNoChange(): void {
    $configMock = $this->createMock(\Drupal\Core\Config\Config::class);
    $configMock->method('get')->with('bundles')->willReturn([
      'some_bundle' => ['vocabulary' => 'vocab1', 'field' => 'field_tags'],
    ]);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configMock);

    $termChangeDetector = new TermChangeDetector($configFactoryMock);

    $parent = new \stdClass();
    $parent->target_id = 5;

    $originalTerm = $this->createMock(TermInterface::class);
    $originalTerm->method('label')->willReturn('Label A');
    $originalTerm->method('get')->willReturnCallback(fn($name) => $name === 'parent' ? $parent : NULL);

    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('vocab1');
    $term->method('label')->willReturn('Label A');
    $term->method('get')->willReturnCallback(fn($name) => match ($name) {
      'parent' => $parent,
      'original' => $originalTerm,
      default => NULL,
    });

    $result = $termChangeDetector->needsAliasUpdate($term, TRUE);

    //~ var_dump([
      //~ 'original_parent' => $originalTerm->get('parent')->target_id,
      //~ 'current_parent' => $term->get('parent')->target_id,
      //~ 'original_label' => $originalTerm->label(),
      //~ 'current_label' => $term->label(),
    //~ ]);
    
    $this->assertFalse($result);
  }



  public function testNeedsAliasUpdateForUpdateChanged() {
    // Mock Config that returns bundles configuration.
    $configMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configMock->method('get')
      ->with('bundles')
      ->willReturn([
        'some_bundle' => ['vocabulary' => 'vocab1', 'field' => 'field_tags'],
      ]);

    // Mock ConfigFactoryInterface returning the configMock.
    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configMock);

    $termChangeDetector = new TermChangeDetector($configFactoryMock);

    // Mock original term.
    $original = $this->createMock(TermInterface::class);
    $original->method('label')->willReturn('Label A');

    // FieldValueStub to simulate parent target_id.
    $parentOriginal = new FieldValueStub(['target_id' => 123]);
    $parentCurrent = new FieldValueStub(['target_id' => 456]);
    //~ $fieldItemListMock = new FieldValueStub(['target_id' => 123]);

    // Original term's get('parent') returns $parentOriginal.
    $original->method('get')
      ->willReturnCallback(fn($name) => $name === 'parent' ? $parentOriginal : NULL);

    // Current term mock.
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('vocab1');
    $term->method('label')->willReturn('Label B');

    // Current term's get('parent') returns $parentCurrent.
    // Current term's get('original') returns the original term.
    $term->method('get')
      ->willReturnCallback(fn($name) => match($name) {
        'parent' => $parentCurrent,
        'original' => $original,
        default => NULL,
      });

    // Test that the method detects changes and returns TRUE.
    $result = $termChangeDetector->needsAliasUpdate($term, TRUE);
    $this->assertTrue($result);
  }


  public function testNeedsAliasUpdateForDifferentVocabulary() {
    // Config mock con vocabularios diferentes
    $configMock = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $configMock->method('get')
      ->with('bundles')
      ->willReturn([
        'some_bundle' => ['vocabulary' => 'other_vocab', 'field' => 'field_tags'],
      ]);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')
      ->with('taxonomy_section_paths.settings')
      ->willReturn($configMock);

    $termChangeDetector = new TermChangeDetector($configFactoryMock);

    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('vocab1');

    $result = $termChangeDetector->needsAliasUpdate($term, FALSE);
    $this->assertFalse($result);
  }


}
