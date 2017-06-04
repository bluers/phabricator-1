<?php

final class PhabricatorPeopleAjaxSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Users');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPeopleApplication';
  }

  public function newQuery() {
    return id(new PhabricatorPeopleQuery())
      ->needPrimaryEmail(true)
      ->needProfileImage(true);
  }

  protected function buildCustomSearchFields() {
    $fields = array(
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Usernames'))
        ->setKey('usernames')
        ->setAliases(array('username'))
        ->setDescription(pht('Find users by exact username.')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('nameLike')
        ->setDescription(
          pht('Find users whose usernames contain a substring.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Administrators'))
        ->setKey('isAdmin')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Administrators'),
          pht('Hide Administrators'))
        ->setDescription(
          pht(
            'Pass true to find only administrators, or false to omit '.
            'administrators.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Disabled'))
        ->setKey('isDisabled')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Disabled Users'),
          pht('Hide Disabled Users'))
        ->setDescription(
          pht(
            'Pass true to find only disabled users, or false to omit '.
            'disabled users.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Bots'))
        ->setKey('isBot')
        ->setAliases(array('isSystemAgent'))
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Bots'),
          pht('Hide Bots'))
        ->setDescription(
          pht(
            'Pass true to find only bots, or false to omit bots.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Mailing Lists'))
        ->setKey('isMailingList')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Mailing Lists'),
          pht('Hide Mailing Lists'))
        ->setDescription(
          pht(
            'Pass true to find only mailing lists, or false to omit '.
            'mailing lists.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Needs Approval'))
        ->setKey('needsApproval')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Unapproved Users'),
          pht('Hide Unappproved Users'))
        ->setDescription(
          pht(
            'Pass true to find only users awaiting administrative approval, '.
            'or false to omit these users.')),
    );

    $viewer = $this->requireViewer();
    if ($viewer->getIsAdmin()) {
      $fields[] = id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Has MFA'))
        ->setKey('mfa')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Users With MFA'),
          pht('Hide Users With MFA'))
        ->setDescription(
          pht(
            'Pass true to find only users who are enrolled in MFA, or false '.
            'to omit these users.'));
    }

    $fields[] = id(new PhabricatorSearchDateField())
      ->setKey('createdStart')
      ->setLabel(pht('Joined After'))
      ->setDescription(
        pht('Find user accounts created after a given time.'));

    $fields[] = id(new PhabricatorSearchDateField())
      ->setKey('createdEnd')
      ->setLabel(pht('Joined Before'))
      ->setDescription(
        pht('Find user accounts created before a given time.'));

    return $fields;
  }

  protected function getDefaultFieldOrder() {
    return array(
      '...',
      'createdStart',
      'createdEnd',
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    $viewer = $this->requireViewer();

    // If the viewer can't browse the user directory, restrict the query to
    // just the user's own profile. This is a little bit silly, but serves to
    // restrict users from creating a dashboard panel which essentially just
    // contains a user directory anyway.
    $can_browse = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $this->getApplication(),
      PeopleBrowseUserDirectoryCapability::CAPABILITY);
    if (!$can_browse) {
      $query->withPHIDs(array($viewer->getPHID()));
    }

    if ($map['usernames']) {
      $query->withUsernames($map['usernames']);
    }

    if ($map['nameLike']) {
      $query->withNameLike($map['nameLike']);
    }

    if ($map['isAdmin'] !== null) {
      $query->withIsAdmin($map['isAdmin']);
    }

    if ($map['isDisabled'] !== null) {
      $query->withIsDisabled($map['isDisabled']);
    }

    if ($map['isMailingList'] !== null) {
      $query->withIsMailingList($map['isMailingList']);
    }

    if ($map['isBot'] !== null) {
      $query->withIsSystemAgent($map['isBot']);
    }

    if ($map['needsApproval'] !== null) {
      $query->withIsApproved(!$map['needsApproval']);
    }

    if (idx($map, 'mfa') !== null) {
      $viewer = $this->requireViewer();
      if (!$viewer->getIsAdmin()) {
        throw new PhabricatorSearchConstraintException(
          pht(
            'The "Has MFA" query constraint may only be used by '.
            'administrators, to prevent attackers from using it to target '.
            'weak accounts.'));
      }

      $query->withIsEnrolledInMultiFactor($map['mfa']);
    }

    if ($map['createdStart']) {
      $query->withDateCreatedAfter($map['createdStart']);
    }

    if ($map['createdEnd']) {
      $query->withDateCreatedBefore($map['createdEnd']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/people/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active'),
      'all' => pht('All'),
    );

    $viewer = $this->requireViewer();
    if ($viewer->getIsAdmin()) {
      $names['approval'] = pht('Approval Queue');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query
          ->setParameter('isDisabled', false);
      case 'approval':
        return $query
          ->setParameter('needsApproval', true)
          ->setParameter('isDisabled', false);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $users,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($users, 'PhabricatorUser');

    $request = $this->getRequest();
    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();

    $is_approval = ($query->getQueryKey() == 'approval');

    foreach ($users as $user) {
      $primary_email = $user->loadPrimaryEmail();
      if ($primary_email && $primary_email->getIsVerified()) {
        $email = pht('Verified');
      } else {
        $email = pht('Unverified');
      }

      $item = new PHUIObjectItemView();
      $item->setHeader($user->getFullName())
        ->setHref('/p/'.$user->getUsername().'/')
        ->addAttribute(phabricator_datetime($user->getDateCreated(), $viewer))
        ->addAttribute($email)
        ->setImageURI($user->getProfileImageURI());

      if ($is_approval && $primary_email) {
        $item->addAttribute($primary_email->getAddress());
      }

      if ($user->getIsDisabled()) {
        $item->addIcon('fa-ban', pht('Disabled'));
      }

      if (!$is_approval) {
        if (!$user->getIsApproved()) {
          $item->addIcon('fa-clock-o', pht('Needs Approval'));
        }
      }

      if ($user->getIsAdmin()) {
        $item->addIcon('fa-star', pht('Admin'));
      }

      if ($user->getIsSystemAgent()) {
        $item->addIcon('fa-desktop', pht('Bot'));
      }

      if ($user->getIsMailingList()) {
        $item->addIcon('fa-envelope-o', pht('Mailing List'));
      }

      if ($viewer->getIsAdmin()) {
        if ($user->getIsEnrolledInMultiFactor()) {
          $item->addIcon('fa-lock', pht('Has MFA'));
        }
      }

      if ($viewer->getIsAdmin()) {
        $user_id = $user->getID();
        if ($is_approval) {
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-ban')
              ->setName(pht('Disable'))
              ->setWorkflow(true)
              ->setHref($this->getApplicationURI('disapprove/'.$user_id.'/')));
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-thumbs-o-up')
              ->setName(pht('Approve'))
              ->setWorkflow(true)
              ->setHref($this->getApplicationURI('approve/'.$user_id.'/')));
        }
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No accounts found.'));

    return $result;
  }

  public function buildConduitResponse(
    ConduitAPIRequest $request,
    ConduitAPIMethod $method) {
    $viewer = $this->requireViewer();

    $query_key = $request->getValue('queryKey');
    if (!strlen($query_key)) {
      $saved_query = new PhabricatorSavedQuery();
    } else if ($this->isBuiltinQuery($query_key)) {
      $saved_query = $this->buildSavedQueryFromBuiltin($query_key);
    } else {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withQueryKeys(array($query_key))
        ->executeOne();
      if (!$saved_query) {
        throw new Exception(
          pht(
            'Query key "%s" does not correspond to a valid query.',
            $query_key));
      }
    }

    $constraints = $request->getValue('constraints', array());

    $fields = $this->getSearchFieldsForConduit();

    foreach ($fields as $key => $field) {
      if (!$field->getConduitParameterType()) {
        unset($fields[$key]);
      }
    }

    $valid_constraints = array("projectPHIDs" => true, "projectIDs" => true);
    foreach ($fields as $field) {
      foreach ($field->getValidConstraintKeys() as $key) {
        $valid_constraints[$key] = true;
      }
    }

    foreach ($constraints as $key => $constraint) {
      if (empty($valid_constraints[$key])) {
        throw new Exception(
          pht(
            'Constraint "%s" is not a valid constraint for this query.',
            $key));
      }
    }

    foreach ($fields as $field) {
      if (!$field->getValueExistsInConduitRequest($constraints)) {
        continue;
      }

      $value = $field->readValueFromConduitRequest(
        $constraints,
        $request->getIsStrictlyTyped());
      $saved_query->setParameter($field->getKey(), $value);
    }

    // NOTE: Currently, when running an ad-hoc query we never persist it into
    // a saved query. We might want to add an option to do this in the future
    // (for example, to enable a CLI-to-Web workflow where user can view more
    // details about results by following a link), but have no use cases for
    // it today. If we do identify a use case, we could save the query here.

    $query = $this->buildQueryFromSavedQuery($saved_query);

    $projectIds = null;
    $projectPHIDs = null;
    if(isset($constraints["projectPHIDs"]) && $constraints["projectPHIDs"] != null){
      $projectPHIDs = $constraints["projectPHIDs"];
    }
    else if(isset($constraints["projectIDs"]) && $constraints["projectIDs"] != null){
      $projectIds = $constraints["projectIDs"];
    }


    if($projectPHIDs || $projectIds){
      $projectQuery = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->needMembers(true)
        ->needWatchers(true)
        ->needImages(true)
        ->needSlugs(true);

      if($projectPHIDs)$projectQuery->withPHIDs($projectPHIDs);
      if($projectIds)$projectQuery->withIDs($projectIds);


      $policy_exception = null;
      try {
        $projects = $projectQuery->execute();
      } catch (PhabricatorPolicyException $ex) {
        $projects = null;
      }
      $memberPHIDs = array();
      if($projects){
        foreach ($projects as $project){
          foreach($project->getMemberPHIDs() as $memberPHID){
            $memberPHIDs[] = $memberPHID;
          }
        }
      }
    }

    if(isset($memberPHIDs) && count($memberPHIDs) > 0){
      $query->withPHIDs($memberPHIDs);
    }

    $pager = $this->newPagerForSavedQuery($saved_query);

    $attachments = $this->getConduitSearchAttachments();

    // TODO: Validate this better.
    $attachment_specs = $request->getValue('attachments', array());
    $attachments = array_select_keys(
      $attachments,
      array_keys($attachment_specs));

    foreach ($attachments as $key => $attachment) {
      $attachment->setViewer($viewer);
    }

    foreach ($attachments as $key => $attachment) {
      $attachment->willLoadAttachmentData($query, $attachment_specs[$key]);
    }

    $this->setQueryOrderForConduit($query, $request);
    $this->setPagerLimitForConduit($pager, $request);
    $this->setPagerOffsetsForConduit($pager, $request);

    $objects = $this->executeQuery($query, $pager);

    $data = array();
    if ($objects) {
      $field_extensions = $this->getConduitFieldExtensions();

      $extension_data = array();
      foreach ($field_extensions as $key => $extension) {
        $extension_data[$key] = $extension->loadExtensionConduitData($objects);
      }

      $attachment_data = array();
      foreach ($attachments as $key => $attachment) {
        $attachment_data[$key] = $attachment->loadAttachmentData(
          $objects,
          $attachment_specs[$key]);
      }

      $phids = array();
      foreach ($objects as $object) {
        $phids[] = $object->getPHID();
      }

      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->witHPHIDs($phids)
        ->execute();

      $handles_map = array();
      foreach($handles as $handle){
        $handles_map[$handle->getPHID()] = $handle;
      }

      foreach ($objects as $object) {
        $field_map = $this->getObjectWireFieldsForConduit(
          $object,
          $field_extensions,
          $extension_data);

        $attachment_map = array();
        foreach ($attachments as $key => $attachment) {
          $attachment_map[$key] = $attachment->getAttachmentForObject(
            $object,
            $attachment_data[$key],
            $attachment_specs[$key]);
        }

        // If this is empty, we still want to emit a JSON object, not a
        // JSON list.
        if (!$attachment_map) {
          $attachment_map = (object)$attachment_map;
        }

        $id = (int)$object->getID();
        $phid = $object->getPHID();
        $handle = $handles_map[$object->getPHID()];

        $data[] = array(
          'id' => $id,
          'type' => phid_get_type($phid),
          'phid' => $phid,
          'realName' => $object->getRealName(),
          'name' => $handle->getName(),
          'imageUrl' => $handle->getImageURI(),
          'uri' => $handle->getURI()
        );
      }
    }

    return array(
      'data' => $data,
      'maps' => $method->getQueryMaps($query),
      'query' => array(
        // This may be `null` if we have not saved the query.
        'queryKey' => $saved_query->getQueryKey(),
      ),
      'cursor' => array(
        'limit' => $pager->getPageSize(),
        'after' => $pager->getNextPageID(),
        'before' => $pager->getPrevPageID(),
        'order' => $request->getValue('order'),
      ),
    );
  }

  private function getEngineExtensions() {
    $extensions = PhabricatorSearchEngineExtension::getAllEnabledExtensions();

    foreach ($extensions as $key => $extension) {
      $extension
        ->setViewer($this->requireViewer())
        ->setSearchEngine($this);
    }

    $object = $this->newResultObject();
    foreach ($extensions as $key => $extension) {
      if (!$extension->supportsObject($object)) {
        unset($extensions[$key]);
      }
    }

    return $extensions;
  }

  private function getConduitFieldExtensions() {
    $extensions = $this->getEngineExtensions();
    $object = $this->newResultObject();

    foreach ($extensions as $key => $extension) {
      if (!$extension->getFieldSpecificationsForConduit($object)) {
        unset($extensions[$key]);
      }
    }

    return $extensions;
  }

  private function setQueryOrderForConduit($query, ConduitAPIRequest $request) {
    $order = $request->getValue('order');
    if ($order === null) {
      return;
    }

    if (is_scalar($order)) {
      $query->setOrder($order);
    } else {
      $query->setOrderVector($order);
    }
  }

  private function setPagerLimitForConduit($pager, ConduitAPIRequest $request) {
    $limit = $request->getValue('limit');

    // If there's no limit specified and the query uses a weird huge page
    // size, just leave it at the default gigantic page size. Otherwise,
    // make sure it's between 1 and 100, inclusive.

    if ($limit === null) {
      if ($pager->getPageSize() >= 0xFFFF) {
        return;
      } else {
        $limit = 100;
      }
    }

    if ($limit > 655360) {
      throw new Exception(
        pht(
          'Maximum page size for Conduit API method calls is 100, but '.
          'this call specified %s.',
          $limit));
    }

    if ($limit < 1) {
      throw new Exception(
        pht(
          'Minimum page size for API searches is 1, but this call '.
          'specified %s.',
          $limit));
    }

    $pager->setPageSize($limit);
  }

  private function setPagerOffsetsForConduit(
    $pager,
    ConduitAPIRequest $request) {
    $before_id = $request->getValue('before');
    if ($before_id !== null) {
      $pager->setBeforeID($before_id);
    }

    $after_id = $request->getValue('after');
    if ($after_id !== null) {
      $pager->setAfterID($after_id);
    }
  }
}
