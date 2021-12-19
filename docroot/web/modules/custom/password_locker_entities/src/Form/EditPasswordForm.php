<?php

namespace Drupal\password_locker_entities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class EditPasswordForm.
 */
class EditPasswordForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_password_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The name of the Password entity.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['field_user_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User ID'),
      '#description' => $this->t('User account identifier.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['field_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#description' => $this->t('User account password.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['field_link'] = [
      '#type' => 'url',
      '#title' => $this->t('Link'),
      '#description' => $this->t('Link to application or login page.'),
      '#weight' => '0',
    ];
    $form['field_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#description' => $this->t('Email used with the user account.'),
      '#weight' => '0',
    ];
    $form['field_notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Notes about the user account.'),
      '#weight' => '0',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = [];
    foreach ($form_state->getValues() as $key => $value) {
      $values[$key] = $value;
    }
    $password = Password::create([
      'name'           => $values['name'],
      'field_user_id'  => $values['field_user_id'],
      'field_password' => $values['field_password'],
      'field_link'     => $values['field_link'],
      'field_email'    => $values['field_email'],
      'field_notes'    => $values['field_notes'],
    ]);
    $password->save();
    \Drupal::messenger()->addMessage('Password created successfully.');
  }

}
