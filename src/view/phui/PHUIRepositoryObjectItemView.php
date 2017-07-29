<?php

/**
 * Created by IntelliJ IDEA.
 * User: jimmy
 * Date: 2017/3/16
 * Time: 下午8:53
 */
class PHUIRepositoryObjectItemView extends PHUIObjectItemView
{

  private $objectName;
  private $score;
  private $symbolLanguages;
  private $dateCreated;
  private $dateUpdated;
  private $descriptionView;
  private $contributer;
  private $cloneURL;
  private $owner;
  private $header;
  private $subhead;
  private $href;
  private $attributes = array();
  private $organizations = array();
  private $icons = array();
  private $barColor;
  private $object;
  private $effect;
  private $statusIcon;
  private $handleIcons = array();
  private $bylines = array();
  private $grippable;
  private $actions = array();
  private $headIcons = array();
  private $disabled;
  private $imageURI;
  private $imageIcon;
  private $titleText;
  private $badge;
  private $countdownNum;
  private $countdownNoun;
  private $launchButton;
  private $coverImage;

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  public function addHeadIcon($icon) {
    $this->headIcons[] = $icon;
    return $this;
  }

  public function setObjectName($name) {
    $this->objectName = $name;
    return $this;
  }

  public function setScore($score){
    $this->score = $score;
    return $this;
  }

  public function setSymbolLanguages($symbol){
    $this->symbolLanguages = $symbol;
    return $this;
  }

  public function setDateCreated($date){
    $this->dateCreated = $date;
    return $this;
  }

  public function setDescriptionView($view){
    $this->descriptionView = $view;
    return $this;
  }

  public function setContributer($contributer){
    $this->contributer = $contributer;
    return $this;
  }

  public function setCloneURL($url){
    $this->cloneURL = $url;
    return $this;
  }

  public function setOwner($owner){
    $this->owner = $owner;
  }

  public function setGrippable($grippable) {
    $this->grippable = $grippable;
    return $this;
  }

  public function getGrippable() {
    return $this->grippable;
  }

  public function setEffect($effect) {
    $this->effect = $effect;
    return $this;
  }

