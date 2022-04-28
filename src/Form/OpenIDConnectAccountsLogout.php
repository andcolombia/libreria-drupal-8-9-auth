<?php

namespace Drupal\openid_connect\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\openid_connect\OpenIDConnectSession;
use Drupal\openid_connect\OpenIDConnectAuthmap;
use Drupal\openid_connect\OpenIDConnectClaims;
use Drupal\openid_connect\Plugin\OpenIDConnectClientManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides the user-specific OpenID Connect settings form.
 *
 * @package Drupal\openid_connect\Form
 */
class OpenIDConnectAccountsLogout extends FormBase implements ContainerInjectionInterface {

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The OpenID Connect session service.
   *
   * @var \Drupal\openid_connect\OpenIDConnectSession
   */
  protected $session;

  /**
   * The OpenID Connect authmap service.
   *
   * @var \Drupal\openid_connect\OpenIDConnectAuthmap
   */
  protected $authmap;

  /**
   * The OpenID Connect claims service.
   *
   * @var \Drupal\openid_connect\OpenIDConnectClaims
   */
  protected $claims;

  /**
   * The OpenID Connect client plugin manager.
   *
   * @var \Drupal\openid_connect\Plugin\OpenIDConnectClientManager
   */
  protected $pluginManager;

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user account.
   * @param \Drupal\openid_connect\OpenIDConnectSession $session
   *   The OpenID Connect service.
   * @param \Drupal\openid_connect\OpenIDConnectAuthmap $authmap
   *   The authmap storage.
   * @param \Drupal\openid_connect\OpenIDConnectClaims $claims
   *   The OpenID Connect claims.
   * @param \Drupal\openid_connect\Plugin\OpenIDConnectClientManager $plugin_manager
   *   The OpenID Connect client manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(
    AccountProxy $current_user,
    OpenIDConnectSession $session,
    OpenIDConnectAuthmap $authmap,
    OpenIDConnectClaims $claims,
    OpenIDConnectClientManager $plugin_manager,
    ConfigFactory $config_factory
  ) {

    $this->currentUser = $current_user;
    $this->session = $session;
    $this->authmap = $authmap;
    $this->claims = $claims;
    $this->pluginManager = $plugin_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('openid_connect.session'),
      $container->get('openid_connect.authmap'),
      $container->get('openid_connect.claims'),
      $container->get('plugin.manager.openid_connect_client'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openid_connect_accounts_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {
    $form_state->set('account', $user);

    $clients = $this->pluginManager->getDefinitions();

    $form['help'] = [
      '#prefix' => '<p class="description">',
      '#suffix' => '</p>',
    ];

    if (empty($clients)) {
      $form['help']['#markup'] = $this->t('No external account providers are available.');
      return $form;
    }
    elseif ($this->currentUser->id() == $user->id()) {
      $form['help']['#markup'] = $this->t('You can connect your account with these external providers.');
    }

    $connected_accounts = $this->authmap->getConnectedAccounts($user);

    foreach ($clients as $client) {
      $enabled = $this->configFactory
        ->getEditable('openid_connect.settings.' . $client['id'])
        ->get('enabled');
      if (!$enabled) {
        continue;
      }

      $form[$client['id']] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Provider: @title', ['@title' => $client['label']]),
      ];
      $fieldset = &$form[$client['id']];
      $connected = isset($connected_accounts[$client['id']]);
      $fieldset['status'] = [
        '#type' => 'item',
        '#title' => $this->t('Status'),
        '#markup' => $this->t('Not connected'),
      ];
      if ($connected) {
        $fieldset['status']['#markup'] = $this->t('Connected as %sub', [
          '%sub' => $connected_accounts[$client['id']],
        ]);
        $fieldset['openid_connect_client_' . $client['id'] . '_disconnect'] = [
          '#type' => 'submit',
          '#nameType' => 'logout',
          '#value' => $this->t('Disconnect from @client_title', ['@client_title' => $client['label']]),
          '#name' => 'disconnect__' . $client['id'],
        ];

        $fieldset['status']['#markup'] = $this->t('Not connected');
        $fieldset['openid_connect_client_' . $client['id'] . '_singlesignout'] = [
          '#type' => 'submit',
          '#nameType' => 'singlesignout',
          '#value' => $this->t('Single Sign Out with @client_title', ['@client_title' => $client['label']]),
          '#name' => 'SingleSignOut__' . $client['id'],
          '#access' => $this->currentUser->id() == $user->id(),
        ];

        $fieldset['status']['#markup'] = $this->t('Not connected');
        $fieldset['openid_connect_client_' . $client['id'] . '_personalizar'] = [
          '#type' => 'submit',
          '#nameType' => 'personalizar',
          '#value' => $this->t('Personalizar with @client_title', ['@client_title' => $client['label']]),
          '#name' => 'Personalizar__' . $client['id'],
          '#access' => $this->currentUser->id() == $user->id(),
        ];
      }
      else {
        $fieldset['status']['#markup'] = $this->t('Not connected');
        $fieldset['openid_connect_client_' . $client['id'] . '_connect'] = [
          '#type' => 'submit',
          '#nameType' => 'logout',
          '#value' => $this->t('Connect with @client_title', ['@client_title' => $client['label']]),
          '#name' => 'connect__' . $client['id'],
          '#access' => $this->currentUser->id() == $user->id(),
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    list($op, $client_name) = explode('__', $form_state->getTriggeringElement()['#name'], 2);

    $configuration = $this->config('openid_connect.settings.' . $client_name)
      ->get('settings');
      /** @var \Drupal\openid_connect\Plugin\OpenIDConnectClientInterface $client */
      $client = $this->pluginManager->createInstance(
        $client_name,
        $configuration
      );
    $type = $form_state->getTriggeringElement()['#nameType'];
    if($type === 'logout'){
      if ($op === 'disconnect') {
        global $user;
        
        $id_token = $_SESSION['token_tmp'];
  
        $response = $client->endsession($id_token);
        session_destroy();
        return new RedirectResponse(base_path().'/user/logout');
      }
  
      if ($this->currentUser->id() !== $form_state->get('account')->id()) {
        $this->messenger()->addError($this->t("You cannot connect another user's account."));
        return;
      }
  
      $this->session->saveDestination();
      $scopes = $this->claims->getScopes($client);
      $_SESSION['openid_connect_op'] = $op;
      $_SESSION['openid_connect_connect_uid'] = $this->currentUser->id();
      $response = $client->authorize($scopes, $form_state);
      $form_state->setResponse($response);
    }
    else if($type === 'singlesignout'){
      $scopes = $this->claims->getScopes($client);
      $response = $client->authorize($scopes,'refresh', $_SESSION['login_hint']);
      $form_state->setResponse($response);
    }
    else if($type === 'personalizar'){
      $scopes = $this->claims->getScopes($client);
      $response = $client->authorize($scopes,'personalizar', $_SESSION['login_hint']);
      $form_state->setResponse($response);
    }
  }

  /**
   * Checks access for the OpenID-Connect accounts form.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user having accounts.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $user) {
    if ($this->currentUser->hasPermission('administer users')) {
      return AccessResult::allowed();
    }

    if ($this->currentUser->id() && $this->currentUser->id() === $user->id() &&
      $this->currentUser->hasPermission('manage own openid connect accounts')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
