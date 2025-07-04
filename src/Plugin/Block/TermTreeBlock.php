<?php
// src/Plugin/Block/TermTreeBlock.php

namespace Drupal\taxonomy_section_paths\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy_section_paths\Contract\Service\TermTreeBuilderInterface;



/**
 * @Block(
 *   id = "taxonomy_term_tree_block",
 *   admin_label = @Translation("Taxonomy Term Tree")
 * )
 */
class TermTreeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected TermTreeBuilderInterface $treeBuilder;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TermTreeBuilderInterface $treeBuilder,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    AliasRepositoryInterface $aliasRepository,
    BlockManagerInterface $blockManager,
    CurrentPathStack $currentPathStack
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->treeBuilder = $treeBuilder;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->aliasRepository = $aliasRepository;
    $this->blockManager = $blockManager;
    $this->currentPath = $currentPathStack;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('taxonomy_section_paths.term_tree_builder'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('path_alias.repository'),
      $container->get('plugin.manager.block'),
      $container->get('path.current')
    );
  }

  public function build(): array {

    
    $vocab = $this->configuration['vocabulary'] ?? '';
    $parent_tid = (int) ($this->configuration['parent_tid'] ?? 0);
    $depth_raw = $this->configuration['depth'] ?? NULL;
    $depth = is_numeric($depth_raw) ? (int) $depth_raw : NULL;
    $as_navbar = (bool) $this->configuration['as_navbar'] ?? FALSE;
    $show_branding = (bool) $this->configuration['show_branding'] ?? FALSE;
    $show_logo = (bool) $this->configuration['show_logo'] ?? FALSE;
    $show_site_name = (bool) $this->configuration['show_site_name'] ?? FALSE;
    $show_slogan = (bool) $this->configuration['show_slogan'] ?? FALSE;
    $custom_links = $this->getCustomLinks();
    

    $select_relation = $this->configuration['select_relation'] ?? 'parents';

    //~ kint($this->configuration);

    //Si es nodo, eliminar el último segmento de la url
    // Casilla de representar hijos, representar hermanos
    if ($this->configuration['start_from_url']) {
     
      $langcode = $this->languageManager->getCurrentLanguage()->getId();

      $alias = $this->aliasRepository->lookupBySystemPath($this->currentPath->getPath(), $langcode)['alias'] ?? '';
      $segments = array_values(array_filter(explode('/', $alias)));

      $path = $this->currentPath->getPath();
      $path = explode('/', $path);
      if($path[1] == 'node'){
        array_pop($segments);
        $alias = '/' . implode('/', $segments);
      }

      if ($select_relation == 'parents'){
        $parent_tid = 0;
        $alias_to_match = NULL;
      }
      elseif ($select_relation === 'siblings'){
        array_pop($segments);
        $alias_to_match = '/' . implode('/', $segments);
      }
      elseif ($select_relation === 'children'){
        $alias_to_match = '/' . implode('/', $segments);
      }
      elseif ($select_relation === 'segment'){
        $segment_index = (int) $this->configuration['url_segment_position'];
        if (isset($segments[$segment_index])){
          $alias_to_match = '/' . implode('/', array_slice($segments, 0, $segment_index + 1));
        }
      }
      
      if($alias_to_match){
        $term_tid = $this->getTermTidByAlias($alias_to_match, $langcode);
         if ($term_tid) {
          $parent_tid = $term_tid;
        }
      }
    }
    elseif (!empty($this->configuration['starting_term'])) {
      $parent_tid = $this->configuration['starting_term'];
    }
    else {
      $parent_tid = 0;
    }
    

    $plugin_block = $this->blockManager->createInstance('system_branding_block', []);
    $branding_block = $plugin_block->build();

    $content = $this->treeBuilder->buildTree(
      $vocab,
      $parent_tid,
      $max_depth,
      $custom_links['custom_link_top'],
      $custom_links['custom_link_top_url'],
      $custom_links['custom_link_bottom'],
      $custom_links['custom_link_bottom_url'],
      $as_navbar,
      $show_branding,
      $show_logo,
      $show_site_name,
      $show_slogan
    );

    if(!$content){
      return [];
    }

    $build['content'] = $content;

    $build['content']['#branding'] = $branding_block;

    // Block attributes
    if ($as_navbar) {
      //~ $build['#attributes']['class'][] = 'flex';
      //~ $build['#attributes']['class'][] = 'gap-4';
      //~ $build['#attributes']['class'][] = 'term-navbar';
    }
    else {
      //~ $build['#attributes']['class'][] = 'ml-0 pl-0';
    }

    $build['#attached']['library'][] = 'taxonomy_section_paths/term_tree';

    if($parent_tid){
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($parent_tid);
      if ($term) {
        $build['#title'] = $term->label();
      }
    }


    return $build;
  }
  

  public function defaultConfiguration(): array {
    return [
      'vocabulary' => '',
      'parent_tid' => 0,
      'depth' => NULL,
      'as_navbar' => FALSE,
      'show_branding' => FALSE,
      'show_logo' => FALSE,
      'show_site_name' => FALSE,
      'show_slogan' => FALSE,
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array {

    $vocabularies = $this->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->loadMultiple();
    $options = [];
    foreach ($vocabularies as $vocab) {
      $options[$vocab->id()] = $vocab->label();
    }

    // Primera pestaña: Vocabulary config.
    $form['vocabulary_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuración del vocabulario'),
      '#open' => TRUE,
    ];
  

    $form['vocabulary_config']['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#options' => $options,
      '#default_value' => $this->configuration['vocabulary'],
      '#required' => TRUE,
    ];

    $form['vocabulary_config']['depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum depth'),
      '#default_value' => $this->configuration['depth'],
      '#min' => 1,
      '#description' => $this->t('Leave empty for unlimited depth.'),
    ];

    $form['vocabulary_config']['start_from_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Determinar término inicial desde el alias de la URL'),
      '#default_value' => $this->configuration['start_from_url'] ?? FALSE,
    ];

    $form['vocabulary_config']['select_relation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select relation'),
      '#default_value' => $this->configuration['select_relation'] ?? 'parents',
      '#options' => [
        'parents' => $this->t('Parents'),
        'siblings' => $this->t('Siblings'),
        'children' => $this->t('Children'),
        'segment' => $this->t('From URL segment')
      ],
      '#states' => [
        'visible' => [
          ':input[name="settings[vocabulary_config][start_from_url]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['vocabulary_config']['url_segment_position'] = [
      '#type' => 'number',
      '#title' => $this->t('Posición del segmento de la URL (empezando por 0)'),
      '#default_value' => $this->configuration['url_segment_position'] ?? 0,
      '#states' => [
        'visible' => [
          ':input[name="settings[vocabulary_config][select_relation]"]' => ['value' => 'segment'],
          'and',
          ':input[name="settings[vocabulary_config][start_from_url]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['vocabulary_config']['url_from_position'] = [
      '#type' => 'number',
      '#title' => $this->t('URL from position'),
      '#default_value' => $this->configuration['url_from_position'] ?? 0,
      '#states' => [
        'visible' => [
          ':input[name="settings[vocabulary_config][select_relation]"]' => ['value' => 'parents'],
          'and',
          ':input[name="settings[vocabulary_config][start_from_url]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // O mantener opción manual para fallback:
    $form['vocabulary_config']['starting_term'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Término raíz (si no se usa la URL)'),
      '#target_type' => 'taxonomy_term',
      '#default_value' => !empty($this->configuration['starting_term'])
        ? $this->entityTypeManager->getStorage('taxonomy_term')->load($this->configuration['starting_term'])
        : NULL,
      '#states' => [
        'visible' => [
          ':input[name="settings[vocabulary_config][start_from_url]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Segunda pestaña: Navbar config.
    $form['navbar_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuración de la barra de navegación'),
    ];

    $form['navbar_config']['as_navbar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render as navbar (horizontal layout)'),
      '#default_value' => $this->configuration['as_navbar'],
    ];

    $form['navbar_config']['show_branding'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show branding block'),
      '#default_value' => $this->configuration['show_branding'],
      '#states' => [
        'visible' => [
          ':input[name="settings[navbar_config][as_navbar]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['navbar_config']['branding_content'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="settings[navbar_config][as_navbar]"]' => ['checked' => TRUE],
          ':input[name="settings[navbar_config][show_branding]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['navbar_config']['branding_content']['show_logo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show logo'),
      '#default_value' => $this->configuration['show_logo'],
    ];

    $form['navbar_config']['branding_content']['show_site_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show site name'),
      '#default_value' => $this->configuration['show_site_name'],
    ];

    $form['navbar_config']['branding_content']['show_slogan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show slogan'),
      '#default_value' => $this->configuration['show_slogan'],
    ];

    


    $config = $this->getConfiguration();

    // Tercera pestaña: Custom links.
    $form['custom_links_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Enlaces personalizados'),
    ];
    

    $form['custom_links_config']['custom_link_top'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom top link label'),
      '#default_value' => $config['custom_link_top'] ?? '',
    ];
    $form['custom_links_config']['custom_link_top_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom top link URL'),
      '#default_value' => $config['custom_link_top_url'] ?? '',
    ];

    $form['custom_links_config']['custom_link_bottom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom bottom link label'),
      '#default_value' => $config['custom_link_bottom'] ?? '',
    ];
    $form['custom_links_config']['custom_link_bottom_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom bottom link URL'),
      '#default_value' => $config['custom_link_bottom_url'] ?? '',
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['vocabulary'] = $form_state->getValue('vocabulary_config')['vocabulary'];
    $this->configuration['depth'] = is_numeric($form_state->getValue('vocabulary_config')['depth']) ? (int) $form_state->getValue('vocabulary_config')['depth'] : NULL;
    $this->configuration['start_from_url'] = $form_state->getValue('vocabulary_config')['start_from_url'];
    $this->configuration['select_relation'] = $form_state->getValue('vocabulary_config')['select_relation'];
    $this->configuration['url_segment_position'] = $form_state->getValue('vocabulary_config')['url_segment_position'];
    $this->configuration['url_from_position'] = $form_state->getValue('vocabulary_config')['url_from_position'];
    $this->configuration['starting_term'] = $form_state->getValue('vocabulary_config')['starting_term'];


    $this->configuration['as_navbar'] = $form_state->getValue('navbar_config')['as_navbar'];
    $this->configuration['show_branding'] = $form_state->getValue('navbar_config')['show_branding'];
    $this->configuration['show_logo'] = $form_state->getValue('navbar_config')['branding_content']['show_logo'];
    $this->configuration['show_site_name'] = $form_state->getValue('navbar_config')['branding_content']['show_site_name'];
    $this->configuration['show_slogan'] = $form_state->getValue('navbar_config')['branding_content']['show_slogan'];

    
    $this->configuration['custom_link_top'] = $form_state->getValue('custom_links_config')['custom_link_top'];
    $this->configuration['custom_link_top_url'] = $form_state->getValue('custom_links_config')['custom_link_top_url'];
    $this->configuration['custom_link_bottom'] = $form_state->getValue('custom_links_config')['custom_link_bottom'];
    $this->configuration['custom_link_bottom_url'] = $form_state->getValue('custom_links_config')['custom_link_bottom_url'];
    
  }

  private function getCustomLinks(): array{
    
    $custom_links = [
      'custom_link_top' => $this->configuration['custom_link_top'] ?? NULL,
      'custom_link_top_url' => $this->configuration['custom_link_top_url'] ?? NULL,
      'custom_link_bottom' => $this->configuration['custom_link_bottom'] ?? NULL,
      'custom_link_bottom_url' => $this->configuration['custom_link_bottom_url'] ?? NULL,
    ];

    \Drupal::moduleHandler()->alter('taxonomy_section_paths_custom_links', $custom_links, $this);

    return $custom_links;
  }

    private function getTermTidByAlias(string $alias, string $langcode): ?int {
    // Recorre términos del vocabulario para encontrar el que coincide con el alias
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = $storage->getQuery()->accessCheck(TRUE)->execute();
    $terms = $storage->loadMultiple($tids);
    foreach ($terms as $term) {
      $path = '/taxonomy/term/' . $term->id();
      $term_alias = $this->aliasRepository->lookupBySystemPath($path, $langcode)['alias'] ?? $path;
      if ($term_alias === $alias) {
        return $term->id();
      }
    }

    return NULL;
  }

}
