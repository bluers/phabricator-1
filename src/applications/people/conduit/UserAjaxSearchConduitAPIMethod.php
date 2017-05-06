<?php

final class UserAjaxSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'user.ajaxsearch';
  }

  public function newSearchEngine() {
    return new PhabricatorPeopleAjaxSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about users.');
  }

}
