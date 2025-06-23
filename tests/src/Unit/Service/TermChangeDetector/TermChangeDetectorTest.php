<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Service\TermChangeDetector;

use Drupal\Tests\UnitTestCase;
use Drupal\taxonomy_section_paths\Service\TermChangeDetector;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Tests\taxonomy_section_paths\Stub\FieldValueStub;

/**
 * Tests the TermChangeDetector service.
 *
 * @group taxonomy_section_paths
 */
class TermChangeDetectorTest extends UnitTestCase {

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\TermChangeDetector::needsAliasUpdate
   * @scenario New term creation in managed vocabulary
   * @context Term bundle matches configured vocabulary, is not an update
   * @expected Should require alias update (returns TRUE)
   */
  public function testNeedsAliasUpdateForNewTerm() {
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('bundles')->willReturn([
      'some_bundle' => ['vocabulary' => 'vocab1', 'field' => 'field_tags'],
    ]);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    $termChangeDetector = new TermChangeDetector($configFactoryMock);

    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('vocab1');

    $result = $termChangeDetector->needsAliasUpdate($term, FALSE);
    $this->assertTrue($result);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\TermChangeDetector::needsAliasUpdate
   * @scenario Update term with no changes in label or parent
   * @context Term bundle matches configured vocabulary, original term matches current
   * @expected Should NOT require alias update (returns FALSE)
   */
  public function testNeedsAliasUpdateForUpdateNoChange(): void {
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('bundles')->willReturn([
      'some_bundle' => ['vocabulary' => 'vocab1', 'field' => 'field_tags'],
    ]);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

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
    $this->assertFalse($result);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\TermChangeDetector::needsAliasUpdate
   * @scenario Update term with changed label or parent
   * @context Term bundle matches configured vocabulary, original term different from current
   * @expected Should require alias update (returns TRUE)
   */
  public function testNeedsAliasUpdateForUpdateChanged() {
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('bundles')->willReturn([
      'some_bundle' => ['vocabulary' => 'vocab1', 'field' => 'field_tags'],
    ]);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    $termChangeDetector = new TermChangeDetector($configFactoryMock);

    $original = $this->createMock(TermInterface::class);
    $original->method('label')->willReturn('Label A');
    $parentOriginal = new FieldValueStub(['target_id' => 123]);
    $original->method('get')->willReturnCallback(fn($name) => $name === 'parent' ? $parentOriginal : NULL);

    $parentCurrent = new FieldValueStub(['target_id' => 456]);

    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('vocab1');
    $term->method('label')->willReturn('Label B');
    $term->method('get')->willReturnCallback(fn($name) => match($name) {
      'parent' => $parentCurrent,
      'original' => $original,
      default => NULL,
    });

    $result = $termChangeDetector->needsAliasUpdate($term, TRUE);
    $this->assertTrue($result);
  }

  /**
   * @covers \Drupal\taxonomy_section_paths\Service\TermChangeDetector::needsAliasUpdate
   * @scenario Term bundle not managed by module
   * @context Configured vocabularies do not include term's bundle
   * @expected Should NOT require alias update (returns FALSE)
   */
  public function testNeedsAliasUpdateForDifferentVocabulary() {
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('bundles')->willReturn([
      'some_bundle' => ['vocabulary' => 'other_vocab', 'field' => 'field_tags'],
    ]);

    $configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->with('taxonomy_section_paths.settings')->willReturn($configMock);

    $termChangeDetector = new TermChangeDetector($configFactoryMock);

    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('vocab1');

    $result = $termChangeDetector->needsAliasUpdate($term, FALSE);
    $this->assertFalse($result);
  }

}