  public function getEffect() {
    return $this->effect;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setSubHead($subhead) {
    $this->subhead = $subhead;
    return $this;
  }

  public function setBadge(PHUIBadgeMiniView $badge) {
    $this->badge = $badge;
    return $this;
  }

  public function setCountdown($num, $noun) {
    $this->countdownNum = $num;
    $this->countdownNoun = $noun;
    return $this;
  }

  public function setTitleText($title_text) {
    $this->titleText = $title_text;
    return $this;
  }

  public function getTitleText() {
    return $this->titleText;
  }

  public function getHeader() {
    return $this->header;
  }

  public function addByline($byline) {
    $this->bylines[] = $byline;
    return $this;
  }

  public function setImageURI($image_uri) {
    $this->imageURI = $image_uri;
    return $this;
  }

  public function getImageURI() {
    return $this->imageURI;
  }

  public function setImageIcon($image_icon) {
    if (!$image_icon instanceof PHUIIconView) {
      $image_icon = id(new PHUIIconView())
        ->setIcon($image_icon);
    }
    $this->imageIcon = $image_icon;
    return $this;
  }

  public function getImageIcon() {
    return $this->imageIcon;
  }

  public function setCoverImage($image) {
    $this->coverImage = $image;
    return $this;
  }

  public function setEpoch($epoch) {
    $this->dateUpdated = $epoch;
    $date = phabricator_datetime($epoch, $this->getUser());
    $this->addIcon('none', $date);
    return $this;
  }

  public function addAction(PHUIListItemView $action) {
    if (count($this->actions) >= 3) {
      throw new Exception(pht('Limit 3 actions per item.'));
    }
    $this->actions[] = $action;
    return $this;
  }

  public function addIcon($icon, $label = null, $attributes = array()) {
    $this->icons[] = array(
      'icon'  => $icon,
      'label' => $label,
      'attributes' => $attributes,
    );
    return $this;
  }

  /**
   * This method has been deprecated, use @{method:setImageIcon} instead.
   *
   * @deprecated
   */
  public function setIcon($icon) {
    phlog(
      pht('Deprecated call to setIcon(), use setImageIcon() instead.'));

    return $this->setImageIcon($icon);
  }

  public function setStatusIcon($icon, $label = null) {
    $this->statusIcon = array(
      'icon' => $icon,
      'label' => $label,
    );
    return $this;
  }

  public function addHandleIcon(
    PhabricatorObjectHandle $handle,
    $label = null) {
    $this->handleIcons[] = array(
      'icon' => $handle,
      'label' => $label,
    );
    return $this;
  }

  public function setBarColor($bar_color) {
    $this->barColor = $bar_color;
    return $this;
  }

  public function getBarColor() {
    return $this->barColor;
  }

  public function addAttribute($attribute) {
    if (!empty($attribute)) {
      $this->attributes[] = $attribute;
    }
    return $this;
  }

  public function addOrganizations($attribute) {
    if (!empty($attribute)) {
      $this->organizations[] = $attribute;
    }
    return $this;
  }

  public function setLaunchButton(PHUIButtonView $button) {
    $button->setSize(PHUIButtonView::SMALL);
    $this->launchButton = $button;
    return $this;
  }

  protected function getTagName() {
    return 'li';
  }

  protected function getTagAttributes() {
    $item_classes = array();
    $item_classes[] = 'phui-oi';

    if ($this->icons) {
      $item_classes[] = 'phui-oi-with-icons';
    }

    if ($this->attributes) {
      $item_classes[] = 'phui-oi-with-attrs';
    }

    if ($this->handleIcons) {
      $item_classes[] = 'phui-oi-with-handle-icons';
    }

    if ($this->barColor) {
      $item_classes[] = 'phui-oi-bar-color-'.$this->barColor;
    } else {
      $item_classes[] = 'phui-oi-no-bar';
    }

    if ($this->actions) {
      $n = count($this->actions);
      $item_classes[] = 'phui-oi-with-actions';
      $item_classes[] = 'phui-oi-with-'.$n.'-actions';
    }

    if ($this->disabled) {
      $item_classes[] = 'phui-oi-disabled';
    }

    switch ($this->effect) {
      case 'highlighted':
        $item_classes[] = 'phui-oi-highlighted';
        break;
      case 'selected':
        $item_classes[] = 'phui-oi-selected';
        break;
      case 'visited':
        $item_classes[] = 'phui-oi-visited';
        break;
      case null:
        break;
      default:
        throw new Exception(pht('Invalid effect!'));
    }

    if ($this->getGrippable()) {
      $item_classes[] = 'phui-oi-grippable';
    }

    if ($this->getImageURI()) {
      $item_classes[] = 'phui-oi-with-image';
    }

    if ($this->getImageIcon()) {
      $item_classes[] = 'phui-oi-with-image-icon';
    }

    return array(
      'class' => $item_classes,
    );
  }

  protected function getTagContent() {
    $viewer = $this->getUser();

    $repo_name = $this->header;

    $header_link = phutil_tag(
      $this->href ? 'a' : 'div',
      array(
        'href' => $this->href,
        'class' => 'phui-oi-link',
        'title' => $this->href,
      ),
      $repo_name);

    $dateCreated = date('Y-m-d', $this->dateCreated);

    if ($this->dateUpdated){
      $dateUpdated = date('Y-m-d', $this->dateUpdated);
    }
    else{
      $dateUpdated = pht('None');
    }

    $symbol = $this->symbolLanguages;
    $attrs = null;
    if ($this->organizations) {
      $attrs = array();
      $spacer = phutil_tag(
        'span',
        array(
          'class' => 'phui-oi-attribute-spacer',
        ),
        "\xC2\xB7");
      $first = true;
      foreach ($this->organizations as $attribute) {
        $attrs[] = phutil_tag(
          'li',
          array(
            'class' => 'phui-oi-attribute',
          ),
          array(
            ($first ? null : $spacer),
            $attribute,
          ));
        $first = false;
      }

      $attrs = phutil_tag(
        'ul',
        array(
          'class' => 'phui-oi-attributes',
        ),
        array(
          $attrs,
        ));
    }

    $href_content = $header_link->getHTMLContent();
    $description = null;
    if($this->descriptionView){
      $description = $this->descriptionView->render();
    }

    $contributer = $this->contributer;
    $owner = $this->owner;
    if($owner && $owner != 'admin'){
      $user = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s',
        $owner);

      if($user){
        $owner = $user->getUserName();
      }
      else{
        $policies = id(new PhabricatorPolicyQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($owner))
          ->execute();

        if($policies){
          $policy = head($policies);
          $rule_data = $policy->getRules();
          $users = array();
          $admin_user = null;
          foreach ($rule_data as $rule){
            if($rule["action"] == "allow" && $rule["rule"] == "PhabricatorUsersPolicyRule"){
              foreach ($rule["value"] as $userPHID){
                $actor = id(new PhabricatorUser())->loadOneWhere(
                  'phid = %s',
                  $userPHID);

                if($actor->getUserName() == "admin"){
                  $admin_user = $actor->getUserName()."(".$actor->getRealName().")";
                }
                $users[] = $actor->getUserName()."(".$actor->getRealName().")";
              }
            }
          }//foreach ($rule_data as $rule)
          if(count($users) > 0){
            if(count($users) > 1){
              $users = array_diff($users, [$admin_user]);
            }
            $owner = join($users, ", ");
          }
          else{
            $owner = "多人";
          }
        }

      }
    }

    $addr_tag = $this->cloneURL;
    $addr_html = $addr_tag->getHTMLContent();
    $frame_html = "<div class=\"remarkup-table-wrap\"><table class=\"remarkup-table\" style=\"width:100%\">
<tbody>
<tr><td colspan='8'>$description</td></tr>
<tr>
<td>单位</td><td>$attrs</td>
<td>作者</td><td>$owner</td>
<td>开发语言</td><td>$symbol</td>
<td>更新时间</td><td>$dateUpdated</td>
</tr>
</tbody></table></div>";

    $table = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      array(
        new PhutilSafeHTML($frame_html),
      ));

