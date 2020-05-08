<?php

namespace Drupal\commerce_credomatic  \Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\Exception\AuthenticationException;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "cardinal",
 *   label = "Credomatic Cardinal (Redirect to Credomatic Cardinal)",
 *   display_label = "Credomatic Cardinal",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_credomatic\PluginForm\CardinalForm",
 *     "capture-payment" = "Drupal\commerce_payment\PluginForm\PaymentCaptureForm",
 *     "void-payment" = "Drupal\commerce_payment\PluginForm\PaymentVoidForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "mastercard", "visa",
 *   },
 * )
 */
class Cardinal extends OffsitePaymentGatewayBase {

  /**
   * Url.
   *
   * @var string
   */
  protected $url;

  /**
   * HTTP Client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Constructs a new Offsite object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, ClientInterface $client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
    $this->url = 'https://credomatic.compassmerchantsolutions.com/api/transact.php';
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'key' => '',
      'key_id' => '',
      'processor_id' => '',
      'username' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['mode']['#default_value'] = 'live';
    $form['mode']['#access'] = FALSE;

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
      '#default_value' => $this->configuration['key'],
      '#required' => TRUE,
    ];

    $form['key_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key ID'),
      '#default_value' => $this->configuration['key_id'],
      '#required' => TRUE,
    ];

    $form['processor_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Processor ID'),
      '#default_value' => $this->configuration['processor_id'],
      '#required' => FALSE,
    ];

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $this->configuration['username'],
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $payment_state = $payment->getState()->getId();
    $operations = [];
    $operations['capture'] = [
      'title' => $this->t('Capture'),
      'page_title' => $this->t('Capture payment'),
      'plugin_form' => 'capture-payment',
      'access' => $payment_state == 'auth',
    ];

    $operations['void'] = [
      'title' => $this->t('Void'),
      'page_title' => $this->t('Void payment'),
      'plugin_form' => 'void-payment',
      'access' => $payment_state == 'auth',
    ];

    return $operations;
  }

  /**
   * Returns Key.
   */
  public function getKey() {
    return $this->configuration['key'] ?: '';
  }

  /**
   * Returns Key ID.
   */
  public function getKeyId() {
    return $this->configuration['key_id'] ?: '';
  }

  /**
   * Returns Processor ID.
   */
  public function getProcessorId() {
    return $this->configuration['processor_id'] ?: '';
  }

