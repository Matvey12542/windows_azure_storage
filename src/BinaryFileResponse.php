<?php

namespace Drupal\windows_azure_storage;

use Symfony\Component\HttpFoundation\BinaryFileResponse as BinaryFileResponseOrigin;
use Symfony\Component\HttpFoundation\File\File;

/**
 * BinaryFileResponse represents an HTTP response delivering a file.
 */
class BinaryFileResponse extends BinaryFileResponseOrigin {

  /**
   * Sets the file to stream.
   *
   * @param \SplFileInfo|string $file
   *   The file to stream.
   * @param string $contentDisposition
   *   Content disposition.
   * @param bool $autoEtag
   *   Etag.
   * @param bool $autoLastModified
   *   Last modified.
   *
   * @return $this
   */
  public function setFile($file, $contentDisposition = NULL, $autoEtag = FALSE, $autoLastModified = TRUE) {
    if (!$file instanceof File) {
      if ($file instanceof \SplFileInfo) {
        $file = new File($file->getPathname());
      }
      else {
        $file = new File((string) $file);
      }
    }

    $this->file = $file;

    if ($autoEtag) {
      $this->setAutoEtag();
    }

    if ($autoLastModified) {
      $this->setAutoLastModified();
    }

    if ($contentDisposition) {
      $this->setContentDisposition($contentDisposition);
    }

    return $this;
  }

}
