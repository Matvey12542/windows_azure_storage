<?php

namespace Drupal\windows_azure_storage\Common;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Exception;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

/**
 * Class WindowsAzureStorageHelper.
 */
class WindowsAzureStorageHelper {

  /**
   * Storage client.
   *
   * @var \MicrosoftAzure\Storage\Blob\BlobRestProxy
   */
  protected $client = NULL;

  /**
   * Container Name.
   *
   * @var string
   */
  public $containerName = '';

  /**
   * Account name.
   *
   * @var string
   */
  public $accountName = '';

  /**
   * Storage configuration parameters.
   *
   * @var array
   *   List of parameters.
   */
  protected $storageConfigurations = [];

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   States.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   */
  public function __construct(StateInterface $state, LoggerChannelFactoryInterface $logger_factory) {
    $this->state = $state;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Helper function to get azure storage configurations.
   *
   * @return array
   *   List of parameters.
   */
  public function getStorageConfigurations() {
    return $this->state->get('windows_azure_storage.credentials');
  }

  /**
   * Helper function to get Azure Storage Blob Client.
   *
   * @return \MicrosoftAzure\Storage\Blob\BlobRestProxy|null
   *   Return storage client.
   */
  protected function getAzureStorageClient() {
    if (!empty($this->client)) {
      return $this->client;
    }

    // Init Client.
    $azure_configuration = $this->getStorageConfigurations();
    if (empty($azure_configuration)) {
      $this->loggerFactory
        ->get('WAS')
        ->notice("Failed to init Azure Storage Client cause Azure Configuration is empty.");

      return NULL;
    }

    $connectionString = implode([
      'DefaultEndpointsProtocol=https;AccountName=',
      $azure_configuration['account'],
      ';AccountKey=',
      $azure_configuration['primary_key'],
    ]);

    $this->client = BlobRestProxy::createBlobService($connectionString);

    return $this->client;
  }

  /**
   * Helper function to upload blob in container.
   *
   * @param string $container
   *   Container.
   * @param string $blob_name
   *   Blob name.
   * @param mixed $blob_content
   *   Blob content.
   *
   * @return bool
   *   Return result.
   */
  public function uploadBlob($container, $blob_name, $blob_content) {
    $result = TRUE;
    try {
      $parts = explode('.', $blob_name);
      $ext = array_pop($parts);
      $mime_type = NULL;
      if ($ext == 'css') {
        $mime_type = new CreateBlobOptions();
        $mime_type->setContentType('text/css');
      }
      elseif ($ext == 'js') {
        $mime_type = new CreateBlobOptions();
        $mime_type->setContentType('application/javascript');
      }

      $client = $this->getAzureStorageClient();
      if (empty($client)) {
        throw new \Exception("AzureBlob client is not available", 1);
      }
      $client->createBlockBlob($container, $blob_name, $blob_content, $mime_type);
    }
    catch (Exception $e) {
      $result = FALSE;
    }

    return $result;
  }

  /**
   * Helper function to upload blob in container.
   *
   * @param string $blob_name
   *   Blob name.
   * @param string $blob_content
   *   Blob content.
   *
   * @return bool
   *   Return result.
   */
  public function uploadFile($blob_name, $blob_content) {
    $result = TRUE;
    try {
      $this->getAzureStorageClient()
        ->createBlockBlob($this->getContainerName(), $blob_name, $blob_content);
    }
    catch (Exception $e) {
      $result = FALSE;
    }

    return $result;
  }

  /**
   * Helper function to delete file from Azure Storage Blob Container.
   *
   * @param string $container
   *   Container object.
   * @param string $blob_name
   *   Blob name.
   *
   * @return bool
   *   True mean delete sucess.
   */
  public function deleteBlob($container, $blob_name) {
    $result = TRUE;
    try {
      $this->getAzureStorageClient()->deleteBlob($container, $blob_name);
    }
    catch (ServiceException $e) {
      $result = FALSE;
    }
    return $result;
  }

  /**
   * Helper function to download the blob.
   *
   * @param string $container
   *   Container object.
   * @param string $blob_name
   *   Blob name.
   *
   * @return resource|bool
   *   Return resource.
   */
  public function downloadBlob($container, $blob_name) {
    try {
      $blob = $this->getAzureStorageClient()->getBlob($container, $blob_name);
      $result = $blob->getContentStream();
      return $result;
    }
    catch (Exception $e) {
      return FALSE;
    }
  }

  /**
   * Helper function to get blob list.
   *
   * @param string $container
   *   Container name.
   * @param \MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions $options
   *   The optional parameters.
   *
   * @return \MicrosoftAzure\Storage\Blob\Models\Blob[]
   *   Return blob list.
   */
  public function listBlobs($container, ListBlobsOptions $options = NULL) {
    $blobs = [];
    try {
      $blob_list = $this->getAzureStorageClient()->listBlobs($container, $options);
      $blobs = $blob_list->getBlobs();
    }
    catch (Exception $e) {
      $code = $e->getCode();
      $error_message = $e->getMessage();
      echo $code . ':' . $error_message . '<br />';
    }

    return $blobs;
  }

  /**
   * Helper function to check if container exists.
   *
   * @param string $container
   *   Container.
   *
   * @return bool
   *   Return result.
   */
  public function containerExist($container) {
    $containerExists = FALSE;
    $listContainersResult = $this->listContainers();
    foreach ($listContainersResult->getContainers() as $item) {
      if ($item->getName() == $container) {
        $containerExists = TRUE;
      }
    }

    return $containerExists;
  }

  /**
   * Helper function to insert a record to db when the file has been uploaded.
   *
   * @param string $container
   *   Container object.
   * @param string $folder
   *   Folder path.
   * @param string $name
   *   File name.
   * @param string $url
   *   Url string.
   *
   * @return bool
   *   Return result.
   *
   * @throws \Exception
   */
  // @todo check if need delete.
  public function insertDb($container, $folder, $name, $url) {
    $result = db_insert('windows_azure_storage_file')
      ->fields([
        'container' => $container,
        'folder'    => $folder,
        'name'      => $name,
        'url'       => $url,
      ])
      ->execute();

    return isset($result);
  }

  /**
   * Helper function to delete a record from db when the file has been deleted.
   *
   * @param string $id
   *   Identifier.
   *
   * @return bool
   *   REturn result.
   */
  public function deleteDb($id) {
    $result = db_delete('windows_azure_storage_file')
      ->condition('id', $id)
      ->execute();

    return isset($result);
  }

  /**
   * Helper function to get list the containers in account.
   */
  public function listContainers() {
    return $this->getAzureStorageClient()->listContainers();
  }

  /**
   * Helper function to get Container name.
   */
  public function getContainerName() {
    if (!empty($this->containerName)) {
      return $this->containerName;
    }

    // Init from configuration.
    $this->containerName = $this->getStorageConfigurations()['blob_container'];
    return $this->containerName;
  }

  /**
   * Helper function to get Account name.
   */
  public function getAccountName() {
    if (!empty($this->accountName)) {
      return $this->accountName;
    }

    // Init from configuration.
    $this->accountName = $this->getStorageConfigurations()['account'];
    return $this->accountName;
  }

  /**
   * Helper function to check if blob exists.
   *
   * @param string $blob_name
   *   Blob name.
   *
   * @return bool
   *   TRUE if blob exists.
   */
  public function blobExists($blob_name) {
    try {
      $blob_props = $this->getBlobProperties($blob_name);

      return !empty($blob_props);
    }
    catch (Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Helper function for get blob properties.
   *
   * @param string $blob_name
   *   Blob name.
   *
   * @return bool|\MicrosoftAzure\Storage\Blob\Models\GetBlobPropertiesResult
   *   Return blob properties.
   */
  public function getBlobProperties($blob_name) {
    try {
      $client = $this->getAzureStorageClient();

      if (empty($client)) {
        return FALSE;
      }

      return $client->getBlobProperties($this->getContainerName(), $blob_name);
    }
    catch (Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Helper function to check if path is a directory.
   *
   * @param string $uri
   *   File uri.
   *
   * @return bool
   *   TRUE if path is a directory.
   */
  public function isDirectory($uri) {
    return $this->blobExists($uri . '/.placeholder');
  }

}
