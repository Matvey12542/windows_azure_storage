<?php

namespace Drupal\windows_azure_storage\Asset;

use Drupal\Core\Asset\CssOptimizer;

/**
 * Optimizes a CSS asset.
 */
class WindowsAzureStorageCssOptimizer extends CssOptimizer {

  /**
   * Return absolute urls to access static files that they aren't in azure.
   *
   * @param array $matches
   *   An array of matches by a preg_replace_callback() call that scans for
   *   url() references in CSS files, except for external or absolute ones.
   * @param string $base_path
   *   Base url.
   *
   * @return string
   *   The file path.
   */
  public function rewriteFileURI($matches, $base_path = NULL) {
    // Prefix with base and remove '../' segments where possible.
    $path = $base_path
      ? $base_path . $matches[1]
      : $this->rewriteFileURIBasePath . $matches[1];

    $last = '';
    while ($path != $last) {
      $last = $path;
      $path = preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);
    }
    return 'url(' . file_create_url($path) . ')';
  }

}
