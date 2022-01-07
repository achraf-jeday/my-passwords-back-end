<?php

namespace Drupal\password_locker_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Drupal\user\UserStorageInterface;

/**
 * Provides a resource to validate the user packing key.
 *
 * @RestResource(
 *   id = "validate_packing_key",
 *   label = @Translation("Validate packing key"),
 *   uri_paths = {
 *     "canonical" = "/user/packing-key",
 *     "create" = "/user/packing-key"
 *   }
 * )
 */
class ValidatePackingKey extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a new GetPasswordRestResourse object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    UserStorageInterface $user_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->userStorage = $user_storage;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest_password'),
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function post(array $data) {

    $response = ['message' => $this->t('Please Post packing key.')];
    $code = 400;

    if(!empty($data['packing_key'])) {
      // Get the packing key of the current user.
      $user_id = $this->currentUser->id();
      $currentUser = \Drupal\user\Entity\User::load($user_id);
      $field_packing_key = $currentUser->field_packing_key->value;

      if($data['packing_key'] === $field_packing_key) {
        $this->logger->notice("Successful login of user '%name'.", ['%name' => $currentUser->name->value]);
        $message_ok = $this->t('Packing key is valid. You can access your accoount now.');
        $response = ['message' => $message_ok];
        $code = 200;
      }
      else {
        $this->logger->notice("Unsuccessful login attempt: incorrect packing key.");
        $response = ['message' => $this->t("Incorrect packing key!")];
        $code = 401;
      }
    }

    return new ResourceResponse($response, $code);
  }

}
