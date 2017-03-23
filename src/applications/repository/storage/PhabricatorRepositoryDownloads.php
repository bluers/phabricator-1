<?php

/**
 * Created by IntelliJ IDEA.
 * User: jimmy
 * Date: 2017/3/23
 * Time: 下午11:03
 */
final class PhabricatorRepositoryDownloads extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface
{
  protected $key;
  protected $count;

  protected function getConfiguration() {
    return array(
        self::CONFIG_TIMESTAMPS => false,
        self::CONFIG_COLUMN_SCHEMA => array(
          'key' => 'text256',
          'count' => 'uint32',
        ),
        self::CONFIG_KEY_SCHEMA => array(
          'key_namekey' => array(
            'columns' => array('`key`'),
            'unique' => true,
          ),
        ),
      ) + parent::getConfiguration();
  }

  public static function incrementDownloads($id) {

    $table = new self();
    $table_name = $table->getTableName();
    $conn = $table->establishConnection('w');

    AphrontWriteGuard::beginUnguardedWrites();
      queryfx(
        $conn,
        'INSERT INTO %T (`key`, `count`) VALUES (%s, 1) ON DUPLICATE KEY UPDATE count=count+1;',
        $table_name,
        $id);
    AphrontWriteGuard::endUnguardedWrites();
  }

  public static function getDownloads($id) {

    $table = new self();
    $table_name = $table->getTableName();
    $conn = $table->establishConnection('w');

    $ret = queryfx_one(
      $conn,
      'SELECT IFNULL(sum(`count`),0) as c FROM %T WHERE `key`=%s;',
      $table_name,
      $id);

    return $ret["c"];
  }

  /* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }
}
