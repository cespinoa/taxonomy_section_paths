<?php

namespace Drupal\Tests\taxonomy_section_paths\Unit\Utility\AliasConflictResolver;



use Drupal\Tests\UnitTestCase;
use Drupal\Tests\taxonomy_section_paths\Fake\FakeAliasRepository;
use Drupal\taxonomy_section_paths\Utility\AliasConflictResolver;

/**
 * Tests alias conflict resolution logic.
 *
 * @group taxonomy_section_paths
 */
class AliasConflictResolverTest extends UnitTestCase {

  /**
   * @covers \Drupal\taxonomy_section_paths\Utility\AliasConflictResolver::ensureUniqueAlias
   * @scenario Alias does not exist initially
   * @context Empty repository
   * @expected Returns the base alias unchanged
   */
  public function testEnsureUniqueAlias(): void {
    $repository = new FakeAliasRepository();
    $resolver = new AliasConflictResolver($repository);

    $langcode = 'en';
    $path = '/node/1';

    // Case 1: alias does not exist yet.
    $alias = $resolver->ensureUniqueAlias('section/technology', $langcode, $path);
    $this->assertSame('section/technology', $alias, 'Returns alias unchanged if it does not exist');

    // Case 2: alias exists for the same path.
    $repository->setAlias('section/technology', $langcode, $path);
    $alias = $resolver->ensureUniqueAlias('section/technology', $langcode, $path);
    $this->assertSame('section/technology', $alias, 'Keeps alias if it exists for the same path');

    // Case 3: alias exists for a different path.
    $repository->setAlias('section/technology', $langcode, '/node/99');
    $alias = $resolver->ensureUniqueAlias('section/technology', $langcode, $path);
    $this->assertSame('section/technology-2', $alias, 'Appends suffix if alias is taken by another path');

    // Case 4: both alias and alias-2 exist for other paths.
    $repository->setAlias('section/technology-2', $langcode, '/node/42');
    $alias = $resolver->ensureUniqueAlias('section/technology', $langcode, $path);
    $this->assertSame('section/technology-3', $alias, 'Continues suffixing until unique alias is found');
  }
}
