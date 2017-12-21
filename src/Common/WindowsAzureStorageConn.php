<?php
/**
 * @author DylanLi
 */
namespace Drupal\windows_azure_storage\Common;

class WindowsAzureStorageConn{

  /**
	 * Get Windows Azure Storage Configuration from database.
	 */
  public function getWindowsAzureStorageConfiguration(){
	  return db_select('windows_azure_storage','azure')
      ->fields('azure',array('account','primary_key','blob_container'))
      ->condition('azure.name','windowsazurestorage')
      ->execute()
      ->fetchAssoc();
	}
}

?>