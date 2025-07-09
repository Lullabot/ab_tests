<?php

namespace Drupal\ab_blocks\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;

/**
 * Adds the 'hide_block' setting to a block.
 */
trait HideableBlockSettingsTrait {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['hide_block' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    $form['hide_block'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide block?'),
      '#default_value' => $configuration['hide_block'] ?? FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['hide_block'] = $form_state->getValue('hide_block');
  }

}
