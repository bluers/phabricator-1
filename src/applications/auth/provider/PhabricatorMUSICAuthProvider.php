<?php

final class PhutilMUSICAuthAdapter extends PhutilAuthAdapter{

  private $hostname;
  private $loginUsername;
  private $loginPassword;

  private $webUserId;//这个对应Phabricator的user name
  private $webUserName;//这个对应Phabricator的real name
  private $email;
  private $verifyStatus;


  public function getAdapterType() {
    return 'music';
  }

  public function setHostname($host) {
    $this->hostname = $host;
    return $this;
  }

  public function authWithMUSIC(){
    $authUrl = "$this->hostname"."?webUserId=".$this->loginUsername."&pwd=".$this->loginPassword."";
    $authResultRaw = file_get_contents($authUrl);
    $authResult = json_decode($authResultRaw);
    $returnCode = $authResult["returnCode"];
    if($returnCode == "0"){
      $ds = $authResult["DS"][0];
      $this->email = $ds["email"];
      $this->webUserId = $ds["WEB_USER_ID"];
      $this->webUserName = $ds["WEB_USER_ID"];
      $this->verifyStatus = $ds["VERIFY_STATUS"];
    }
  }

  public function getAdapterDomain() {
    return 'self';
  }

  public function getAccountID() {
    return $this->getAccountName();//MUSIC的accountId同accountName
  }

  public function getAccountName() {
    return $this->webUserId;
  }

  public function getAccountRealName() {
    return $this->webUserName;
  }

  public function getAccountEmail() {
    return $this->email;
  }

  public function setLoginUsername($login_username) {
    $this->loginUsername = $login_username;
    return $this;
  }

  public function setLoginPassword(PhutilOpaqueEnvelope $login_password) {
    $this->loginPassword = $login_password;
    return $this;
  }

}

final class PhabricatorMUSICAuthProvider extends PhabricatorAuthProvider {

  private $adapter;

  public function getProviderName() {
    return pht('MUSIC');
  }

  public function getDescriptionForCreate() {
    return pht(
      'Configure a connection to an MUSIC server so that users can use their '.
      'MUSIC credentials to log in to Phabricator.');
  }

  public function getDefaultProviderConfig() {
    return parent::getDefaultProviderConfig();
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $conf = $this->getProviderConfig();

      $adapter = id(new PhutilMUSICAuthAdapter())
        ->setHostname(
          $conf->getProperty(self::KEY_HOSTNAME));
      $this->adapter = $adapter;
    }
    return $this->adapter;
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setSubmitURI($this->getLoginURI())
      ->setUser($viewer);

    if ($mode == 'link') {
      $dialog->setTitle(pht('Link MUSIC Account'));
      $dialog->addSubmitButton(pht('Link Accounts'));
      $dialog->addCancelButton($this->getSettingsURI());
    } else if ($mode == 'refresh') {
      $dialog->setTitle(pht('Refresh MUSIC Account'));
      $dialog->addSubmitButton(pht('Refresh Account'));
      $dialog->addCancelButton($this->getSettingsURI());
    } else {
      if ($this->shouldAllowRegistration()) {
        $dialog->setTitle(pht('Login or Register with MUSIC'));
        $dialog->addSubmitButton(pht('Login or Register'));
      } else {
        $dialog->setTitle(pht('Login with MUSIC'));
        $dialog->addSubmitButton(pht('Login'));
      }
      if ($mode == 'login') {
        $dialog->addCancelButton($this->getStartURI());
      }
    }

    $v_user = $request->getStr('music_username');

    $e_user = null;
    $e_pass = null;

