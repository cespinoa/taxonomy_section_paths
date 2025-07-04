<?php

/**
 * @file
 * Hooks provided by the Taxonomy Section Paths module.
 */

/**
 * Permite alterar los enlaces personalizados añadidos al árbol de términos.
 *
 * @param array &$custom_links
 *   Un array asociativo con claves como 'top' y 'bottom'. Cada valor puede ser:
 *   - NULL, para omitir ese enlace.
 *   - Un array con al menos:
 *     - 'label' (string): El texto a mostrar.
 *     - 'url' (string|\Drupal\Core\Url): El destino del enlace.
 *     - 'attributes' (opcional): Array de atributos HTML para el enlace.
 *
 * @param \Drupal\taxonomy_section_paths\Plugin\Block\TermTreeBlock $block
 *   La instancia del bloque que está construyendo el árbol.
 *
 * @see \Drupal\taxonomy_section_paths\Service\TermTreeBuilder::getCustomLinks()
 */
function hook_taxonomy_section_paths_custom_links_alter(array &$custom_links, \Drupal\taxonomy_section_paths\Plugin\Block\TermTreeBlock $block) {
  // Ejemplo: añadir un enlace al principio si estamos en /blog
  $path = \Drupal::service('path.current')->getPath();
  if (str_starts_with($path, '/blog')) {
    $custom_links['top'] = [
      'label' => 'Volver al blog',
      'url' => '/blog',
      'attributes' => ['class' => ['text-sm', 'text-gray-500']],
    ];
  }
}


/**
 * Permite alterar el render array de un ítem del árbol.
 *
 * @param array &$item
 *   El array de render generado para el ítem.
 * @param array $link
 *   El array renderizado del enlace.
 * @param bool $is_active
 *   TRUE si la ruta coincide con la actual.
 * @param bool $open
 *   TRUE si el ítem aparece expandido por defecto.
 */
function hook_taxonomy_section_paths_tree_item_alter(array &$item) {
  // Añadir una clase personalizada si el enlace está activo y abierto
  if ($is_active && $open) {
    $item['#attributes']['class'][] = 'activo-y-visible';
  }

  // O personalizar el margen
  $item['#li_left_margin'] = 'ml-8';
}

/**
 * Permite alterar un enlace antes de ser renderizado en el árbol.
 *
 * @param array &$link
 *   El render array del enlace generado.
 * @param string $label
 *   El texto original del enlace.
 * @param string $url
 *   La ruta interna (ej: /taxonomy/term/5 o alias).
 * @param string|null $langcode
 *   El código del idioma, si se conoce.
 */
function hook_taxonomy_section_paths_build_link_alter(array &$link) {
  // Ejemplo: Añadir icono a enlaces a un vocabulario concreto.
  if (str_starts_with($url, '/blog/relatos')) {
    $link['#markup'] = '<i class="fa fa-feather"></i> ' . $label;
  }
}


/**
 * Permite alterar los parámetros de construcción del render array del tree list.
 *
 * Este hook se ejecuta antes de construir el render array del listado de términos,
 * permitiendo modificar los ítems o los atributos de los contenedores.
 *
 * @param array &$items
 *   Render arrays de los elementos hijos. Cada uno representa un <li>.
 * @param \Drupal\Core\Template\Attribute $attributes
 *   Atributos HTML aplicados al <ul> contenedor principal.
 * @param \Drupal\Core\Template\Attribute $children_attributes
 *   Atributos HTML aplicados al sub-<ul> que contiene a los ítems hijos.
 * @param bool $top_level
 *   TRUE si se trata del nivel más alto del árbol.
 * @param string $vocabulary_id
 *   ID del vocabulario al que pertenece el árbol de términos.
 */
function hook_taxonomy_section_paths_build_list_alter(array &$variables) {
  // Ejemplo: añadir una clase específica si es el árbol principal de 'blog'
  if ($top_level && $vocabulary_id === 'blog') {
    $attributes->addClass('blog-top-tree');
  }

  // Ejemplo: añadir un ítem al final
  $items[] = [
    '#markup' => '<li class="extra-item">Ver todos los artículos</li>',
  ];
}