  /**
   * Returns url.
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Returns username.
   */
  public function getUsername() {
    return $this->configuration['username'] ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['key'] = $values['key'];
      $this->configuration['key_id'] = $values['key_id'];
      $this->configuration['processor_id'] = $values['processor_id'];
      $this->configuration['username'] = $values['username'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $response = $request->query->all();
    try {
      if ($this->validateResponse($response)) {
        $amount = $order->getTotalPrice();
        $amount_number = number_format(round($amount->getNumber(), 2), 2, '.', '');
        if ($response['amount'] == $amount_number) {
          $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
          $payment = $payment_storage->create([
            'state' => $response['type'],
            'amount' => $amount,
            'payment_gateway' => $this->entityId,
            'order_id' => $order->id(),
            'test' => FALSE,
            'remote_id' => $response['transactionid'],
            'authorized' => $this->time->getRequestTime(),
          ]);
          $payment->save();
        }
        else {
          throw new InvalidResponseException($this->t('Amount was changed in an unauthorized way'));
        }
      }
    }
    catch (PaymentGatewayException $e) {
      // Log and throw again.
      watchdog_exception('commerce_credomatic', $e);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, array $payment_details, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    global $base_url;
    // Remember to take into account $capture when performing the request.
    $amount = $payment->getAmount();
    if ($amount->getCurrencyCode() !== $this->getCurrency()) {
      throw new AuthenticationException($this->t('Payment currency @currency does not match gateway currency @gateway_currency', [
        '@currency' => $amount->getCurrencyCode(),
        '@gateway_currency' => $this->getCurrency(),
      ]));
    }
    $amount_number = number_format(round($amount->getNumber(), 2), 2, '.', '');
    $remote_id = $payment_method->getRemoteId();
    $time = $this->time->getRequestTime();
    $parameters = [
      'type' => 'auth',
      'key_id' => $this->getKeyId(),
      'hash' => $this->getHash([
        $payment->getOrderId(),
        $amount_number,
        $time,
        $this->getKey(),
      ]),
      'time' => $time,
      'ccnumber' => $payment_details['number'],
      'ccexp' => $payment_method->card_exp_month->value . $payment_method->card_exp_year->value,
      'amount' => $amount_number,
      'orderid' => $payment->getOrderId(),
      'cvv' => $payment_details['security_code'],
      'processor_id' => $this->getProcessorId(),
      'username' => $this->getUsername(),
    ];
    $result = $this->doPost($parameters);
    $this->validateResponse($result);
    $next_state = 'authorization';
    $payment->setState($next_state);
    $payment->setRemoteId($result['transactionid']);
    $payment->save();
    if ($capture) {
      $this->capturePayment($payment);
    }
  }

  /**
   * Validate response array formatted from cardinal.
   *
   * @param array $response
   *   The response array.
   *
   * @return bool
   *   Whether response is valid or not.
   */
  protected function validateResponse(array $response) {
    if (isset($response['response'])) {
      if ($response['response'] == 2) {
        throw new DeclineException($this->t('Denied transaction. Text: @text', ['@text' => $response['responsetext']]));
      }
      elseif ($response['response'] == 3) {
        throw new AuthenticationException($this->t('Data error in the transaction or system error. Text: @text', ['@text' => $response['responsetext']]));
      }
    }
    elseif (isset($response['esponse'])) {
      // There is a typo in some responses.
      if ($response['esponse'] == 2) {
        throw new DeclineException($this->t('Denied transaction. Text: @text', ['@text' => $response['responsetext']]));
      }
      elseif ($response['esponse'] == 3) {
        throw new AuthenticationException($this->t('Data error in the transaction or system error. Text: @text', ['@text' => $response['responsetext']]));
      }
    }
    else {
      throw new InvalidResponseException($this->t('Response value not found'));
    }
    if (!empty($result['avsresponse'])) {
      throw new DeclineException($this->t('AVS response error. Code: @code', [
        '@code' => $response['avsresponse'],
      ]));
    }
    if (!empty($result['cvvresponse'])) {
      throw new DeclineException($this->t('CVV response error. Code: @code', [
        '@code' => $response['cvvresponse'],
      ]));
    }
    if (isset($response['response_code'])) {
      if ($response['response_code'] != 100) {
        throw new DeclineException($this->t('Denied transaction. Code: @code', [
          '@code' => $response['response_code'],
        ]));
      }
    }
    else {
      throw new InvalidResponseException($this->t('Response code value not found'));
    }

    $hash_elements = [];
    $hash_elements[] = isset($response['orderid']) ? $response['orderid'] : '';
    $hash_elements[] = isset($response['amount']) ? $response['amount'] : '';
    // Take typo into account.
    if (isset($response['response'])) {
      $hash_elements[] = $response['response'];
    }
    elseif ($response['esponse']) {
      $hash_elements[] = $response['esponse'];
    }
    else {
      $hash_elements[] = '';
    }
    $hash_elements[] = isset($response['transactionid']) ? $response['transactionid'] : '';
    $hash_elements[] = isset($response['avsresponse']) ? $response['avsresponse'] : '';
    $hash_elements[] = isset($response['cvvresponse']) ? $response['cvvresponse'] : '';
    $hash_elements[] = isset($response['time']) ? $response['time'] : '';
    $hash_elements[] = $this->getKey();

    $hash = $this->getHash($hash_elements);
    if (isset($response['hash']) && $hash !== $response['hash']) {
      throw new InvalidResponseException($this->t('Hash can not be verified'));
    }

    return TRUE;
  }

  /**
   * Do POST Request.
   */
  protected function doPost($parameters) {
    $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
    $response = $this->client->request('POST', $this->getUrl(), [
      'form_params' => $parameters,
      'timeout' => 10,
    ]);
    $contents = $response->getBody()->getContents();
    $contents = substr($contents, 1);
    $response_data = explode('&', $contents);
    $data = [];
    foreach ($response_data as $data_element) {
      $parts = explode('=', $data_element);
      $data[$parts[0]] = $parts[1];
    }
    return $data;
  }

  /**
   * Returns hash from provided values.
   */
  public function getHash($values) {
    $string = implode('|', $values);
    return md5($string);
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['auth']);

    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    $remote_id = $payment->getRemoteId();
    $amount_number = number_format(round($amount->getNumber(), 2), 2, '.', '');
    $parameters = [
      'type' => 'capture',
      'key_id' => $this->getKeyId(),
      'hash' => $this->getHash([
        '',
        $amount_number,
        $this->time->getRequestTime(),
        $this->getKey(),
      ]),
      'time' => $this->time->getRequestTime(),
      'transactionid' => $remote_id,
      'amount' => $amount_number,
      'processor_id' => $this->getProcessorId(),
      'username' => $this->getUsername(),
    ];
    $result = $this->doPost($parameters);
    $this->validateResponse($result);
    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['auth']);
    $remote_id = $payment->getRemoteId();
    $time = $this->time->getRequestTime();
    $parameters = [
      'type' => 'void',
      'key_id' => $this->getKeyId(),
      'hash' => $this->getHash([
        '',
        '',
        $time,
        $this->getKey(),
      ]),
      'time' => $time,
      'transactionid' => $remote_id,
      'username' => $this->getUsername(),
    ];
    if (!empty($this->getProcessorId())) {
      $parameters['processor_id'] = $this->getProcessorId();
    }
    $result = $this->doPost($parameters);
    $this->validateResponse($result);
    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, array $payment_details, Price $amount = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      'type',
      'number',
      'expiration',
      'security_code',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    $payment_method->setReusable(FALSE);
    $payment_method->card_type = $payment_details['type'];
    // Only the last 4 numbers are safe to store.
    $payment_method->card_number = substr($payment_details['number'], -4);
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    // Last 2 digits of year.
    $payment_method->card_exp_year = substr($payment_details['expiration']['year'], -2);
    $expires = CreditCard::calculateExpirationTimestamp($payment_details['expiration']['month'], $payment_details['expiration']['year']);
    $remote_id = '-1';

    $payment_method->setRemoteId($remote_id);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $payment_method->delete();
  }

}
