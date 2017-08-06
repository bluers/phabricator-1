<?php

final class PhabricatorPeopleBecomeDevProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.becomedev';

  public function getMenuItemTypeName() {
    return pht('Make Developer');
  }

  private function getDefaultName() {
    return pht('Make Developer');
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

    if ($user->getIsDev()) {
      $empower_icon = 'fa-arrow-circle-o-down';
      $empower_name = pht('Remove Developer');
    } else {
      $empower_icon = 'fa-arrow-circle-o-up';
      $empower_name = pht('Make Developer');
    }

    $item = $this->newItem()
      ->setHref("/people/makedev/{$id}/")
      ->setName($empower_name)
      ->setIcon($empower_icon);

    return array(
      $item,
    );
  }
}
