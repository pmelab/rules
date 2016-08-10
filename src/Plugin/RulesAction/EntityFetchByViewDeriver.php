<?php

namespace Drupal\rules\Plugin\RulesAction;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\rules\Context\ContextDefinition;
use Drupal\rules\Plugin\views\display\Rules;
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
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    // Build a lookup dictionary of table names pointing to corresponding
    // entity types. Used to determine which entity type is the result of a
    // given view.
    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($base_table = $entity_type->getBaseTable()) {
        $entity_types[$base_table] = $entity_type;
      }

      if ($data_table = $entity_type->getDataTable()) {
        $entity_types[$data_table] = $entity_type;
      }
    }

    foreach (Views::getApplicableViews('rules') as $data) {
      list($view_id, $display_id) = $data;

      // Fetch the current view applicable view and get it's base table.
      /** @var $view \Drupal\views\Entity\View */
      $view = $this->viewsStorage->load($view_id);
      $table = $view->get('base_table');

      /** @var $entity_type \Drupal\Core\Entity\EntityTypeInterface */
      if ($entity_type = $entity_types[$table] ?: FALSE) {
        // Proceed only, if the view is based on an entity.
        // Prepare views executable and display.
        $views_executable = $view->getExecutable();
        $views_executable->setDisplay($display_id);
        $display = $views_executable->getDisplay();

        // Build the list of derivative definitions if the display is of type
        // "Rules".
        if ($display instanceof Rules) {
          $this->derivatives[$view_id . ':' . $display_id]= [
              'label' => $this->t('Fetch entities from @view - @display', [
                '@view' => $view_id,
                '@display' => $display->display['display_title'],
              ]),
              'view_id' => $view_id,
              'display_id' => $display_id,
              'context' => $display->getRulesContext(),
              'provides' => [
                'entity_fetched' => ContextDefinition::create("entity:" . $entity_type->id())
                  ->setLabel($entity_type->getLabel())
                  ->setMultiple(TRUE)
              ],
          ] + $base_plugin_definition;
        }
      }
    }
    return $this->derivatives;
  }
}
