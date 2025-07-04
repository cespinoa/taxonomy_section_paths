<?php

namespace Drupal\taxonomy_section_paths\Contract\Service;

interface TermTreeBuilderInterface {
  /**
   * Genera un árbol jerárquico de términos con enlaces y clases Tailwind.
   *
   * @param string $vocabulary_id
   *   El ID del vocabulario.
   * @param int $parent
   *   El TID padre (por defecto 0).
   * @param int|null $max_depth
   *   Profundidad máxima (null = sin límite).
   * @return array
   *   Un array renderizable.
   */
  public function buildTree(
    string $vocabulary_id,
    int $parent_id = 0,
    ?int $max_depth = NULL,
    $custom_link_top,
    $custom_link_top_url,
    $custom_link_bottom,
    $custom_link_bottom_url,
    $navbar,
    $show_branding,
    $show_logo,
    $show_site_name,
    $show_slogan
    ): ?array;
    
}
