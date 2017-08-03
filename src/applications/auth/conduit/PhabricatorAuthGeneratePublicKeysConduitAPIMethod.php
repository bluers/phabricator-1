<?php

final class PhabricatorAuthGeneratePublicKeysConduitAPIMethod
  extends PhabricatorAuthConduitAPIMethod {

  private $keyObject;

  public function getAPIMethodName() {
    return 'auth.generatepublickey';
  }

  public function getMethodDescription() {
    return pht('Generated public keys.');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'result-set';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer)
      ->withIsActive(true);

    $ids = $request->getValue('ids');
    if ($ids !== null) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids !== null) {
      $query->withPHIDs($phids);
    }

    $object_phids = array($viewer->getPHID());
    if ($object_phids !== null) {
      $query->withObjectPHIDs($object_phids);
    }
    else{
      $results = array(
        'data' => array(),
      );

      $pager = $this->newPager($request);
      return $this->addPagerResults($results, $pager);
    }

    $keys = $request->getValue('keys');
    if ($keys !== null) {
      $key_objects = array();
      foreach ($keys as $key) {
        $key_objects[] = PhabricatorAuthSSHPublicKey::newFromRawKey($key);
      }

      $query->withKeys($key_objects);
    }

    $pager = $this->newPager($request);
    $public_keys = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($public_keys as $public_key) {
      //deactivate all public_keys
      $xactions = array();
      $xactions[] = id(new PhabricatorAuthSSHKeyTransaction())
        ->setTransactionType(PhabricatorAuthSSHKeyTransaction::TYPE_DEACTIVATE)
        ->setNewValue(true);

      id(new PhabricatorAuthSSHKeyEditor())
        ->setActor($viewer)
        ->setContentSource(PhabricatorContentSource::newForSource(PhabricatorWebContentSource::SOURCECONST))
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($public_key, $xactions);
    }

    $key = $this->newKeyForObjectPHID($viewer->getPHID());
    $default_name = $key->getObject()->getSSHKeyDefaultName();
    $keys = PhabricatorSSHKeyGenerator::generateKeypair();
    list($public_key, $private_key) = $keys;

    $file = PhabricatorFile::newFromFileData(
      $private_key,
      array(
        'name' => $default_name.'.key',
        'ttl.relative' => phutil_units('10 minutes in seconds'),
        'viewPolicy' => $viewer->getPHID(),
      ));

    $public_key = PhabricatorAuthSSHPublicKey::newFromRawKey($public_key);

    $type = $public_key->getType();
    $body = $public_key->getBody();
    $comment = pht('Generated');

    $entire_key = "{$type} {$body} {$comment}";


    $type_create = PhabricatorTransactions::TYPE_CREATE;
    $type_name = PhabricatorAuthSSHKeyTransaction::TYPE_NAME;
    $type_key = PhabricatorAuthSSHKeyTransaction::TYPE_KEY;

    $xactions = array();

    $xactions[] = id(new PhabricatorAuthSSHKeyTransaction())
      ->setTransactionType($type_create);

    $xactions[] = id(new PhabricatorAuthSSHKeyTransaction())
      ->setTransactionType($type_name)
      ->setNewValue($default_name);

    $xactions[] = id(new PhabricatorAuthSSHKeyTransaction())
      ->setTransactionType($type_key)
      ->setNewValue($entire_key);

    $editor = id(new PhabricatorAuthSSHKeyEditor())
      ->setActor($viewer)
      ->setContentSource(PhabricatorContentSource::newForSource(PhabricatorWebContentSource::SOURCECONST))
      ->applyTransactions($key, $xactions);

    $results = array(
      'uri' => $file->getDownloadURI(),
      'publickey' => $entire_key,
    );

    return $results;
  }

  protected function loadSSHKeyObject($object_phid, $need_edit) {
    $viewer = $this->getViewer();

    $query = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid));

    if ($need_edit) {
      $query->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ));
    }

    $object = $query->executeOne();

    if (!$object) {
      return null;
    }

    // If this kind of object can't have SSH keys, don't let the viewer
    // add them.
    if (!($object instanceof PhabricatorSSHPublicKeyInterface)) {
      return null;
    }

    $this->keyObject = $object;

    return $object;
  }

  protected function newKeyForObjectPHID($object_phid) {
    $viewer = $this->getViewer();

    $object = $this->loadSSHKeyObject($object_phid, true);
    if (!$object) {
      return null;
    }

    return PhabricatorAuthSSHKey::initializeNewSSHKey($viewer, $object);
  }

}
