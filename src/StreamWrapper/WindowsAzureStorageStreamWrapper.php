<?php

namespace Drupal\windows_azure_storage\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\Url;
use Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A new Stream Wrapper of Windows Azure Storage.
 */
class WindowsAzureStorageStreamWrapper implements StreamWrapperInterface {

  /**
   * Instance URI (current filename)
   *
   * @var string
   */
  protected $uri;

  /**
   * Temporary filename.
   *
   * @var string
   */
  protected $tempFilename = NULL;

  /**
   * Temporary file handle.
   *
   * @var string
   */
  protected $tempFileHandle = NULL;

  /**
   * Write mode.
   *
   * @var bool
   */
  protected $writeMode = FALSE;

  /**
   * The storage helper service.
   *
   * @var \Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper
   */
  protected $azureHelper;

  /**
   * Class constructor.
   *
   * @param \Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper $azure_storage_helper
   *   WindowsAzureStorageHelper.
   */
  public function __construct(WindowsAzureStorageHelper $azure_storage_helper = NULL) {
    $this->azureHelper = $azure_storage_helper ?? \Drupal::service('windows_azure_storage.storage_helper');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('windows_azure_storage.storage_helper'));
  }

  /**
   * Returns the type of stream wrapper.
   *
   * @return int
   *   Type flag.
   */
  public static function getType() {
    return StreamWrapperInterface::NORMAL;
  }

  /**
   * Returns the name of the stream wrapper for use in the UI.
   *
   * @return string
   *   The stream wrapper name.
   */
  public function getName() {
    return t('Windows Azure Storage');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('The files will store in Windows Azure Storage');
  }

  /**
   * Base implementation of setUri().
   *
   * Set the absolute stream resource URI.
   * Generally is only called by the factory method.
   *
   * @param string $uri
   *   A string containing the URI that should be used for this instance.
   */
  public function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * Base implementation of getUri().
   *
   * Returns the stream resource URI.
   *
   * @return string
   *   The resource URI.
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * Base implementation of realpath().
   *
   * Returns canonical, absolute path of the resource.
   *
   * @return string|false
   *   Returns a string with absolute pathname on success (implemented by core
   *   wrappers), or FALSE on failure or if the registered wrapper does not
   *   provide an implementation.
   */
  public function realpath() {
    // @todo If called as temporary://, return a realpath?
    return FALSE;
  }

  /**
   * Gets the name of the directory from a given path.
   *
   * This method is usually accessed through drupal_dirname(),
   * which wraps around the normal PHP dirname() function,
   * which does not support stream wrappers.
   *
   * @param string $uri
   *   An optional URI.
   *
   * @return string
   *   A string containing the directory name.
   */
  public function dirname($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }
    list($scheme, $target) = explode('://', $uri, 2);
    // Use getTarget() if location for writing is different from reading.
    $dirname = dirname(trim($target, '\/'));
    if ($dirname === '.') {
      $dirname = '';
    }
    return $scheme . '://' . $dirname;
  }

  /**
   * Returns the local writable target of the resource within the stream.
   *
   * This function should be used in place of calls to realpath() or
   * similar functions when attempting to determine the location of a file.
   * While functions like realpath() may return the location of a read-only
   * file, this method may return a URI or path suitable for writing that is
   * completely separate from the URI used for reading.
   *
   * @param string $uri
   *   An optional URI.
   *
   * @return string
   *   String representing a location suitable for writing of a file.
   */
  protected function getTarget($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }
    list($scheme, $target) = explode('://', $uri, 2);
    // Remove erroneous leading or trailing, forward-slashes and backslashes.
    return trim($target, '\/');
  }

  /**
   * This function should return a URL that can be embedded in a web page.
   *
   * @return string
   *   A string containing a web accessible URL for the resource.
   */
  public function getExternalUrl() {
    if ($this->azureHelper->blobExists($this->getTarget())) {
      return $this->getRealExternalUrl();
    }
    else {
      $path = $this->getTarget();
      $parts = explode('/', $path);
      $first_part = array_shift($parts);

      // If the file is a styles derivative, treat it differently.
      // Strip out path prefix.
      if ($first_part === 'styles') {
        $path = implode('/', $parts);
        // Get the image style, scheme and path.
        if (substr_count($path, '/') >= 2) {
          list($image_style, $scheme, $file) = explode('/', $path, 3);

          return Url::fromUserInput('/azure/generate/' . $image_style . '/' . $scheme,
            ['query' => ['file' => $file], 'absolute' => TRUE])->toString();
        }
        return Url::fromUserInput('/azure/generate/' . implode('/', $parts),
          ['absolute' => TRUE])->toString();
      }
    }

    return FALSE;
  }

  /**
   * Helper function to get the URL of a remote resource.
   *
   * @return string
   *   A string containing a web accessible URL for the resource.
   */
  public function getRealExternalUrl() {
    $azure_configuration = $this->azureHelper->getStorageConfigurations();
    $target = explode('/', $this->getTarget());
    $file_name = array_pop($target);
    $target[] = rawurlencode($file_name);
    $target = implode('/', $target);

    return implode('', [
      'https://',
      $azure_configuration['account'],
      '.blob.core.windows.net/',
      $azure_configuration['blob_container'],
      '/',
      $target,
    ]);
  }

  /**
   * Converts a Drupal URI path into what is expected to be stored in azure.
   *
   * @param string $uri
   *   An appropriate URI formatted like 'protocol://path'.
   *
   * @return string
   *   A converted string ready for windowsazurestorage to process it.
   */
  protected function convertUriToKeyedPath($uri) {
    // Remove the protocol.
    $parts = explode('://', $uri);

    if (!empty($parts[1])) {
      // public:// file are all placed in the azure_folder.
      $public_folder = 'was-public';
      if (\Drupal::service('file_system')->uriScheme($uri) == 'public') {
        $parts[1] = "$public_folder/{$parts[1]}";
      }
    }

    // Set protocol to S3 so AWS stream wrapper works correctly.
    $parts[0] = 'windowsazurestorage';
    return implode('://', $parts);
  }

  /**
   * Support for fopen(), file_get_contents(), file_put_contents() etc.
   *
   * @param string $uri
   *   A string containing the URI to the file to open.
   * @param string $mode
   *   The file mode ("r", "wb" etc.).
   * @param int $options
   *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
   * @param string $opened_path
   *   A string containing the path actually opened.
   *
   * @return bool
   *   Returns TRUE if file was opened successfully.
   */
  public function stream_open($uri, $mode, $options, &$opened_path) {
    $this->setUri($uri);
    $converted = $this->convertUriToKeyedPath($uri);
    try {
      $blob_name = $this->getFileName($converted);

      if (empty($blob_name)) {
        throw new Exception(t('Empty blob path name given. Has to be a full filename.'));
      }

      // Write mode?
      if (strpbrk($mode, 'wax+')) {
        $this->writeMode = TRUE;
      }
      else {
        $this->writeMode = FALSE;
      }

      $result = FALSE;

      // If read/append, fetch the file.
      if (!$this->writeMode || strpbrk($mode, 'ra+')) {
        $this->tempFileHandle = $this->azureHelper
          ->downloadBlob($this->azureHelper->getContainerName(),
            $this->getFileName($converted));
        $result = TRUE;
      }
      else {
        $this->tempFilename = tempnam(sys_get_temp_dir(), 'azurestorageblob');
        // Check the file can be opened.
        $fh = @fopen($this->tempFilename, "w");
        if ($fh !== FALSE) {
          fclose($fh);

          // Open temporary file handle.
          $this->tempFileHandle = fopen($this->tempFilename, "w");

          // Ok!
          $result = TRUE;
        }
      }
      return $result;
    }
    catch (Exception $ex) {
      // The stream_open() function should not raise any exception.
      return FALSE;
    }
  }

  /**
   * Base implementation of stream_lock().
   *
   * Support for flock().
   *
   * @param int $operation
   *   - LOCK_SH to acquire a shared lock (reader)
   *   - LOCK_EX to acquire an exclusive lock (writer)
   *   - LOCK_UN to release a lock (shared or exclusive)
   *   - LOCK_NB if you don't want flock() to block while locking. (not
   *   supported on Windows)
   *
   * @return bool
   *   Always returns TRUE at present.
   */
  public function stream_lock($operation) {
    if (in_array($operation, [LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB])) {
      return flock($this->tempFileHandle, $operation);
    }

    return TRUE;
  }

  /**
   * Closes the stream. Support for fclose().
   */
  public function stream_close() {
    // Prevent timeout when uploading.
    drupal_set_time_limit(0);

    @fclose($this->tempFileHandle);
  }

  /**
   * Helper function to cleanup after the stream is closed.
   */
  private function cleanup() {
    if ($this->tempFilename) {
      @unlink($this->tempFilename);
    }
  }

  /**
   * Support for fread(), file_get_contents() etc.
   *
   * @param int $count
   *   Maximum number of bytes to be read.
   *
   * @return string|false
   *   The string that was read, or FALSE in case of an error.
   */
  public function stream_read($count) {
    if (!$this->tempFileHandle) {
      return FALSE;
    }
    return fread($this->tempFileHandle, $count);
  }

  /**
   * Support for fwrite(), file_put_contents() etc.
   *
   * @param string $data
   *   The string to be written.
   *
   * @return int
   *   The number of bytes written
   */
  public function stream_write($data) {
    return fwrite($this->tempFileHandle, $data);
  }

  /**
   * End of the stream? Support for feof().
   *
   * @return bool
   *   TRUE if end-of-file has been reached.
   */
  public function stream_eof() {
    if (!$this->tempFileHandle) {
      return TRUE;
    }
    return feof($this->tempFileHandle);
  }

  /**
   * What is the current read/write position of the stream?
   *
   * @return int
   *   The current offset in bytes from the beginning of file.
   */
  public function stream_tell() {
    return ftell($this->tempFileHandle);
  }

  /**
   * Update the read/write position of the stream.
   *
   * @param int $offset
   *   The byte offset to got to.
   * @param int $whence
   *   SEEK_SET, SEEK_CUR, or SEEK_END.
   *
   * @return bool
   *   TRUE on success.
   */
  public function stream_seek($offset, $whence = SEEK_SET) {
    if (!$this->tempFileHandle) {
      return FALSE;
    }
    return (fseek($this->tempFileHandle, $offset, $whence) === 0);
  }

  /**
   * Flush current cached stream data to storage.
   *
   * @return bool
   *   TRUE if data was successfully stored (or there was no data to store).
   */
  public function stream_flush() {
    $content = fopen($this->tempFilename, "r");
    $result = TRUE;

    // Upload the file.
    if ($this->writeMode
      && !$this->azureHelper->uploadBlob($this->azureHelper->getContainerName(), $this->getFileName($this->uri), $content)) {
      return FALSE;
    }
    $this->cleanup();
    return $result;
  }

  /**
   * Returns data array of stream variables.
   *
   * @return array
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   */
  public function stream_stat() {
    if (!$this->tempFileHandle) {
      return FALSE;
    }
    return $this->url_stat($this->uri, 0);
  }

  /**
   * Attempt to delete the item.
   *
   * @param string $uri
   *   A string containing the URI to the resource to delete.
   *
   * @return bool
   *   TRUE if resource was successfully deleted.
   */
  public function unlink($uri) {
    $this->setUri($uri);
    // Unlink() should never throw an exception.
    return $this->azureHelper->deleteBlob($this->azureHelper->getContainerName(),
      $this->getFileName($this->uri));
  }

  /**
   * Attempt to rename the item.
   *
   * @param string $path_from
   *   The URI to the file to rename.
   * @param string $path_to
   *   The new URI for file.
   *
   * @return bool
   *   TRUE if file was successfully renamed.
   */
  public function rename($path_from, $path_to) {
    return TRUE;
  }

  /**
   * Return array of URL variables.
   *
   * @param string $uri
   *   A string containing the URI to get information about.
   * @param int $flags
   *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
   *
   * @return array|bool
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   */
  public function url_stat($uri, $flags) {
    $this->setUri($uri);

    // Default values.
    $stat = [
      'dev'     => 0,
      'ino'     => 0,
      'mode'    => 0666,
      'nlink'   => 0,
      'uid'     => 0,
      'gid'     => 0,
      'rdev'    => 0,
      'size'    => 0,
      'atime'   => 0,
      'mtime'   => 0,
      'ctime'   => 0,
      'blksize' => 0,
      'blocks'  => 0,
    ];

    try {
      if ($blob_properties = $this->azureHelper->getBlobProperties($this->getFileName($this->uri))) {

        // Set the modification time to the Last-Modified header.
        $lastmodified = $blob_properties->getProperties()->getLastModified()->format('U');
        $stat['mtime'] = $lastmodified;
        $stat['ctime'] = $lastmodified;
        $stat['size'] = $blob_properties->getProperties()->getContentLength();

        // Entry is a regular file with group access.
        $stat['mode'] = 0100000 | 660;
      }
      elseif ($this->azureHelper->isDirectory($this->getFileName($this->uri))) {
        // It is a directory.
        $stat['mode'] |= 0040777;
      }
      else {
        // File really does not exist.
        return FALSE;
      }
    }
    catch (Exception $ex) {
      // Unexisting file... check if it is a directory.
      if ($this->azureHelper->isDirectory($this->getFileName($this->uri))) {
        // It is a directory.
        $stat['mode'] |= 0040777;
      }
      else {
        // File really does not exist.
        return FALSE;
      }
    }

    // Last access time.
    $stat['atime'] = time();
    // Return both numeric and associative values.
    return array_values($stat) + $stat;
  }

  /**
   * Creates a new directory.
   *
   * @param string $uri
   *   A string containing the URI to the directory to create.
   * @param int $mode
   *   Permission flags - see mkdir().
   * @param int $options
   *   A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE.
   *
   * @return bool
   *   TRUE if directory was successfully created.
   */
  public function mkdir($uri, $mode, $options) {
    $this->setUri($uri);
    // Create the placeholder for a virtual directory in the container.
    return $this->azureHelper->uploadBlob(
      $this->azureHelper->getContainerName(),
      $this->getTarget() . '/.placeholder', '');
  }

  /**
   * Remove a directory.
   *
   * @param string $uri
   *   A string containing the URI to the directory to delete.
   * @param int $options
   *   A bit mask of STREAM_REPORT_ERRORS.
   *
   * @return bool
   *   TRUE if directory was successfully removed.
   */
  public function rmdir($uri, $options) {
    return TRUE;
  }

  /**
   * Attempt to open a directory.
   *
   * @param string $uri
   *   A string containing the URI to the directory to open.
   * @param int $options
   *   Unknown (not documented).
   *
   * @return bool
   *   TRUE on success.
   */
  public function dir_opendir($uri, $options) {
    return TRUE;
  }

  /**
   * Return the next filename in the directory.
   *
   * @return bool
   *   The next filename, or FALSE if there are no more files in the directory.
   */
  public function dir_readdir() {
    return TRUE;
  }

  /**
   * Reset the directory pointer.
   *
   * @return bool
   *   TRUE on success.
   */
  public function dir_rewinddir() {
    return TRUE;
  }

  /**
   * Close a directory.
   *
   * @return bool
   *   TRUE on success.
   */
  public function dir_closedir() {
    return TRUE;
  }

  /**
   * Retrieve name of the blob.
   *
   * @param string $uri
   *   File uri.
   *
   * @return string
   *   Blob name
   */
  protected function getFileName($uri) {
    return $this->getTarget($uri);
  }

  /**
   * Retrieve the underlying stream resource.
   *
   * This method is called in response to stream_select().
   *
   * @param int $cast_as
   *   Can be STREAM_CAST_FOR_SELECT when stream_select() is calling
   *   stream_cast() or STREAM_CAST_AS_STREAM when stream_cast() is called for
   *   other uses.
   *
   * @return resource|false
   *   The underlying stream resource or FALSE if stream_select() is not
   *   supported.
   *
   * @see stream_select()
   * @see http://php.net/manual/streamwrapper.stream-cast.php
   */
  public function stream_cast($cast_as) {
    return FALSE;
  }

  /**
   * Sets metadata on the stream.
   *
   * @param string $path
   *   A string containing the URI to the file to set metadata on.
   * @param int $option
   *   One of:
   *   - STREAM_META_TOUCH: The method was called in response to touch().
   *   - STREAM_META_OWNER_NAME: The method was called in response to chown()
   *     with string parameter.
   *   - STREAM_META_OWNER: The method was called in response to chown().
   *   - STREAM_META_GROUP_NAME: The method was called in response to chgrp().
   *   - STREAM_META_GROUP: The method was called in response to chgrp().
   *   - STREAM_META_ACCESS: The method was called in response to chmod().
   * @param mixed $value
   *   If option is:
   *   - STREAM_META_TOUCH: Array consisting of two arguments of the touch()
   *     function.
   *   - STREAM_META_OWNER_NAME or STREAM_META_GROUP_NAME: The name of the owner
   *     user/group as string.
   *   - STREAM_META_OWNER or STREAM_META_GROUP: The value of the owner
   *     user/group as integer.
   *   - STREAM_META_ACCESS: The argument of the chmod() as integer.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure. If $option is not
   *   implemented, FALSE should be returned.
   *
   * @see http://www.php.net/manual/streamwrapper.stream-metadata.php
   */
  public function stream_metadata($path, $option, $value) {
    $bypassed_options = [STREAM_META_ACCESS];
    return in_array($option, $bypassed_options);
  }

  /**
   * Change stream options.
   *
   * This method is called to set options on the stream.
   *
   * @param int $option
   *   One of:
   *   - STREAM_OPTION_BLOCKING: The method was called in response to
   *     stream_set_blocking().
   *   - STREAM_OPTION_READ_TIMEOUT: The method was called in response to
   *     stream_set_timeout().
   *   - STREAM_OPTION_WRITE_BUFFER: The method was called in response to
   *     stream_set_write_buffer().
   * @param int $arg1
   *   If option is:
   *   - STREAM_OPTION_BLOCKING: The requested blocking mode:
   *     - 1 means blocking.
   *     - 0 means not blocking.
   *   - STREAM_OPTION_READ_TIMEOUT: The timeout in seconds.
   *   - STREAM_OPTION_WRITE_BUFFER: The buffer mode, STREAM_BUFFER_NONE or
   *     STREAM_BUFFER_FULL.
   * @param int $arg2
   *   If option is:
   *   - STREAM_OPTION_BLOCKING: This option is not set.
   *   - STREAM_OPTION_READ_TIMEOUT: The timeout in microseconds.
   *   - STREAM_OPTION_WRITE_BUFFER: The requested buffer size.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise. If $option is not implemented, FALSE
   *   should be returned.
   */
  public function stream_set_option($option, $arg1, $arg2) {
    return FALSE;
  }

  /**
   * Truncate stream.
   *
   * Will respond to truncation; e.g., through ftruncate().
   *
   * @param int $new_size
   *   The new size.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise.
   *
   * @todo
   *   This one actually makes sense for the example.
   */
  public function stream_truncate($new_size) {
    return FALSE;
  }

  /**
   * Gets the path that the wrapper is responsible for.
   *
   * This function isn't part of DrupalStreamWrapperInterface, but the rest
   * of Drupal calls it as if it were, so we need to define it.
   *
   * @return string
   *   The empty string. Since this is a remote stream wrapper,
   *   it has no directory path.
   *
   * @see \Drupal\Core\File\LocalStream::getDirectoryPath()
   */
  public function getDirectoryPath() {
    return '';
  }

}
