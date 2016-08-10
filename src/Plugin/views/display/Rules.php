<?php
namespace Drupal\rules\Plugin\views\display;

use Drupal\rules\Context\ContextDefinition;
use Drupal\views\Annotation\ViewsDisplay;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * @ViewsDisplay(
 *   id = "rules",
 *   title = @Translation("Rules"),
 *   admin = @Translation("Rules entity source"),
 *   help = @Translation("Provide views results to rules workflows."),
 *   theme = "views_view",
 *   register_theme = FALSE,
 *   uses_menu_links = FALSE,
 *   rules = TRUE
 * )
 */
class Rules extends DisplayPluginBase {
  /**
   * {@inheritdoc}
   */
  protected $usesAJAX = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesPager = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesAttachments = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesAreas = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesMore = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Force the style plugin to 'entity_reference_style' and the row plugin to
    // 'fields'.
    $options['style']['contains']['type'] = array('default' => 'rules');
    $options['defaults']['default']['style'] = FALSE;

    // Set the display title to an empty string (not used in this display type).
    $options['title']['default'] = '';
    $options['defaults']['default']['title'] = FALSE;

    return $options;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::optionsSummary().
   *
   * Disable 'cache' and 'title' so it won't be changed.
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    unset($options['title']);
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'rules';
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    return $this->view->render($this->display['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (!empty($this->view->result) && $this->view->style_plugin->evenEmpty()) {
      return $this->view->style_plugin->render($this->view->result);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function usesExposed() {
    return FALSE;
  }

  /**
   * Build a list of rules context definitions based on the defined views
   * contextual arguments.
   *
   * @return \Drupal\rules\Context\ContextDefinitionInterface[]
   */
  public function getRulesContext() {
    $context = [];

    foreach ($this->getOption('arguments') as $argument_name => $argument) {
      // Use the admin title as context label if possible.
      $label = $argument['admin_label'] ?: $argument_name;

      // If the view is configured to display all items or has a configured
      // default value for this argument, don't mark the context as required.
      $required = !in_array($argument['default_action'], ['ignore', 'default']);

      // Default type for arguments is string.
      $type = 'string';

      // Check if views argument validation is configured for a specific entity
      // type. Use this type as context type definition.
      if (strpos($argument['validate']['type'], 'entity:') !== FALSE) {
        $type = $argument['validate']['type'];
      }

      $context[$argument_name] = ContextDefinition::create($type)
        ->setLabel($label)
        ->setRequired($required);
    }

    return $context;
  }
}