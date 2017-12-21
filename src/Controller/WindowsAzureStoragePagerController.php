<?php
/**
 * A class for get a list from DB.
 * 
 * @author DylanLi
 */
namespace Drupal\windows_azure_storage\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class WindowsAzureStoragePagerController extends ControllerBase {
	
  public function queryParameters() {
    $header = array(
      'folder' => $this->t('Folder Name'),
      'name' => $this->t('Blob Name(File name)'),
      'url' => $this->t('Image'),
      'operations' => $this->t('Delete'),
  	);
    $query = db_select('windows_azure_storage_file', 'azure_file')->extend('Drupal\Core\Database\Query\PagerSelectExtender')->element(0);
    $query->fields('azure_file', array('id', 'container', 'folder', 'name' ,'url'));
    $result = $query
      ->limit(5)
      ->orderBy('azure_file.id')
      ->execute();
    $rows = array();
    while ($row = $result->fetchAssoc()) {
      $rows[] = array(
        'data' => array(
          $row['folder'],
          $row['name'],
          $this->t('<img src="' . $row['url'] . '" width="60" height="60" />'),
          \Drupal::l('Delete', url::fromRoute('windows_azure_storage_delete_file', ['id' => $row['id'], 'container' => $row['container'], 'folder' => $row['folder'], 'file_name' => $row['name']])),
        )
      );
    }
    
	  $build['pager_table_azure'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t("There are no file stored in Azure storage blob"),
    );
	
    $build['pager_pager_pager'] = array(
      '#type' => 'pager',
      '#element' => 0,
      '#pre_render' => ['Drupal\windows_azure_storage\Controller\WindowsAzureStoragePagerController::showPagerCacheContext',]
    );
	
    return $build;
  }
	
  /**
   * #pre_render callback for #type => pager that shows the pager cache context.
   */
  public static function showPagerCacheContext(array $pager) {
    return $pager;
  }
}

?>