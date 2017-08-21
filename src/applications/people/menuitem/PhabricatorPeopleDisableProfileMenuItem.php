<?php

final class PhabricatorPeopleDisableProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.disable';

  public function getMenuItemTypeName() {
    return pht('Disable User');
  }

  private function getDefaultName() {
    return pht('Disable User');
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

    if ($user->getIsDisabled()) {
      $disable_icon = 'fa-check-circle-o';
      $disable_name = pht('Enable User');
    } else {
      $disable_icon = 'fa-ban';
      $disable_name = pht('Disable User');
    }

    $item = $this->newItem()
      ->setHref("/people/disable/{$id}/")
      ->setName($disable_name)
      ->setIcon($disable_icon);

    return array(
      $item,
    );
  }

}
