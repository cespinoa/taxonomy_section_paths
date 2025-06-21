<?php

namespace Drupal\taxonomy_section_paths\Contract;

/**
 * Interface for generating clean slugs from text strings.
 */
interface SlugifierInterface {

  /**
   * Converts a string into a clean, URL-friendly slug.
   *
   * @param string $text
   *   The original text to slugify (e.g., a title or label).
   * @param int $max_length
   *   The maximum length of the slug. Default is 128 characters.
   *
   * @return string
   *   The slugified version of the text, using lowercase letters,
   *   without accents, symbols, or whitespace.
   */
  public function slugify(string $text, int $max_length = 128): string;

}
