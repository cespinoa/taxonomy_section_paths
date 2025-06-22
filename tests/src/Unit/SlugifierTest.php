<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy_section_paths\Unit;

use Drupal\taxonomy_section_paths\Utility\Slugifier;

use PHPUnit\Framework\TestCase;

/**
 * Unit test for the Slugifier utility class.
 */
class SlugifierTest extends TestCase {

  /**
   * Test the slugify() method with various input cases.
   */
  public function testSlugify(): void {
    $slugifier = new Slugifier();

    $this->assertSame(
      'simple-text',
      $slugifier->slugify('Simple Text'),
      'Basic slug conversion failed.'
    );

    $this->assertSame(
      'texto-largo-de-prueba-123',
      $slugifier->slugify('Texto Largo de Prueba 123'),
      'Slug conversion with numbers failed.'
    );

    $this->assertSame(
      'cafe-con-pan',
      $slugifier->slugify('Café con pan'),
      'Accented characters were not transliterated correctly.'
    );
  }

}
