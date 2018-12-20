<?php

namespace Drupal\windows_azure_storage\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WindowsAzureStorageDeleteFileForm.
 */
class WindowsAzureStorageDeleteFileForm extends ConfirmFormBase {

  protected $name;

  protected $folder;

  protected $containerName;

  protected $id;

  /**
   * The storage helper service.
   *
   * @var \Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper
   */
  protected $azureStorageHelper;

  /**
   * Class constructor.
   *
   * @param \Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper $azure_storage_helper
   *   WindowsAzureStorageHelper.
   */
  public function __construct(WindowsAzureStorageHelper $azure_storage_helper) {
    $this->azureStorageHelper = $azure_storage_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('windows_azure_storage.storage_helper'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'windows_azure_storage_delete_file';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you true you want to delete this file: %name in container: %container?',
      ['%name' => $this->name, '%container' => $this->containerName]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('windows_azure_storage_list_the_files_query_parameters');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = '', $container = '', $folder = '', $file_name = '') {
    $this->name = $file_name;
    $this->folder = $folder;
    $this->containerName = $container;
    $this->id = $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form['confirm']) {
      if ($this->azureStorageHelper->deleteBlob($this->containerName,
        $this->folder . '/' . $this->name)) {
        if ($this->azureStorageHelper->deleteDb($this->id)) {
          $this->messenger()
            ->addMessage($this->t('The file: %name has been deleted.',
              ['%name' => $this->name]), 'status');
        }
        else {
          $this->messenger()
            ->addMessage($this->t('The file: %name delete fail in DB, please try latter.',
              ['%name' => $this->name]), 'warning');
        }
      }
      else {
        $this->messenger()
          ->addMessage($this->t('The file: %name delete fail, please try latter.',
            ['%name' => $this->name]), 'warning');
      }
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
  }

}
