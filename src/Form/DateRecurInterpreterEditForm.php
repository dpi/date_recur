<?php

namespace Drupal\date_recur\Form;


use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * X.
 *
 * @method \Drupal\date_recur\Entity\DateRecurInterpreter getEntity
 */
class DateRecurInterpreterEditForm extends EntityForm {

  /**
   * The plugin form factory.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * Creates an instance of WorkflowStateEditForm.
   *
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $pluginFormFactory
   *   The plugin form factory.
   */
  public function __construct(PluginFormFactoryInterface $pluginFormFactory) {
    $this->pluginFormFactory = $pluginFormFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin_form.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\date_recur\Entity\DateRecurInterpreter $dateRecurInterpreter */
    $dateRecurInterpreter = $this->getEntity();

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $dateRecurInterpreter->label(),
    ];

    $plugin = $dateRecurInterpreter->getPlugin();

    $key = 'configure';
    if ($plugin->hasFormClass($key)) {
      $form['configure'] = [
        '#tree' => TRUE,
      ];
      $subform_state = SubformState::createForSubform($form['configure'], $form, $form_state);
      $form['configure'] += $this->pluginFormFactory
        ->createInstance($plugin, $key)
        ->buildConfigurationForm($form['configure'], $subform_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $key = 'configure';
    $plugin = $this->getEntity()->getPlugin();
    if ($plugin->hasFormClass($key)) {
      $subform_state = SubformState::createForSubform($form['configure'], $form, $form_state);
      $this->pluginFormFactory
        ->createInstance($plugin, $key)
        ->validateConfigurationForm($form['configure'], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();

    $key = 'configure';
    $plugin = $entity->getPlugin();
    if ($plugin->hasFormClass($key)) {
      $subform_state = SubformState::createForSubform($form['configure'], $form, $form_state);
      $this->pluginFormFactory
        ->createInstance($plugin, $key)
        ->submitConfigurationForm($form['configure'], $subform_state);
    }

    $entity->save();
    $this->messenger()->addStatus($this->t('Saved the %label interpreter.', [
      '%label' => $entity->label(),
    ]));
  }

}
