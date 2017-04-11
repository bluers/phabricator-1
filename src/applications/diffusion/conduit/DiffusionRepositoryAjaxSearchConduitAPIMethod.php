<?php

/**
 * Created by IntelliJ IDEA.
 * User: jimmy
 * Date: 2017/4/11
 * Time: 下午8:46
 */
class DiffusionRepositoryAjaxSearchConduitAPIMethod extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.repository.ajaxsearch';
  }

  public function newSearchEngine() {
    return new PhabricatorRepositoryAjaxSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about repositories.');
  }

}
