<?php

class fast_contacts extends rcube_addressbook
{
  private $labels;
  private $all_members_group_id = 1;
  private $current_group_id;

  function __construct($labels)
  {
    $this->labels = $labels;
    $this->ready = true;
    $this->groups = true;
  }

  function get_name()
  {
    return $this->labels['name'];
  }

  function set_search_set($filter)
  {
    $this->filter = $filter;
    $this->cache = null;
  }

  function get_search_set()
  {
    return $this->filter;
  }

  function reset()
  {
    $this->result = null;
    $this->filter = null;
    $this->cache  = null;
  }

  function list_records($cols=null, $subset=0)
  {
    $this->result = new rcube_result_set();

    $this->result->add(
      array(
        'ID' => 1,
        'email' => 'foo@foo.de',
        'firstname' => 'John',
        'surname' => 'Doe'
      )
    );

    $this->result->count = 1;

    return $this->result;
  }

  function search($fields, $value, $mode=0, $select=true, $nocount=false, $required=array())
  {
    $this->list_records();

    return $this->result;
  }

  function count()
  {
    return new rcube_result_set(1, ($this->list_page-1) * $this->page_size);
  }

  function get_result()
  {
    return $this->result;
  }

  /**
   * Get a specific contact record
   *
   * @param mixed $id    Record identifier(s)
   * @param bool  $assoc Enables returning associative array
   *
   * @return rcube_result_set|array Result object with all record fields
   */
  function get_record($id, $assoc=false)
  {
    // return cached result
    if ($this->result && ($first = $this->result->first()) && $first['ID'] == $id) {
      return $assoc ? $first : $this->result;
    }

    $this->result = new rcube_result_set(1);
    $this->result->add(array(
      'ID' => 1,
      'email' => 'foo@foo.de',
      'firstname' => 'John',
      'surname' => 'Doe'
    ));

    return $this->result;
  }

  /**
   * Setter for the current group
   */
  function set_group($gid)
  {
    $this->current_group_id = $gid;
    $this->cache    = null;
  }

  /**
   * List all active contact groups of this source
   *
   * @param string $search Optional search string to match group name
   * @param int    $mode   Search mode. Sum of self::SEARCH_*
   *
   * @return array  Indexed list of contact groups, each a hash array
   */
  function list_groups($search = null, $mode = 0)
  {
    $result = array();

    $name = $this->labels['all_members'];

    if (
      !$search
      || ($mode & rcube_addressbook::SEARCH_STRICT && $search === $name)
      || ($mode & rcube_addressbook::SEARCH_PREFIX && stripos($name, $search) === 0)
      || (!($mode & rcube_addressbook::SEARCH_STRICT)
         && !($mode & rcube_addressbook::SEARCH_PREFIX)
         && stripos($name, $search) !== false)) {

      $result[] = array(
        'ID' => $this->all_members_group_id,
        'name' => $name
      );
    }

    return $result;
  }

  /**
   * Get group assignments of a specific contact record
   *
   * @param mixed $id Record identifier
   *
   * @return array List of assigned groups as ID=>Name pairs
   */
  function get_record_groups($id)
  {
    $result = array();
    $result[$this->all_members_group_id] = $this->labels['all_members'];
    return $result;
  }
}
