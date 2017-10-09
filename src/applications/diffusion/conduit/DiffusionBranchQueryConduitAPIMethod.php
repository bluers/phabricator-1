<?php

final class DiffusionBranchQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.branchquery';
  }

  public function getMethodDescription() {
    return pht('Determine what branches exist for a repository.');
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'closed' => 'optional bool',
      'limit' => 'optional int',
      'offset' => 'optional int',
      'contains' => 'optional string',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $contains = $request->getValue('contains');
    if (strlen($contains)) {
      // NOTE: We can't use DiffusionLowLevelGitRefQuery here because
      // `git for-each-ref` does not support `--contains`.
      if ($repository->isWorkingCopyBare()) {
        list($stdout) = $repository->execxLocalCommand(
          'branch --verbose --no-abbrev --contains %s --',
          $contains);
        $ref_map = DiffusionGitBranch::parseLocalBranchOutput(
          $stdout);
      } else {
        list($stdout) = $repository->execxLocalCommand(
          'branch -r --verbose --no-abbrev --contains %s --',
          $contains);
        $ref_map = DiffusionGitBranch::parseRemoteBranchOutput(
          $stdout,
          DiffusionGitBranch::DEFAULT_GIT_REMOTE);
      }

      $refs = array();
      foreach ($ref_map as $ref => $commit) {
        $refs[] = id(new DiffusionRepositoryRef())
          ->setShortName($ref)
          ->setCommitIdentifier($commit);
      }
    } else {
      $refs = id(new DiffusionLowLevelGitRefQuery())
        ->setRepository($repository)
        ->withRefTypes(
          array(
            PhabricatorRepositoryRefCursor::TYPE_BRANCH,
          ))
        ->execute();
    }

    return $this->processBranchRefs($request, $refs);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $query = id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository);

    $contains = $request->getValue('contains');
    if (strlen($contains)) {
      $query->withContainsCommit($contains);
    }

    $refs = $query->execute();

    return $this->processBranchRefs($request, $refs);
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    // Since SVN doesn't have meaningful branches, just return nothing for all
    // queries.
    $branches = array();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $user = PhabricatorUser::getOmnipotentUser();

    $query = id(new PhabricatorRepositoryQuery())
      ->needProjectPHIDs(true)
      ->needCommitCounts(true)
      ->needMostRecentCommits(true)
      ->withPHIDs(array($repository->getPHID()))
      ->setViewer($user);

    $repositories = $query->execute();
    $repository = $repositories[$repository->getID()];



    $params = array(
      'repository' => $repository->getID(),
      'user' => $user,
      'blob' => null,
      'commit' => null,
      'path' => null,
      'line' => null,
      'branch' => null,
      'lint' => null,
    );

    $drequest = DiffusionRequest::newFromDictionary($params);

    $method = 'diffusion.browsequery';

    if($repository->getMostRecentCommit() == null){
      return $branches;
    }

    try {
      $browseDocFuture = DiffusionQuery::callConduitWithDiffusionRequest(
        $user,
        $drequest,
        $method,
        array(
          'commit' => $repository->getMostRecentCommit()->getCommitIdentifier(),
          'path' => '/branches',
          'limit' => 1000 + 1,
        ), true);

      $browsedoc_results = $browseDocFuture->resolve();
      $browsedoc_results = DiffusionBrowseResultSet::newFromConduit(
        $browsedoc_results);

      $browsedoc_paths = $browsedoc_results->getPaths();

      foreach ($browsedoc_paths as $item) {
        $file_type = $item->getFileType();
        if ($file_type != DifferentialChangeType::FILE_DIRECTORY) {
          continue;
        }
        $ref = new DiffusionRepositoryRef();

        $path = $item->getFullPath();
        if(substr($path,0, 8) == "branches"){
          $path = "branches_".substr($path,9);
        }

        $path = rtrim($path, '/');
        if(strpos($path, '/') > 0){
          $path = null;
        }


      if($path != null) {
        $key = $repository->getPHID() . "_" . $path;
        $download_count = PhabricatorRepositoryDownloads::getDownloads($key);
      }
      else{
        $download_count = 0;
      }

        $ref->setRawFields(array("epoch" => $repository->getMostRecentCommit()->getEpoch(),
          "fullPath" => "/".$item->getFullPath(),
          "browseUri" => $drequest->generateURI(
            array(
              'action' => 'browse',
              'path'   => "/".$item->getFullPath(),
            ))->getPath(),
          "downloadUri" => $drequest->generateURI(
            array(
              'action' => 'download',
              'path'   => "/".$item->getFullPath(),
            ))->getPath(),
          "download_count" => $download_count,
          ));
        $ref->setCommitIdentifier($repository->getMostRecentCommit()->getCommitIdentifier());
        $ref->setShortName($item->getPath());
        $ref->setRefType(PhabricatorRepositoryRefCursor::TYPE_BRANCH);
        $branches[] = $ref;

        $data = $item->getLastCommitData();
        if ($data) {
          if ($data->getCommitDetail('authorPHID')) {
            $phids[$data->getCommitDetail('authorPHID')] = true;
          }
          if ($data->getCommitDetail('committerPHID')) {
            $phids[$data->getCommitDetail('committerPHID')] = true;
          }
        }
      }

      $browsedoc_exception = null;
    } catch (Exception $ex) {
      $browsedoc_paths = null;
      $browsedoc_exception = $ex;
    }



    return mpull($branches, 'toDictionary');;
  }

  private function processBranchRefs(ConduitAPIRequest $request, array $refs) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');

    foreach ($refs as $key => $ref) {
      if (!$repository->shouldTrackBranch($ref->getShortName())) {
        unset($refs[$key]);
      }
    }

    $with_closed = $request->getValue('closed');
    if ($with_closed !== null) {
      foreach ($refs as $key => $ref) {
        $fields = $ref->getRawFields();
        if (idx($fields, 'closed') != $with_closed) {
          unset($refs[$key]);
        }
      }
    }

    // NOTE: We can't apply the offset or limit until here, because we may have
    // filtered untrackable branches out of the result set.

    if ($offset) {
      $refs = array_slice($refs, $offset);
    }

    if ($limit) {
      $refs = array_slice($refs, 0, $limit);
    }

    return mpull($refs, 'toDictionary');
  }

}