    $errors = array();
    if ($request->isHTTPPost()) {
      // NOTE: This is intentionally vague so as not to disclose whether a
      // given username exists.
      $e_user = pht('Invalid');
      $e_pass = pht('Invalid');
      $errors[] = pht('Username or password are incorrect.');
    }

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer)
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('MUSIC Username'))
          ->setName('music_username')
          ->setValue($v_user)
          ->setError($e_user))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('MUSIC Password'))
          ->setName('music_password')
          ->setError($e_pass));

    if ($errors) {
      $errors = id(new PHUIInfoView())->setErrors($errors);
    }

    $dialog->appendChild($errors);
    $dialog->appendChild($form);


    return $dialog;
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $viewer = $request->getUser();
    $response = null;
    $account = null;

    $username = $request->getStr('music_username');
    $password = $request->getStr('music_password');
    $has_password = strlen($password);
    $password = new PhutilOpaqueEnvelope($password);

    if (!strlen($username) || !$has_password) {
      $response = $controller->buildProviderPageResponse(
        $this,
        $this->renderLoginForm($request, 'login'));
      return array($account, $response);
    }

    if ($request->isFormPost()) {
      try {
        if (strlen($username) && $has_password) {
          $adapter = $this->getAdapter();
          $adapter->setLoginUsername($username);
          $adapter->setLoginPassword($password);

          $adapter->authWithMUSIC();

          DarkConsoleErrorLogPluginAPI::enableDiscardMode();
          $account_id = $adapter->getAccountID();
          DarkConsoleErrorLogPluginAPI::disableDiscardMode();
        } else {
          throw new Exception(pht('Username and password are required!'));
        }
      } catch (PhutilAuthCredentialException $ex) {
        $response = $controller->buildProviderPageResponse(
          $this,
          $this->renderLoginForm($request, 'login'));
        return array($account, $response);
      } catch (Exception $ex) {
        // TODO: Make this cleaner.
        throw $ex;
      }
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }


  const KEY_HOSTNAME                = 'music:url';

  private function getPropertyKeys() {
    return array_keys($this->getPropertyLabels());
  }

  private function getPropertyLabels() {
    return array(
      self::KEY_HOSTNAME => pht('MUSIC的登陆API接口地址'),
    );
  }

  public function readFormValuesFromProvider() {
    $properties = array();
    foreach ($this->getPropertyLabels() as $key => $ignored) {
      $properties[$key] = $this->getProviderConfig()->getProperty($key);
    }
    return $properties;
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    $values = array();
    foreach ($this->getPropertyKeys() as $key) {
      switch ($key) {
        case self::KEY_REALNAME_ATTRIBUTES:
          $values[$key] = $request->getStrList($key, array());
          break;
        default:
          $values[$key] = $request->getStr($key);
          break;
      }
    }

    return $values;
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {
    $errors = array();
    $issues = array();
    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    self::assertLDAPExtensionInstalled();

    $labels = $this->getPropertyLabels();

    $captions = array(
      self::KEY_HOSTNAME =>
        pht('Example: %s%sFor MUSIC, use: %s',
          phutil_tag('tt', array(), pht('http://10.20.69.101:8008/MUSIC_MetaData/login')),
          phutil_tag('br'),
          phutil_tag('tt', array(), pht('http://10.20.69.101:8008/MUSIC_MetaData/login'))),

    );

    $types = array();

    foreach ($labels as $key => $label) {
      $caption = idx($captions, $key);
      $type = idx($types, $key);
      $value = idx($values, $key);

      $control = null;
      switch ($type) {
        case 'checkbox':
          $control = id(new AphrontFormCheckboxControl())
            ->addCheckbox(
              $key,
              1,
              hsprintf('<strong>%s:</strong> %s', $label, $caption),
              $value);
          break;
        case 'list':
          $control = id(new AphrontFormTextControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value ? implode(', ', $value) : null);
          break;
        case 'password':
          $control = id(new AphrontFormPasswordControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setDisableAutocomplete(true)
            ->setValue($value);
          break;
        case 'textarea':
          $control = id(new AphrontFormTextAreaControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value);
          break;
        default:
          $control = id(new AphrontFormTextControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value);
          break;
      }

      $form->appendChild($control);
    }
  }

  public function renderConfigPropertyTransactionTitle(
    PhabricatorAuthProviderConfigTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    $key = $xaction->getMetadataValue(
      PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);

    $labels = $this->getPropertyLabels();
    if (isset($labels[$key])) {
      $label = $labels[$key];

      if ($old === null || $old === '') {
        return pht(
          '%s set the "%s" value to "%s".',
          $xaction->renderHandleLink($author_phid),
          $label,
          $new);
      } else {
        return pht(
          '%s changed the "%s" value from "%s" to "%s".',
          $xaction->renderHandleLink($author_phid),
          $label,
          $old,
          $new);
      }
    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

  public static function getMUSICProvider() {
    $providers = self::getAllEnabledProviders();

    foreach ($providers as $provider) {
      if ($provider instanceof PhabricatorMUSICAuthProvider) {
        return $provider;
      }
    }

    return null;
  }

}
