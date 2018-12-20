<?php

namespace Drupal\windows_azure_storage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that save azure storage blob setting info.
 */
class WindowsAzureStorageConfigurationForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('state'));
  }

  /**
   * Return form id.
   *
   * @return string
   *   Form id.
   */
  public function getFormId() {
    return 'windows_azure_storage_configuration_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $configs = $this->state->get('windows_azure_storage.credentials');

    $form['account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Name'),
      '#default_value' => $configs['account'],
      '#description' => $this->t('Name of the Windows Azure Storage account.'),
      '#required' => TRUE,
    ];

    $form['primary_key'] = [
      '#type' => 'textfield',
      '#title' => t('Primary Key'),
      '#default_value' => $configs['primary_key'],
      '#description' => t('The primary access key attached to this Windows Azure Storage account.'),
      '#required' => TRUE,
    ];

    $form['blob_container'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Blob container'),
      '#default_value' => $configs['blob_container'],
      '#description' => $this->t('The container attached to this Windows Azure Storage account.'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Check if container name is of the valid format.
   *
   * 1. Container names must start with a letter or number,
   *    and can contain only letters, numbers, and the dash (-) character.
   * 2. Every dash (-) character must be immediately preceded and followed by a
   *    letter or number; consecutive dashes are not permitted in container
   * names.
   * 3. All letters in a container name must be lowercase.
   * 4. Container names must be from 3 through 63 characters long.
   *
   * @link http://msdn.microsoft.com/en-us/library/windowsazure/dd135715.aspx
   * @author James Mover Zhou
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('blob_container'))) {
      $pattern_match = preg_match('/^[a-z0-9](([a-z0-9\-[^\-])){1,61}[a-z0-9]$/', $form_state->getValue('blob_container'));

      if ($pattern_match !== 1) {
        $form_state->setErrorByName('blob_container',
          $this->t('Container names must start with a letter or number, and can contain only letters, numbers, and the dash (-) character.')
        );
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->set('windows_azure_storage.credentials', [
      'account' => $form_state->getValue('account'),
      'primary_key' => $form_state->getValue('primary_key'),
      'blob_container' => $form_state->getValue('blob_container'),
    ]);
    $this->messenger()->addMessage($this->t('Save Success!'), 'status');
  }

}