    $header_name = array();

    if ($viewer) {
      $header_name[] = id(new PHUISpacesNamespaceContextView())
        ->setUser($viewer)
        ->setObject($this->object);
    }

    if ($this->objectName) {
      $header_name[] = array(
        phutil_tag(
          'span',
          array(
            'class' => 'phui-oi-objname',
          ),
          $this->objectName),
        ' ',
      );
    }

    $title_text = null;
    if ($this->titleText) {
      $title_text = $this->titleText;
    } else if ($this->href) {
      $title_text = $this->header;
    }

    $header_link = phutil_tag(
      $this->href ? 'a' : 'div',
      array(
        'href' => $this->href,
        'class' => 'phui-oi-link',
        'title' => $title_text,
      ),
      $this->header);

    $score_tag = null;
    if($this->score){
      $score_tag =
        phutil_tag(
          'span',
          array(
            'class' => 'phui-oi-objname',
          ),
          "  $this->score"
      );
    }

    // Wrap the header content in a <span> with the "slippery" sigil. This
    // prevents us from beginning a drag if you click the text (like "T123"),
    // but not if you click the white space after the header.
    $header = phutil_tag(
      'div',
      array(
        'class' => 'phui-oi-name',
      ),
      javelin_tag(
        'span',
        array(
          'sigil' => 'slippery',
        ),
        array(
          $this->headIcons,
          $header_name,
          $header_link,
         // $score_tag,
        )));

    $frame = phutil_tag(
      'div',
      array(
        'class' => 'phui-oi-frame',
      ),
      array(
        $header,
        $addr_tag,
        $table,
      ));

    $frame = phutil_tag(
      'div',
      array(
        'class' => 'phui-two-column-content',
      ),
      array(
        $frame
      ));

    return $frame;
  }

  private function renderStatusIcon($icon, $label) {
    Javelin::initBehavior('phabricator-tooltips');

    $icon = id(new PHUIIconView())
      ->setIcon($icon);

    $options = array(
      'class' => 'phui-oi-status-icon',
    );

    if (strlen($label)) {
      $options['sigil'] = 'has-tooltip';
      $options['meta']  = array('tip' => $label, 'size' => 300);
    }

    return javelin_tag('div', $options, $icon);
  }


  private function renderHandleIcon(PhabricatorObjectHandle $handle, $label) {
    Javelin::initBehavior('phabricator-tooltips');

    $options = array(
      'class' => 'phui-oi-handle-icon',
      'style' => 'background-image: url('.$handle->getImageURI().')',
    );

    if (strlen($label)) {
      $options['sigil'] = 'has-tooltip';
      $options['meta']  = array('tip' => $label, 'align' => 'E');
    }

    return javelin_tag('span', $options, '');
  }
}
