<?php

/**
 * @file
 * Contains \Drupal\demo_formatter\Plugin\Field\FieldFormatter\DemoFieldFormatter.
 */

namespace Drupal\demo_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'demo_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "demo_field_formatter",
 *   label = @Translation("Demo field formatter"),
 *   field_types = {
 *     "email"
 *   }
 * )
 */
class DemoFieldFormatter extends FormatterBase {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      // Implement default settings.
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return array(
      // Implement settings form.
    ) + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    // Implement settings summary.

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    \Drupal::logger('demo_formatter')->notice('HOOLA');
    foreach ($items as $delta => $item) {
      $url = Url::fromUri('mailto:' . $this->viewValue($item), array(
        'absolute' => TRUE,
      ));
      $link = new Link('Send email', $url);
      $elements[$delta] = $link->toRenderable();
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return nl2br(Html::escape($item->value));
  }

}
