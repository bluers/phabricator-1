<?php

/**
 * Created by IntelliJ IDEA.
 * User: jimmy
 * Date: 2017/3/23
 * Time: 下午10:58
 */
final class PhabricatorRepositoryManagementDownloadsWorkflow extends PhabricatorRepositoryManagementWorkflow
{
  protected function didConstruct() {
    $this
      ->setName('Downloads')
      ->setExamples('9999+')
      ->setSynopsis(
        pht(
          'Number of commit downloads.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {

    echo tsprintf(
      "%s\n",
      pht('NOP'));


  }

}
