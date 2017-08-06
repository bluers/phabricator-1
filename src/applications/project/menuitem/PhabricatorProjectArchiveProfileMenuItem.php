<?php

final class PhabricatorProjectArchiveProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project.archive';

  public function getMenuItemTypeName() {
    return pht('Archive Project');
  }

  private function getDefaultName() {
    return pht('Archive Project');
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
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

    $project = $config->getProfileObject();

    $id = $project->getID();

    $name = $this->getDisplayName($config);
    $icon = 'fa-ban';
    $href = "/project/archive/{$id}/";

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
