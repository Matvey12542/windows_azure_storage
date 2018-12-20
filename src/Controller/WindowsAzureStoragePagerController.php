<?php

namespace Drupal\windows_azure_storage\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\windows_azure_storage\BinaryFileResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * A class for get a list from DB.
 */
class WindowsAzureStoragePagerController extends ControllerBase {

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a WindowsAzureStoragePagerController object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory) {
    $this->lock = $lock;
    $this->imageFactory = $image_factory;
    $this->logger = $this->getLogger('image');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('lock'), $container->get('image.factory'));
  }

  /**
   * Page callback - return query parameters.
   *
   * @return mixed
   *   Render array.
   */
  public function queryParameters() {
    $header = [
      'folder' => $this->t('Folder Name'),
      'name' => $this->t('Blob Name(File name)'),
      'url' => $this->t('Image'),
      'operations' => $this->t('Delete'),
    ];

    //@todo refactor.
    $query = db_select('windows_azure_storage_file', 'azure_file')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->element(0);

    $query->fields('azure_file', ['id', 'container', 'folder', 'name', 'url']);
    $result = $query
      ->limit(5)
      ->orderBy('azure_file.id')
      ->execute();

    $rows = [];

    while ($row = $result->fetchAssoc()) {
      $rows[] = [
        'data' => [
          $row['folder'],
          $row['name'],
          '<img src="' . $row['url'] . '" width="60" height="60" />',

          // @todo refactor.
          \Drupal::l('Delete',
            url::fromRoute('windows_azure_storage_delete_file', [
              'id'        => $row['id'],
              'container' => $row['container'],
              'folder'    => $row['folder'],
              'file_name' => $row['name'],
            ])),
        ],
      ];
    }

    $build['pager_table_azure'] = [
      '#theme'  => 'table',
      '#header' => $header,
      '#rows'   => $rows,
      '#empty'  => $this->t("There are no file stored in Azure storage blob"),
    ];

    $build['pager_pager_pager'] = [
      '#type' => 'pager',
      '#element'  => 0,
      '#pre_render' => ['Drupal\windows_azure_storage\Controller\WindowsAzureStoragePagerController::showPagerCacheContext'],
    ];

    return $build;
  }

  /**
   * Callback #pre_render for #type => pager that shows the pager cache context.
   *
   * @param array $pager
   *   Pager array.
   *
   * @return array
   *   Pager.
   */
  public static function showPagerCacheContext(array $pager) {
    return $pager;
  }

  /**
   * Generate image style.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   Image style.
   * @param string $scheme
   *   Scheme.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return bool|\Drupal\Core\Routing\TrustedRedirectResponse|\Symfony\Component\HttpFoundation\Response
   *   Response.
   */
  public function generate(ImageStyle $image_style, $scheme, Request $request) {

    // Check that the style is defined and the scheme is valid.
    if (empty($image_style) || \Drupal::service('file_system')->validScheme($scheme) === FALSE) {
      return FALSE;
    }

    $target = $request->query->get('file');
    $valid = !empty($image_style) && \Drupal::service('file_system')->validScheme($scheme);

    if (!$this->config('image.settings')->get('allow_insecure_derivatives')
      || strpos(ltrim($target, '\/'), 'styles/') === 0) {
      $valid &= $request->query->get(IMAGE_DERIVATIVE_TOKEN) === $image_style->getPathToken($scheme . '://' . $target);
    }

    if (!$valid) {
      throw new AccessDeniedHttpException();
    }

    $image_uri = $scheme . '://' . $target;
    $derivative_uri = $image_style->buildUri($image_uri);

    // Don't start generating the image if the derivative already exists or if
    // generation is in progress in another thread.
    if (!file_exists($derivative_uri)) {
      $lock_name = 'image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($image_uri);
      $lock_acquired = $this->lock->acquire($lock_name);

      if (!$lock_acquired) {

        // Tell client to retry again in 3 seconds. Currently no browsers are
        // known to support Retry-After.
        throw new ServiceUnavailableHttpException(3, $this->t('Image generation in progress. Try again shortly.'));
      }
      else {
        $success = file_exists($derivative_uri) || $image_style->createDerivative($image_uri, $derivative_uri);
      }
    }
    else {
      $success = TRUE;
    }

    if (!empty($lock_acquired) && !empty($lock_name)) {
      $this->lock->release($lock_name);
    }

    if ($success) {
      // Perform a 302 Redirect to the new image derivative in azure.
      // It must be TrustedRedirectResponse for external redirects.
      $response = new TrustedRedirectResponse(file_create_url($derivative_uri));
      $cacheableMetadata = $response->getCacheableMetadata();
      $cacheableMetadata->addCacheContexts(
        [
          'url.query_args:file',
          'url.query_args:itok',
        ]
      );
      $cacheableMetadata->mergeCacheMaxAge(0);
      $response->addCacheableDependency($cacheableMetadata);

      return $response;
    }
    else {
      $this->logger->notice('Unable to generate the derived image located at %path.',
        ['%path' => $derivative_uri]);
      return new Response($this->t('Error generating image.'), 500);
    }
  }

  /**
   * Returns a HTTP response for a file being downloaded.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to download, as an entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response.
   */
  public function download(FileInterface $file) {
    // Ensure there is a valid token to download this file.
    if (!$this->config('file_entity.settings')
      ->get('allow_insecure_download')) {
      if (!isset($_GET['token']) || $_GET['token'] !== $file->getDownloadToken()) {
        return new Response(t('Access to file @url denied',
          ['@url' => $file->getFileUri()]), 403);
      }
    }

    $headers = [
      'Content-Type' => Unicode::mimeHeaderEncode($file->getMimeType()),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode(drupal_basename($file->getFileUri())) . '"',
      'Content-Length' => $file->getSize(),
      'Content-Transfer-Encoding' => 'binary',
      'Pragma' => 'no-cache',
      'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
      'Expires' => '0',
    ];

    // Let other modules alter the download headers.
    \Drupal::moduleHandler()->alter('file_download_headers', $headers, $file);

    // Let other modules know the file is being downloaded.
    \Drupal::moduleHandler()->invokeAll('file_transfer', [$file->getFileUri(), $headers]);

    try {
      return new BinaryFileResponse($file->getFileUri(), 200, $headers);
    }
    catch (FileNotFoundException $e) {
      return new Response(t('File @uri not found', ['@uri' => $file->getFileUri()]), 404);
    }
  }

}
