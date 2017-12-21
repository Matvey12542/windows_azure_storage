<?php
/**
 * A new Stream Wrapper of Windows Azure Storage.
 * @author DylanLi
 */
namespace Drupal\windows_azure_storage\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\windows_azure_storage\Common\WindowsAzureStorageHelper;
use Drupal\windows_azure_storage\Common\WindowsAzureStorageConn;



class WindowsAzureStorageStreamWrapper implements StreamWrapperInterface{
   
  /**
   * Instance URI (current filename)
   * @var string
   */
  protected $uri;
  
  /**
   * Temporary filename
   * @var string
   */
  protected $temp_filename = NULL;
  
  /**
   * Temporary file handle
   * @var string
   */
  protected $temp_file_handle = NULL;
  
  /**
   * Write mode?
   * @var boolean
   */
  protected $write_mode = false;
  
  

  /**
   * Returns the type of stream wrapper.
   *
   * @return int
   */
  public static function getType(){
    return StreamWrapperInterface::NORMAL;
  }
  
  /**
   * Returns the name of the stream wrapper for use in the UI.
   *
   * @return string
   *   The stream wrapper name.
   */
  public function getName(){
    return t('Windows Azure Storage');
  }
  
  /**
   * {@inheritdoc}
   */
  public function getDescription(){
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
   *   Returns a string with absolute pathname on success (implemented by core wrappers), 
   *   or FALSE on failure or if the registered wrapper does not provide an implementation.
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
    // Use getTarget() if location for writing is different from reading
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
   * While functions like realpath() may return the location of a read-only file, 
   * this method may return a URI or path suitable for writing that is 
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
   * This function should return a URL that can be embedded in a web page 
   * and accessed from a browser.
   * 
   * @return string
   *   A string containing a web accessible URL for the resource.
   */
  public function getExternalUrl() {
  	return $this->getRealExternalUrl();
  }
  
  /**
   * Helper function to get the URL of a remote resource.
   * 
   * @return string
   *   A string containing a web accessible URL for the resource.
   */
  public function getRealExternalUrl() {
  	$azure_conn = new WindowsAzureStorageConn();
  	$azure_configuration = $azure_conn->getWindowsAzureStorageConfiguration();
  	return "https://" . $azure_configuration['account'] . ".blob.core.windows.net/" . $azure_configuration['blob_container'] . "/". $this->getTarget();
  }

  /**
   * Support for fopen(), file_get_contents(), file_put_contents() etc.
   * 
   * @param string $uri
   *   A string containing the URI to the file to open.
   * @param string $mode
   *   The file mode ("r", "wb" etc.).
   * @param integer $options
   *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
   * @param string $opened_path
   *   A string containing the path actually opened.
   * 
   * @return boolean
   *   Returns TRUE if file was opened successfully.
   */
  public function stream_open($uri, $mode, $options, &$opened_path) {

  	$this->setUri($uri);
    try {
      $blob_name = $this->getFileName($this->uri);
      
      if (empty($blob_name)) {
        throw new Exception(t('Empty blob path name given. Has to be a full filename.'));
      }

      // Write mode?
      if (strpbrk($mode, 'wax+')) {
        $this->write_mode = TRUE;
      } 
      else {
        $this->write_mode = FALSE;
      }
      
      $result = FALSE;

      // If read/append, fetch the file
      if (!$this->write_mode || strpbrk($mode, 'ra+')) {
      	$azure_storage_helper = new WindowsAzureStorageHelper();
        $this->temp_file_handle  = $azure_storage_helper->downloadBlob($azure_storage_helper->container_name, $this->getFileName($this->uri));
        $result = TRUE;
      }
      else {
      	\Drupal::logger('WAS')->notice("__OPENING__");
        $this->temp_filename = tempnam(sys_get_temp_dir(), 'azurestorageblob');
        // Check the file can be opened
        $fh = @fopen($this->temp_filename, "w");
        if ($fh !== FALSE) {
          fclose($fh);

          // Open temporary file handle
          $this->temp_file_handle = fopen($this->temp_filename, "w");

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
   *   - LOCK_NB if you don't want flock() to block while locking. (not supported on Windows)
   * 
   * @return boolean
   *   Always returns TRUE at present.
   */
  public function stream_lock($operation) {
    if (in_array($operation, array(LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB))) {
      return flock($this->temp_file_handle, $operation);
    }
  
    return TRUE;
  }

  /**
   * Closes the stream. Support for fclose().
   * 
   * @return boolean
   *   TRUE if stream was successfully closed.
   */
  public function stream_close() {
    // Prevent timeout when uploading
    drupal_set_time_limit(0);

    @fclose($this->temp_file_handle);

  }
  
  /**
   * Helper function to cleanup after the stream is closed.
   */
  private function cleanup() {
    if ($this->temp_filename) {
      @unlink($this->temp_filename);
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
    if (!$this->temp_file_handle) {
      return FALSE;
    }
    return fread($this->temp_file_handle, $count);
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
  	return fwrite($this->temp_file_handle, $data);
  }

  /**
   * End of the stream? Support for feof().
   *
   * @return boolean
   *   TRUE if end-of-file has been reached.
   */
  public function stream_eof() {
    if (!$this->temp_file_handle) {
      return TRUE;
    }
    return feof($this->temp_file_handle);
  }

  /**
   * What is the current read/write position of the stream?
   *
   * @return int
   *   The current offset in bytes from the beginning of file.
   */
  public function stream_tell() {
    return ftell($this->temp_file_handle);
  }

  /**
   * Update the read/write position of the stream.
   *
   * @param integer $offset
   *   The byte offset to got to.
   * @param integer $whence
   *   SEEK_SET, SEEK_CUR, or SEEK_END
   * 
   * @return boolean
   *   TRUE on success.
   */
  public function stream_seek($offset, $whence = SEEK_SET) {
    if (!$this->temp_file_handle) {
        return FALSE;
    }
    return (fseek($this->temp_file_handle, $offset, $whence) === 0);
  }

  /**
   * Flush current cached stream data to storage.
   *
   * @return boolean
   *   TRUE if data was successfully stored (or there was no data to store).
   */
  public function stream_flush() {
  	$content=fopen($this->temp_filename,"r");
    //$result = fflush($this->temp_file_handle);
  	$result=true;
    
    // Upload the file?
    if ($this->write_mode) {
    	$azure_storage_helper = new WindowsAzureStorageHelper();
    	// Upload the file
      if(!$azure_storage_helper->uploadBlob($azure_storage_helper->container_name, $this->getFileName($this->uri), $content)){
      	return FALSE;
      }
      $azure_storage_helper->insertDB($azure_storage_helper->container_name, explode('/', $this->getFileName($this->uri))[0], explode('/', $this->getFileName($this->uri))[1], $this->getRealExternalUrl());
    }
    $this->cleanup();
    return $result;
  }
  
  /**
   * Returns data array of stream variables
   *
   * @return array
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   */
  public function stream_stat() {
    if (!$this->temp_file_handle) {
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
   * @return boolean
   *   TRUE if resource was successfully deleted.
   */
  public function unlink($uri) {
    $this->setUri($uri);
    // unlink() should never throw an exception
    $azure_storage_helper = new WindowsAzureStorageHelper();
    return $azure_storage_helper->deleteBlob($azure_storage_helper->container_name, $this->getFileName($this->uri));
    clearstatcache(true, $uri);
  }
  
  /** 
   * Attempt to rename the item
   * 
   * @param string $path_from
   *   The URI to the file to rename.
   * @param string $path_to
   *   The new URI for file.
   * 
   * @return boolean
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
   * @param integer $flags
   *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
   * 
   * @return array
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   */
  public function url_stat($uri, $flags) {
  	$this->uri = $uri;
  	$path = $this->getTarget($uri);
  	// Suppress warnings if requested or if the file or directory does not
  	// exist. This is consistent with PHP's plain filesystem stream wrapper.
  	if ($flags & STREAM_URL_STAT_QUIET || !file_exists($path)) {
  		return @stat($path);
  	}
  	else {
  		return stat($path);
  	}
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
   * @return boolean
   *   TRUE if directory was successfully created.
   */
  public function mkdir($uri, $mode, $options) {
    $this->setUri($uri);
    // Create the placeholder for a virtual directory in the container
    $azure_storage_helper = new WindowsAzureStorageHelper();
    return $azure_storage_helper->uploadBlob($azure_storage_helper->container_name, $this->getTarget() . '/.placeholder', '');
  }

  /**
   * Remove a directory.
   * 
   * @param string $uri
   *   A string containing the URI to the directory to delete.
   * @param int $options
   *   A bit mask of STREAM_REPORT_ERRORS.
   * 
   * @return boolean
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
   * @return boolean
   *   TRUE on success.
   */
  public function dir_opendir($uri, $options) {
    return true;
  }

  /**
   * Return the next filename in the directory.
   * 
   * @return boolean
   *   The next filename, or FALSE if there are no more files in the directory.
   */
  public function dir_readdir() {
    return true;
  }
  
  /**
   * Reset the directory pointer.
   *
   * @return boolean
   *   TRUE on success.
   */
  public function dir_rewinddir() {
    return TRUE;
  }

  /**
   * Close a directory.
   *
   * @return boolean
   *   TRUE on success.
   */
  public function dir_closedir() {
    return TRUE;
  }

  /**
   * Retrieve name of the blob.
   * 
   * @param string $uri
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
    return FALSE;
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
}
?>