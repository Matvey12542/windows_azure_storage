<?php

namespace Drupal\windows_azure_storage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * The stream wrapper class.
 */
class WindowsAzureStorageServiceProvider extends ServiceProviderBase {

  /**
   * Modifies existing service definitions.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder whose service definitions can be altered.
   */
  public function alter(ContainerBuilder $container) {

    // Fix CSS static urls.
    $container->getDefinition('asset.css.optimizer')
      ->setClass('Drupal\windows_azure_storage\Asset\WindowsAzureStorageCssOptimizer');
  }

}
