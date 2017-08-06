<?php

final class PhabricatorPeopleDeleteProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.delete';

  public function getMenuItemTypeName() {
    return pht('Delete User');
  }

  private function getDefaultName() {
    return pht('Delete User');
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

    $item = $this->newItem()
      ->setHref("/people/delete/{$id}/")
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-times');

    return array(
      $item,
    );
  }

}
