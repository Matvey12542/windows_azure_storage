<?php
/**
 * @file
 * Contains \Drupal\azure_storage_blob\Form\WindowsAzureStorageDeleteFileForm.
 * @author DylanLi
 */
namespace Drupal\windows_azure_storage\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper;


class WindowsAzureStorageDeleteFileForm extends ConfirmFormBase{

  protected $name;
  protected $folder;
  protected $container_name;
  protected $id;
	
  /**
   * {@inherithoc}
   */
  public function getFormId(){
	  return 'windows_azure_storage_delete_file';
  }
	
	/**
   * {@inherithoc}
   */
  public function getQuestion(){
	  return $this->t('Are you true you want to delete this file: %name in container: %container?', array('%name' => $this->name,'%container' => $this->container_name));	
  }
	
  /**
   * {@inherithoc}
   */
  public function getConfirmText(){
    return $this->t('Delete');
  }
	
  /**
   * {@inherithoc}
   */
  public function getCancelUrl(){
    return Url::fromRoute('windows_azure_storage_list_the_files_query_parameters');
  }
	
  /**
   * {@inherithoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = '', $container = '', $folder = '', $file_name = ''){
  	$this->name = $file_name;
  	$this->folder = $folder;
  	$this->container_name = $container;
  	$this->id = $id;
    return parent::buildForm($form, $form_state);
  }
	
  /**
   * {@inherithoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    if($form['confirm']){
      $azure_storage_helper = new WindowsAzureStorageHelper();
      if($azure_storage_helper->deleteBlob($this->container_name, $this->folder . '/' . $this->name)){
        if($azure_storage_helper->deleteDB($this->id)){
          drupal_set_message($this->t('The file: %name has been deleted.', array('%name' => $this->name)));
        }else{
          drupal_set_message($this->t('The file: %name delete fail in DB, please try latter.', array('%name' => $this->name)),'warning');
        }
      }else{
        drupal_set_message($this->t('The file: %name delete fail, please try latter.', array('%name' => $this->name)),'warning');
      }
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
  }
}

?>