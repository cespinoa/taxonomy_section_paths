<?php

namespace Drupal\taxonomy_section_paths\Utility;

use Drupal\Component\Utility\Unicode;
use Drupal\taxonomy_section_paths\Contract\SlugifierInterface;

/**
 * Provides a method to generate clean slugs from text.
 */
class Slugifier implements SlugifierInterface {

  /**
   * {@inheritdoc}
   */
  public function slugify(string $text, int $max_length = 128): string {
    $transliteration = [
      'á' => 'a',
      'à' => 'a',
      'ä' => 'a',
      'â' => 'a',
      'ã' => 'a',
      'å' => 'a',
      'é' => 'e',
      'è' => 'e',
      'ë' => 'e',
      'ê' => 'e',
      'í' => 'i',
      'ì' => 'i',
      'ï' => 'i',
      'î' => 'i',
      'ó' => 'o',
      'ò' => 'o',
      'ö' => 'o',
      'ô' => 'o',
      'õ' => 'o',
      'ú' => 'u',
      'ù' => 'u',
      'ü' => 'u',
      'û' => 'u',
      'ñ' => 'n',
      'ç' => 'c',
      'Á' => 'a',
      'À' => 'a',
      'Ä' => 'a',
      'Â' => 'a',
      'Ã' => 'a',
      'Å' => 'a',
      'É' => 'e',
      'È' => 'e',
      'Ë' => 'e',
      'Ê' => 'e',
      'Í' => 'i',
      'Ì' => 'i',
      'Ï' => 'i',
      'Î' => 'i',
      'Ó' => 'o',
      'Ò' => 'o',
      'Ö' => 'o',
      'Ô' => 'o',
      'Õ' => 'o',
      'Ú' => 'u',
      'Ù' => 'u',
      'Ü' => 'u',
      'Û' => 'u',
      'Ñ' => 'n',
      'Ç' => 'c',
    ];

    $text = strtr($text, $transliteration);
    $slug = mb_strtolower($text);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    return Unicode::truncate($slug, $max_length, TRUE, TRUE);
  }

}
