<?php

namespace Drupal\taxonomy_section_paths\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Taxonomy Section Paths general settings form.
 */
class TaxonomySectionPathsSettingsForm extends ConfigFormBase {

  /**
   * The EntityManager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'taxonomy_section_paths_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['taxonomy_section_paths.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->config('taxonomy_section_paths.settings');
    $bundles = $config->get('bundles') ?? [];

    $generate_node_alias_if_term_empty = $config->get('generate_node_alias_if_term_empty');
    $enable_event_logging = $config->get('enable_event_logging');
    $silent_messages = $config->get('silent_messages');
    $use_batch_for_term_operations = $config->get('use_batch_for_term_operations');

    $vocabularies = Vocabulary::loadMultiple();
    $vocab_options = ['' => $this->t('- Select -')];
    foreach ($vocabularies as $vid => $vocab) {
      $vocab_options[$vid] = $vocab->label();
    }

    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    $form['bundles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Node type settings'),
      '#tree' => TRUE,
    ];

    foreach ($node_types as $bundle_id => $bundle) {
      $vocab_selected = $form_state->getValue(['bundles', $bundle_id, 'vocabulary']) ?? ($bundles[$bundle_id]['vocabulary'] ?? '');
      $form['bundles'][$bundle_id] = [
        '#type' => 'details',
        '#title' => $bundle->label(),
        '#open' => FALSE,
      ];

      // Vocabulario.
      $form['bundles'][$bundle_id]['vocabulary'] = [
        '#type' => 'select',
        '#title' => $this->t('Vocabulary'),
        '#options' => $vocab_options,
        '#default_value' => $vocab_selected,
        '#ajax' => [
          'callback' => '::updateFieldOptions',
          'wrapper' => 'field-wrapper-' . $bundle_id,
          'event' => 'change',
        ],
      ];

      // Campo de referencia (se actualiza vía AJAX).
      $form['bundles'][$bundle_id]['field'] = [
        '#type' => 'select',
        '#title' => $this->t('Reference field'),
        '#prefix' => '<div id="field-wrapper-' . $bundle_id . '">',
        '#suffix' => '</div>',
        '#options' => $this->getReferenceFieldsOptions($bundle_id, $vocab_selected),
        '#default_value' => $bundles[$bundle_id]['field'] ?? '',
        '#validated' => TRUE,
      ];
    }

    $form['generate_node_alias_if_term_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate alias even if the taxonomy term field is empty'),
      '#default_value' => $generate_node_alias_if_term_empty ?? TRUE,
    ];

    $form['enable_event_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable event logging.'),
      '#default_value' => $enable_event_logging ?? TRUE,
    ];

    $form['silent_messages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppresses on-screen messages.'),
      '#default_value' => $silent_messages ?? FALSE,
    ];

    $form['use_batch_for_term_operations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use batch processing for term edit and delete operations.'),
      '#default_value' => $use_batch_for_term_operations ?? TRUE,
      '#description' => $this->t('Enable batch processing to handle large numbers of nodes when editing or deleting taxonomy terms. Recommended for sites with many content items per category to prevent timeouts and improve performance.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to update the field select.
   */
  public function updateFieldOptions(array &$form, FormStateInterface $form_state): array {
    // Get triggering element to determine which bundle changed.
    $trigger = $form_state->getTriggeringElement();
    $bundle_id = $trigger['#parents'][1];
    return $form['bundles'][$bundle_id]['field'];
  }

  /**
   * Get fields that reference the given vocabulary and have cardinality 1.
   */
  private function getReferenceFieldsOptions(string $bundle_id, string $vocabulary_id): array {
    $options = [];

    if (!$vocabulary_id) {
      return $options;
    }

    $fields = $this->entityTypeManager
      ->getStorage('field_config')
      ->loadByProperties(['entity_type' => 'node', 'bundle' => $bundle_id]);

    foreach ($fields as $field) {
      /** @var \Drupal\field\Entity\FieldConfig $field */
      if (
        $field->getType() === 'entity_reference' &&
        $field->getSetting('target_type') === 'taxonomy_term' &&
        $field->getFieldStorageDefinition()->getCardinality() === 1
      ) {
        $handler_settings = $field->getSetting('handler_settings');
        $target_bundles = $handler_settings['target_bundles'] ?? [];
        if (in_array($vocabulary_id, $target_bundles)) {
          $options[$field->getName()] = $field->label();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('taxonomy_section_paths.settings');

    $bundles_config = [];
    foreach ($form_state->getValue('bundles') as $bundle_id => $settings) {
      if (!empty($settings['vocabulary']) && !empty($settings['field'])) {
        $bundles_config[$bundle_id] = [
          'vocabulary' => $settings['vocabulary'],
          'field' => $settings['field'],
        ];
      }
    }

    $config
      ->set('bundles', $bundles_config)
      ->set('generate_node_alias_if_term_empty', $form_state->getValue('generate_node_alias_if_term_empty'))
      ->set('enable_event_logging', $form_state->getValue('enable_event_logging'))
      ->set('silent_messages', $form_state->getValue('silent_messages'))
      ->set('use_batch_for_term_operations', $form_state->getValue('use_batch_for_term_operations'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
