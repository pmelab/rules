<?php

namespace Drupal\rules\Plugin\RulesAction;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\rules\Context\ContextDefinition;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives EntityFetchByView plugin definitions from views configurations.
 *
 * @see EntityFetchByView
 */
class EntityFetchByViewDeriver extends DeriverBase implements ContainerDeriverInterface  {
  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $viewsStorage;

  /**
   * Array mapping table names to entity types.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityTables = [];

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param string $base_plugin_id
   * @return static
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * EntityFetchByViewDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->viewsStorage = $entity_type_manager->getStorage('view');
    $this->stringTranslation = $string_translation;

    // Build an array of table names pointing to corresponding entity types.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {

      if ($base_table = $entity_type->getBaseTable()) {
        $this->entityTables[$base_table] = $entity_type;
      }

      if ($data_table = $entity_type->getDataTable()) {
        $this->entityTables[$data_table] = $entity_type;
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach (Views::getApplicableViews('rules') as $data) {
      list($view_id, $display_id) = $data;
      $view = $this->viewsStorage->load($view_id);
      $table = $view->get('base_table');
      if (isset($this->entityTables[$table])) {
        $entity_type = $this->entityTables[$table];

        $context = [];

        /** @var $views_executable \Drupal\views\ViewExecutable */
        $views_executable = $view->getExecutable();
        $views_executable->setDisplay($display_id);
        $display = $views_executable->getDisplay();

        foreach ($display->getOption('arguments') as $argument_name => $argument) {
          $label = $argument['admin_label'] ? $argument['admin_label'] : $argument_name;
          $required = !in_array($argument['default_action'], ['ignore', 'default']);
          $type = strpos($argument['validate']['type'], 'entity:') !== FALSE ? "entity:" . explode(':', $argument['validate']['type'])[1] : "string";

          $context[$argument_name] = ContextDefinition::create($type)
            ->setLabel($label)
            ->setRequired($required);
        }

        $this->derivatives[$view_id . ':' . $display_id]= [
            'label' => $this->t('Fetch entities from @view - @display', [
              '@view' => $view_id,
              '@display' => $display->display['display_title'],
            ]),
            'view_id' => $view_id,
            'display_id' => $display_id,
            'context' => $context,
            'provides' => [
              'entity_fetched' => ContextDefinition::create("entity:" . $entity_type->id())
                ->setLabel($entity_type->getLabel())
                ->setMultiple(TRUE)
            ],
          ] + $base_plugin_definition;
      }
    }
    return $this->derivatives;
  }
}
