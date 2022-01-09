<?php

namespace Drupal\password_locker_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Drupal\user\UserStorageInterface;

/**
 * Provides resources related to the user packing key.
 *
 * @RestResource(
 *   id = "packing_key",
 *   label = @Translation("Packing key"),
 *   uri_paths = {
 *     "canonical" = "/user/packing-key",
 *     "create" = "/user/packing-key"
 *   }
 * )
 */
class PackingKey extends ResourceBase {

  /**
   * The password hash service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $passwordHasher;

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
    PasswordInterface $password_hasher,
    AccountProxyInterface $current_user,
    UserStorageInterface $user_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->passwordHasher = $password_hasher;
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
      $container->get('logger.factory')->get('password_locker_rest'),
      $container->get('password'),
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
      $success = $this->passwordHasher->check($data['packing_key'], $field_packing_key);
      if($success) {
        $session = \Drupal::request()->getSession();
        $session->set('password_locker_rest.packing_key', $data['packing_key']);
        $this->logger->notice("Successful login of user '%name'.", ['%name' => $currentUser->name->value]);
        $message_ok = $this->t('Packing key is valid. You can access your account now.');
        $response = ['message' => $message_ok];
        $code = 200;
      }
      else {
        $session = \Drupal::request()->getSession();
        $packing_key = $session->get('password_locker_rest.packing_key');
        $session->remove('password_locker_rest.packing_key');
        \Drupal::request()->getSession()->clear();
        $this->logger->notice("Unsuccessful login attempt: incorrect packing key.");
        $response = ['message' => $this->t("Incorrect packing key!")];
        $code = 401;
      }
    }

    return new ResourceResponse($response, $code);
  }

  /**
   * Responds to PATCH requests.
   *
   * @param array $data
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function patch(array $data) {

    if(!empty($data['packing_key']['value'])) {
      $uid = $this->currentUser->id();
      $currentUser = \Drupal\user\Entity\User::load($uid);
      $field_packing_key = $currentUser->field_packing_key->value;
      if(!empty($data['packing_key']['existing'])) {
        if(!empty($field_packing_key)) {
          $success = $this->passwordHasher->check($data['packing_key']['existing'], $field_packing_key);
          if($success) {
            $new_hashed = $this->passwordHasher->hash($data['packing_key']['value']);
            $currentUser->set('field_packing_key', $new_hashed);
            $currentUser->save();
            $this->logger->notice("Packing key of user '%name' updated successfully.",
              [
                '%name' => $this->currentUser->getAccountName()
              ]
            );
            $message_ok = $this->t('Packing key updated successfully.');
            $response = ['message' => $message_ok];
            $code = 200;
          }
          else {
            $this->logger->notice("Unsuccessful update attempt: incorrect packing key.");
            $response = ['message' => $this->t("Incorrect packing key!")];
            $code = 401;
          }
        }
        else {
          $new_hashed = $this->passwordHasher->hash($data['packing_key']['value']);
          $currentUser->set('field_packing_key', $new_hashed);
          $currentUser->save();
          $this->logger->notice("Packing key of user '%name' updated successfully.",
            [
              '%name' => $this->currentUser->getAccountName()
            ]
          );
          $message_ok = $this->t('Packing key updated successfully.');
          $response = ['message' => $message_ok];
          $code = 200;
        }
      }
      else {
        if(empty($field_packing_key)) {
          $new_hashed = $this->passwordHasher->hash($data['packing_key']['value']);
          $currentUser->set('field_packing_key', $new_hashed);
          $currentUser->save();
          $this->logger->notice("Packing key of user '%name' updated successfully.",
            [
              '%name' => $this->currentUser->getAccountName()
            ]
          );
          $message_ok = $this->t('Packing key updated successfully.');
          $response = ['message' => $message_ok];
          $code = 200;
        }
        else {
          $this->logger->notice("Unsuccessful update attempt: incorrect packing key.");
          $response = ['message' => $this->t("Incorrect packing key!")];
          $code = 401;
        }
      }
    }
    else {
      $this->logger->notice("Cannot update packing key.");
      $response = ['message' => $this->t('Please Patch packing key.')];
      $code = 400;
    }

    return new ResourceResponse($response, $code);
  }

}
