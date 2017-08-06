<?php

final class PhabricatorPeopleBecomeAdminProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.becomeadmin';

  public function getMenuItemTypeName() {
    return pht('Make Administrator');
  }

  private function getDefaultName() {
    return pht('Make Administrator');
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $name = $config->getMenuItemProperty('name');

    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setPlaceholder($this->getDefaultName())
        ->setValue($config->getMenuItemProperty('name')),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $user = $config->getProfileObject();
    $id = $user->getID();

    if ($user->getIsAdmin()) {
      $empower_icon = 'fa-arrow-circle-o-down';
      $empower_name = pht('Remove Administrator');
    } else {
      $empower_icon = 'fa-arrow-circle-o-up';
      $empower_name = pht('Make Administrator');
    }

    $item = $this->newItem()
      ->setHref("/people/empower/{$id}/")
      ->setName($empower_name)
      ->setIcon($empower_icon);

    return array(
      $item,
    );
  }

}
