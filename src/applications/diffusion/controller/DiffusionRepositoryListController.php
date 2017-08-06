<?php

final class DiffusionRepositoryListController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $items = array();

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName(pht('Commits'));

    $items[] = id(new PHUIListItemView())
      ->setName(pht('Browse Commits'))
      ->setHref($this->getApplicationURI('commit/'));

    return id(new PhabricatorRepositorySearchEngine())
      ->setController($this)
      //->setNavigationItems($items)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    /*
     * 屏蔽代码库列表右上角的 "创建仓库" 按钮
     * id(new DiffusionRepositoryEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);
    */
    return $crumbs;
  }

}
