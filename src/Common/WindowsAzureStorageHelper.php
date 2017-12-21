<?php
/**
 * Helper class for Windows Azure Storage.
 * 
 * @author DylanLi
 */
namespace Drupal\windows_azure_storage\Common;

require_once drupal_get_path('module', 'windows_azure_storage') . '/lib/vendor/autoload.php';

use Drupal\windows_azure_storage\Common\WindowsAzureStorageConn;
USE WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Blob\Models\ListBlobsOptions;


class WindowsAzureStorageHelper{
	
	protected $client = null;
	public $container_name = '';
	public $account_name = '';
	
  public function __construct(){
    $azure_conn = new WindowsAzureStorageConn();
    $this->container_name = $azure_conn->getWindowsAzureStorageConfiguration()['blob_container'];
    $this->account_name = $azure_conn->getWindowsAzureStorageConfiguration()['account'];
    $this->client = $this->getAzureStorageClient($azure_conn->getWindowsAzureStorageConfiguration());
  }
  
  /**
   * Helper function to get Azure Storage Blob Client.
   * 
   * @param array $azure_configuration
   * @return \WindowsAzure\Common\WindowsAzure\Blob\Internal\IBlob
   */
  protected function getAzureStorageClient($azure_configuration){
  	if(isset($azure_configuration)){
  		$connectionString = 'DefaultEndpointsProtocol=https;AccountName=' . $azure_configuration['account'] . ';AccountKey=' . $azure_configuration['primary_key'];
  		$client = ServicesBuilder::getInstance()->createBlobService($connectionString);
  	}
  	
  	\Drupal::logger('WAS')->notice("Client initializing...");
  	return $client;
  }
  
  /**
   * Helper function to upload blob in container.
   * 
   * @param string $container
   * @param string $blob_name
   * @param unknown $blob_content
   * @return boolean
   */
  public function uploadBlob($container,$blob_name,$blob_content){
    $result = true;
    try {
      $this->client->createBlockBlob($container,$blob_name,$blob_content);
    } catch (Exception $e) {
        $result = false;
    }
    \Drupal::logger('WAS')->notice("Client Uploading...");
    
  	return $result;
  }
  
  /**
   * Helper function to delete file from Azure Storage Blob Container.
   * 
   * @param string $container
   * @param string $blob_name
   * @return true mean delete sucess.
   */
  public function deleteBlob($container,$blob_name) {
  	$result = true;
  	try {
      $this->client->deleteBlob($container,$blob_name);
  	} catch (ServiceException $e) {
  		$result = false;
  	}
  	return $result;
  }
  
  /**
   * Helper function to download the blob.
   * 
   * @param string $container
   * @param string $blob_name
   */
  public function downloadBlob($container,$blob_name){
    try {
      $blob = $this->client->getBlob($container,$blob_name);
      $result = fpassthru($blob->getContentStream());
      if ($result !== false) {
      	return $result;
      }
      else {
      	return false;
      }
    } catch (Exception $e) {
        return false;
    }
  }
  
  /**
   * Helper function to get blob list.
   * 
   * @param string $container Container name
   * @param Models\ListBlobsOptions $options   The optional parameters.
   * @return Blob list
   */
  public function listBlobs($container,$options = null){
  	try {
  		$blob_list = $this->client->listBlobs($container,$options);
  		$blobs = $blob_list->getBlobs();
	  	} catch (ServiceException $e) {
	  		$code = $e->getCode();
	  		$error_message = $e->getMessage();
	  		echo $code . ":" . $error_message . "<br />";
	  	}
	  	
	  	\Drupal::logger('WAS')->notice("Client Listing...");
	  	
	  	return $blobs;
  }
  
  /**
   * Helper function to check if container exists.
   * @param string $container
   */
  public function containerExist($container){
  	$containerExists = false;
  	$listContainersResult = $this->listContainers();
  	foreach ($listContainersResult->getContainers() as $item) {
  	  if($item->getName() == $container){
  	    $containerExists = true;
  	  }
  	}
  	return $containerExists;
  }
  
  /**
   * Helper function to insert a record to db when the file has been uploaded.
   * @param string $container
   * @param string $folder
   * @param string $name
   * @param string $url
   */
  public function insertDB($container, $folder, $name, $url ){
    $result = db_insert('windows_azure_storage_file')
      ->fields(array(
        'container' => $container,
        'folder' => $folder,
        'name' => $name,
        'url' => $url,
      ))
      ->execute();
    if(isset($result)){
      	return true;
    }else {
      return false;
    }
  }
  
  /**
   * Helper function to delete a record from db when the file has been deleted.
   * @param string $id
   */
  public function deleteDB($id){
    $result = db_delete('windows_azure_storage_file')
      ->condition('id', $id)
      ->execute();
    if(isset($result)){
      return true;
    }else {
      return false;
    }
  }
  
  /**
   * Helper function to get list the containers in account.
   */
  public function listContainers(){
  	return $this->client->listContainers();
  }
  
  /**
   * Helper function to get Container name.
   */
  public function getContainerName(){
    return $this->container_name;
  }
  
  /**
   * Helper function to get Account name.
   */
  public function getAccountName(){
  	return $this->account_name;
  }
  
} 

?>