<?php
/**
 * @file
 * Contains \Drupal\windows_azure_storage\Form\AzureStorageBblobForm.
 * @author DylanLi
 */
namespace Drupal\windows_azure_storage\Form;
 
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\windows_azure_storage\Common\WindowsAzureStorageConn;
 
/**
 * Defines a form that save azure storage blob setting info
 */
class WindowsAzureStorageConfigurationForm extends FormBase{
	
	protected $azure_configuration = false;
  /**
	 * {@inherithoc}
	 */
  public function getFormId(){
		return 'windows_azure_storage_configuration_form';
	}
    
  /**
	 * {@inherithoc}
	 */
  public function buildForm(array $form, FormStateInterface $form_state){
  	$azure_conn = new WindowsAzureStorageConn();
  	$this->azure_configuration = $azure_conn->getWindowsAzureStorageConfiguration();
    
	  $form['account'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Account Name'),
	  	'#default_value' => $this->t((string)$this->azure_configuration['account']),
      '#description' => $this->t('Name of the Windows Azure Storage account.'),
      '#required' => TRUE,
    );
    
    $form['primary_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Primary Key'),
    	'#default_value' => $this->t((string)$this->azure_configuration['primary_key']),
      '#description' => t('The primary access key attached to this Windows Azure Storage account.'),
      '#required' => TRUE,
    );
    
    $form['blob_container'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Blob container'),
    	'#default_value' => $this->t((string)$this->azure_configuration['blob_container']),
      '#description' => $this->t('The container attached to this Windows Azure Storage account.'),
      '#required' => TRUE,
    );
    
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );
    
		return $form;
	}
    
    
  /**
  * Check if container name is of the valid format.
  * 
  * 1. Container names must start with a letter or number, 
  *    and can contain only letters, numbers, and the dash (-) character.
  * 2. Every dash (-) character must be immediately preceded and followed by a 
  *    letter or number; consecutive dashes are not permitted in container names.
  * 3. All letters in a container name must be lowercase.
  * 4. Container names must be from 3 through 63 characters long.
  * 
  * @link http://msdn.microsoft.com/en-us/library/windowsazure/dd135715.aspx
  * @author James Mover Zhou
  */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  	if(!empty($form_state->getValue('blob_container')))
  	{
  		$pattern_match = preg_match('/^[a-z0-9](([a-z0-9\-[^\-])){1,61}[a-z0-9]$/', $form_state->getValue('blob_container'));
  		if($pattern_match !==1)
  		{
  			$form_state->setErrorByName('blob_container', $this->t('Container names must start with a letter or number, and can contain only letters, numbers, and the dash (-) character.'));
  		}
  	}
  	
  	parent::validateForm($form, $form_state);
  }
  
  /**
	 * {@inheritdoc}
	 */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  	$result = false;
  	if(is_array($this->azure_configuration)){
  		$update = db_update('windows_azure_storage')
  		  ->condition('name', 'windowsazurestorage')
  		  ->fields(array(
            'account' => $form_state->getValue('account'),
            'primary_key' => $form_state->getValue('primary_key'),
            'blob_container' => $form_state->getValue('blob_container')))
          ->execute();
  		  $result = true;
  	}else{
  		$id = db_insert('windows_azure_storage')
  		  ->fields(array(
  				'name' => 'windowsazurestorage',
  				'account' => $form_state->getValue('account'),
  				'primary_key' => $form_state->getValue('primary_key'),
  				'blob_container' => $form_state->getValue('blob_container')))
  		  ->execute();
  		$result = true;
  	}
   	if($result){
   		drupal_set_message($this->t('Save Success!'));
   	}else{
   		drupal_set_message($this->t('Save Faild!'), 'error');
   	}
  }    
}
?>