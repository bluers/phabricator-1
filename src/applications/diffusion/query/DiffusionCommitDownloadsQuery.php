<?php

/**
 * Created by IntelliJ IDEA.
 * User: jimmy
 * Date: 2017/3/23
 * Time: 下午11:53
 */
final class DiffusionCommitDownloadsQuery extends PhabricatorCursorPagedPolicyAwareQuery
{
  private $ids;

  public function withKeys(array $ids) {
    $this->ids = $ids;
    return $this;
  }


  public function newResultObject() {
    return new PhabricatorRepositoryDownloads();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        '`key` IN (%Ls)',
        $this->ids);
    }
    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
