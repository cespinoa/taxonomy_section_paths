<?php

// src/Service/TermTreeBuilder.php

namespace Drupal\taxonomy_section_paths\Service;

use Drupal\taxonomy_section_paths\Contract\Service\TermTreeBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Template\Attribute;


/**
 * Servicio que construye un árbol jerárquico de términos con alias.
 */
class TermTreeBuilder implements TermTreeBuilderInterface {

  protected $termStorage;
  protected AliasRepositoryInterface $aliasRepository;
  protected RendererInterface $renderer;
  protected bool $as_navbar;
  protected bool $show_branding;
  protected bool $show_logo;
  protected bool $show_site_name;
  protected bool $show_slogan;

   public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AliasRepositoryInterface $aliasRepository,
    RendererInterface $renderer,
    CurrentPathStack $currentPathStack,
    RouteMatchInterface $routeMatch,
    LanguageManagerInterface $languageManager
  ) {
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->aliasRepository = $aliasRepository;
    $this->renderer = $renderer;
    $this->currentPath = $currentPathStack->getPath();
    $this->routeMatch = $routeMatch;
    $this->languageManager = $languageManager;
  }

  /**
   * Construye un render array con un árbol jerárquico de términos.
   *
   * @param string $vocabulary_id
   *   ID del vocabulario.
   * @param int $parent
   *   TID raíz (0 para raíz del vocabulario).
   * @param int|null $max_depth
   *   Profundidad máxima (null = sin límite).
   *
   * @return array
   *   Render array del árbol.
   */
  public function buildTree(
    string $vocabulary_id,
    int $parent_tid = 0,
    ?int $max_depth = NULL,
    $custom_link_top,
    $custom_link_top_url,
    $custom_link_bottom,
    $custom_link_bottom_url,
    $as_navbar,
    $show_branding,
    $show_logo,
    $show_site_name,
    $show_slogan
  ): ?array {

    $this->as_navbar = $as_navbar;
    $this->show_branding = $show_branding;
    $this->show_logo = $show_logo;
    $this->show_site_name = $show_site_name;
    $this->show_slogan = $show_slogan;

    $flat_tree = $this->termStorage->loadTree($vocabulary_id, $parent_tid, $max_depth, TRUE);
    if(empty($flat_tree)){
      return NULL;
    }

    $tree_index = [];

    // Indexamos por TID.
    foreach ($flat_tree as $term) {
      $tree_index[$term->id()] = [
        'term' => $term,
        'children' => [],
      ];
    }

    // Estructura jerárquica.
    $root = [];

    foreach ($tree_index as $tid => &$entry) {
      $parent_tid = $entry['term']->parent->target_id ?? 0;
      if ($parent_tid && isset($tree_index[$parent_tid])) {
        $tree_index[$parent_tid]['children'][] = &$entry;
      } else {
        $root[] = &$entry;
      }
    }
    $root_copy = $root;

    $first_level = 1;
    $term = array_shift($root_copy)['term'];
    
    
    if ($term){
      $path = '/taxonomy/term/' . $term->id();
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      $alias_data = $this->aliasRepository->lookupBySystemPath($path, $langcode);
      $alias = $alias_data['alias'] ?? $path;
      $first_level = count(explode('/', ltrim($alias, '/')));      
    }
    
    $top_level = TRUE;
    
    return $this->buildRenderTree(
      $root,
      $first_level,
      $custom_link_top,
      $custom_link_top_url,
      $custom_link_bottom,
      $custom_link_bottom_url,
      $top_level,
      $vocabulary_id,
      $as_navbar
    );
  }

  /**
   * Convierte un árbol de términos en un render array.
   */
  private function buildRenderTree(
    array $tree,
    $first_level,
    $custom_link_top,
    $custom_link_top_url,
    $custom_link_bottom,
    $custom_link_bottom_url,
    $top_level = TRUE,
    $vocabulary_id,
    $as_navbar,
  ): array {

    $level = FALSE;
    if($top_level){
      $level = TRUE;
    }
    
    $items = [];

    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    if (!empty($custom_link_top)) {
      $items[] = $this->buildCustomItem($custom_link_top, $custom_link_top_url, $langcode, 'custom-top');
    }

    foreach ($tree as $entry) {
      $term = $entry['term'];
      $path = '/taxonomy/term/' . $term->id();
      $alias_data = $this->aliasRepository->lookupBySystemPath($path, $langcode);
      $alias = $alias_data['alias'] ?? $path;

      

      $li_left_margin = 'ml-2 mb-0';
      $level = count(explode('/', ltrim($alias, '/')));
      if($level = $first_level){
        $li_left_margin = 'm-0';
      }
      

      $isActive = $this->isPathActive($alias, $langcode);
      $hasActiveDescendant = false;

      // Recursivo.
      $child_render = '';
      if (!empty($entry['children'])) {
        $top_level = FALSE;
        $child_render = $this->buildRenderTree($entry['children'], $hasActiveDescendant, NULL,NULL,NULL,NULL, $top_level, $vocabulary_id, $as_navbar);
        $child_render = $this->renderer->renderPlain($child_render);
      }

      $link = $this->buildLink($term->label(), $alias, $langcode, $isActive);
      
      $open = $isActive || $hasActiveDescendant;
      if($as_navbar){
        $open = FALSE;
      }
      $items[] =$this->buildItem($link, $link_classes, $isActive, $open, $li_left_margin, $child_render);

      if ($isActive || $hasActiveDescendant) {
        $foundActive = true;
      }
    }
    // Bottom custom link
    if (!empty($custom_link_bottom)) {
      $items[] = $this->buildCustomItem($custom_link_bottom, $custom_link_bottom_url, $langcode, 'custom-bottom');
    }
    
    $children_attributes = NULL;
    $attributes = NULL;
    if(!$level){
      $children_attributes = new Attribute();
      $children_attributes
        ->addClass('ml-4')
        ->addClass('p-0')
        ->addClass('term-tree');
      if ($as_navbar){
        $children_attributes
          ->addClass('absolute left-0 top-full mt-2 p-2 bg-white shadow-lg border rounded z-20 min-w-max');
      }
    }
    else {
      $attributes = new Attribute();
      $attributes
        ->addClass('p-0')
        ->addClass('term-tree');
      if ($as_navbar){
        $attributes
        //~ ->addClass('container mx-auto relative flex w-full flex-wrap items-center  bg-zinc-100 py-2 shadow-dark-mild dark:bg-neutral-700 lg:py-4 px-4');
          ->addClass('hidden md:!flex items-center gap-2 ml-auto justify-end');
          
      }
    }

    
      
    return $this->buildList($items, $attributes, $children_attributes, $level, $vocabulary_id);
    
  }

  private function buildList($items, $attributes, $children_attributes, $level, $vocabulary_id){

    $list = [
      '#theme' =>  'taxonomy_section_paths_tree_list',
      '#items' => $items,
      '#attributes' => $attributes,
      '#children_attributes' => $children_attributes,
      '#top_level' => (bool) $level,
      '#vocabulary_id' => $vocabulary_id,
      '#as_navbar' => (bool) $this->as_navbar,
      '#show_branding' => (bool) $this->show_branding,
      '#show_logo' => (bool) $this->show_logo,
      '#show_site_name' => (bool) $this->show_site_name,
      '#show_slogan' => (bool) $this->show_slogan,
      '#attached' => [
        'library' => ['taxonomy_section_paths/term_tree'],
      ],
    ];

    // Permite que otros módulos alteren la lista.
    \Drupal::moduleHandler()->alter('taxonomy_section_paths_build_list', $list);

    return $list;
  }

  private function isPathActive(string $alias, $langcode): bool {

    $alias_segments = explode('/', ltrim($alias, '/'));
    $current = $this->aliasRepository->lookupBySystemPath($this->currentPath, $langcode)['alias'] ?? '';
    $current_segments = explode('/', ltrim($current, '/'));

    return array_slice($current_segments, 0, count($alias_segments)) === $alias_segments;
  }

  private function buildCustomItem(string $label, string $url, string $langcode, string $class): array {

    $isActive = FALSE;
    if ($url === $this->currentPath){
      $isActive = TRUE;
    }
    else{
      $alias = $this->aliasRepository->lookupBySystemPath($this->currentPath, $langcode)['alias'] ?? NULL;
      if($alias === $url){
        $isActive = TRUE;
      }
    }
    
    $link = $this->buildLink($label, $url, $langcode, $isActive);
    $li_left_margin = 'm-0';
    $open = FALSE;
    $children = [];
    return $this->buildItem($link, $link_classes, $isActive, $open, $li_left_margin, $children);
    
  }

  /**
   * Construye un render array para un enlace con posibilidad de alteración.
   */
  protected function buildLink(string $label, string $url, ?string $langcode = NULL, bool $isActive): array {
    $url_object = Url::fromUri('internal:' . $url, ['language' => $langcode ? \Drupal::languageManager()->getLanguage($langcode) : NULL]);
    $link = Link::fromTextAndUrl($label, $url_object)->toRenderable();

    $link_classes = ['w-100'];
    if ($isActive){
      $link_classes[] = 'active';
      $link['#attributes']['aria-current'] = 'page';
    }

    if ($this->as_navbar){
      $link_classes[] = 'font-medium hover:text-red-600';
    }


    // Añade clases por defecto, si deseas.
    $link['#attributes']['class'] = $link_classes;
    $link['#attributes']['class'][] = 'relative after:bg-black after:absolute after:h-1 after:w-0 after:bottom-0 after:left-0 hover:after:w-full after:transition-all after:duration-300 cursor-pointer';
    if($this->as_navbar){
      $link['#attributes']['class'][] = 'mr-8';
    }


    // Permite que otros módulos alteren el enlace.
    \Drupal::moduleHandler()->alter('taxonomy_section_paths_build_link', $link);

    return $link;
  }


  private function buildItem($link, $link_classes, $isActive, $open, $li_left_margin, $children){
    $attributes = new Attribute();
    $attributes->addClass('term-item');
    
    $item = [
      '#theme' => 'taxonomy_section_paths_tree_item',
      '#label' => $this->renderer->renderPlain($link),
      '#children' => $children,
      '#active' => $isActive,
      '#open' => $open,
      '#attributes' => $attributes,
      '#li_left_margin' => $li_left_margin,
    ];
    
    \Drupal::moduleHandler()->alter('taxonomy_section_paths_tree_item', $item);
    return $item;
  }




}
