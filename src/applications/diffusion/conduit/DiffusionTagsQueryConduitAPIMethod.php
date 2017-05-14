<?php

final class DiffusionTagsQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.tagsquery';
  }

  public function getMethodDescription() {
    return pht('Retrieve information about tags in a repository.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'names' => 'optional list<string>',
      'commit' => 'optional string',
      'needMessages' => 'optional bool',
      'offset' => 'optional int',
      'limit' => 'optional int',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $drequest->getSymbolicCommit();

    $commit_filter = null;
    if ($commit) {
      $commit_filter = $this->loadTagNamesForCommit($commit);
    }

    $name_filter = $request->getValue('names', null);

    $all_tags = $this->loadGitTagList();
    $all_tags = mpull($all_tags, null, 'getName');

    if ($name_filter !== null) {
      $all_tags = array_intersect_key($all_tags, array_fuse($name_filter));
    }
    if ($commit_filter !== null) {
      $all_tags = array_intersect_key($all_tags, $commit_filter);
    }

    $tags = array_values($all_tags);

    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');
    if ($offset) {
      $tags = array_slice($tags, $offset);
    }

    if ($limit) {
      $tags = array_slice($tags, 0, $limit);
    }

    if ($request->getValue('needMessages')) {
      $this->loadMessagesForTags($all_tags);
    }

    return mpull($tags, 'toDictionary');
  }

  private function loadGitTagList() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withRefTypes(
        array(
          PhabricatorRepositoryRefCursor::TYPE_TAG,
        ))
      ->execute();

    $tags = array();
    foreach ($refs as $ref) {
      $fields = $ref->getRawFields();
      $tag = id(new DiffusionRepositoryTag())
        ->setAuthor($fields['author'])
        ->setEpoch($fields['epoch'])
        ->setCommitIdentifier($ref->getCommitIdentifier())
        ->setName($ref->getShortName())
        ->setDescription($fields['subject'])
        ->setType('git/'.$fields['objecttype']);

      $tags[] = $tag;
    }

    return $tags;
  }

  private function loadTagNamesForCommit($commit) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    list($err, $stdout) = $repository->execLocalCommand(
      'tag -l --contains %s',
      $commit);

    if ($err) {
      // Git exits with an error code if the commit is bogus.
      return array();
    }

    $stdout = rtrim($stdout, "\n");
    if (!strlen($stdout)) {
      return array();
    }

    $tag_names = explode("\n", $stdout);
    $tag_names = array_fill_keys($tag_names, true);

    return $tag_names;
  }

  private function loadMessagesForTags(array $tags) {
    assert_instances_of($tags, 'DiffusionRepositoryTag');

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $futures = array();
    foreach ($tags as $key => $tag) {
      $futures[$key] = $repository->getLocalCommandFuture(
        'cat-file tag %s',
        $tag->getName());
    }

    id(new FutureIterator($futures))
      ->resolveAll();

    foreach ($tags as $key => $tag) {
      $future = $futures[$key];
      list($err, $stdout) = $future->resolve();

      $message = null;
      if ($err) {
        // Not all tags are actually "tag" objects: a "tag" object is only
        // created if you provide a message or sign the tag. Tags created with
        // `git tag x [commit]` are "lightweight tags" and `git cat-file tag`
        // will fail on them. This is fine: they don't have messages.
      } else {
        $parts = explode("\n\n", $stdout, 2);
        if (count($parts) == 2) {
          $message = last($parts);
        }
      }

      $tag->attachMessage($message);
    }

    return $tags;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    // For now, we don't support Mercurial tags via API.
    return array();
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    // Subversion has no meaningful concept of tags.
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $offset = (int)$request->getValue('offset');
    $limit = (int)$request->getValue('limit');
    $path = "/tags/";
    $tags = array();

    $subpath = $repository->getDetail('svn-subpath');
    if ($subpath && strncmp($subpath, $path, strlen($subpath))) {
      // If we have a subpath and the path isn't a child of it, it (almost
      // certainly) won't exist since we don't track commits which affect
      // it. (Even if it exists, return a consistent result.)
      return $tags;
    }

    $conn_r = $repository->establishConnection('r');

    $parent_path = DiffusionPathIDQuery::getParentPath($path);
    $path_query = new DiffusionPathIDQuery(
      array(
        $path,
        $parent_path,
      ));
    $path_map = $path_query->loadPathIDs();

    $path_id = $path_map[$path];
    $parent_path_id = $path_map[$parent_path];

    if (empty($path_id)) {
      return $tags;
    }

    $slice_clause = '';

    $index = queryfx_all(
      $conn_r,
      'SELECT pathID, max(svnCommit) maxCommit FROM %T WHERE
        repositoryID = %d AND parentID = %d
        %Q GROUP BY pathID',
      PhabricatorRepository::TABLE_FILESYSTEM,
      $repository->getID(),
      $path_id,
      $slice_clause);

    if (!$index) {
      if ($path == '/') {

      } else {

        // NOTE: The parent path ID is included so this query can take
        // advantage of the table's primary key; it is uniquely determined by
        // the pathID but if we don't do the lookup ourselves MySQL doesn't have
        // the information it needs to avoid a table scan.

        $reasons = queryfx_all(
          $conn_r,
          'SELECT * FROM %T WHERE repositoryID = %d
              AND parentID = %d
              AND pathID = %d
            %Q ORDER BY svnCommit DESC LIMIT 2',
          PhabricatorRepository::TABLE_FILESYSTEM,
          $repository->getID(),
          $parent_path_id,
          $path_id,
          $slice_clause);

        $reason = reset($reasons);

      }
      return $tags;
    }


    $sql = array();
    foreach ($index as $row) {
      $sql[] =
        '(pathID = '.(int)$row['pathID'].' AND '.
        'svnCommit = '.(int)$row['maxCommit'].')';
    }

    $browse = queryfx_all(
      $conn_r,
      'SELECT *, p.path pathName
        FROM %T f JOIN %T p ON f.pathID = p.id
        WHERE repositoryID = %d
          AND parentID = %d
          AND existed = 1
        AND (%Q)
        ORDER BY pathName',
      PhabricatorRepository::TABLE_FILESYSTEM,
      PhabricatorRepository::TABLE_PATH,
      $repository->getID(),
      $path_id,
      implode(' OR ', $sql));

    $loadable_commits = array();
    foreach ($browse as $key => $file) {
      // We need to strip out directories because we don't store last-modified
      // in the filesystem table.
      if ($file['fileType'] != DifferentialChangeType::FILE_DIRECTORY) {
        $loadable_commits[] = $file['svnCommit'];
        $browse[$key]['hasCommit'] = true;
      }
    }

    $commits = array();
    $commit_data = array();
    if ($loadable_commits) {
      // NOTE: Even though these are integers, use '%Ls' because MySQL doesn't
      // use the second part of the key otherwise!
      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'repositoryID = %d AND commitIdentifier IN (%Ls)',
        $repository->getID(),
        $loadable_commits);
      $commits = mpull($commits, null, 'getCommitIdentifier');
      if ($commits) {
        $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID in (%Ld)',
          mpull($commits, 'getID'));
        $commit_data = mpull($commit_data, null, 'getCommitID');
      } else {
        $commit_data = array();
      }
    }

    $path_normal = DiffusionPathIDQuery::normalizePath($path);

    $results = array();
    $count = 0;
    foreach ($browse as $file) {


      $full_path = $file['pathName'];
      $file_path = ltrim(substr($full_path, strlen($path_normal)), '/');
      $full_path = ltrim($full_path, '/');

      $result_path = new DiffusionRepositoryTag();
      $result_path->setName($file_path);
      $result_path->setCommitIdentifier($full_path);
      $result_path->setType("svn/tag");
      $result_path->setDescription($file_path);

      if ($count >= $offset) {
        $tags[] = $result_path;
      }

      $count++;

      if ($limit && ($count >= ($offset + $limit))) {
        break;
      }
    }


    return mpull($tags, 'toDictionary');
  }

}
