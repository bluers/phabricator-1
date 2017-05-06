<?php

final class OrganizationSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'organization.search';
  }

  public function newSearchEngine() {
    return new PhabricatorOrganizationSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about organizations.');
  }

  protected function getCustomQueryMaps($query) {
    return array(
      'slugMap' => $query->getSlugMap(),
    );
  }

}
