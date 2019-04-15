<?php

class fast_contacts extends rcube_addressbook
{
  private $labels;
  private $all_members_group_id = 1;
  private $current_group_id;

  /**
   * Object constructor
   */
  function __construct($labels)
  {
    $this->labels = $labels;
    $this->ready = true;
    $this->groups = true;
  }

  /**
   * Returns addressbook name (e.g. for addressbooks listing)
   */
  function get_name()
  {
    return $this->labels['name'];
  }

  /**
   * Save a search string for future listings
   *
   * @param mixed $filter Search params to use in listing method, obtained by get_search_set()
   */
  function set_search_set($filter)
  {
    $this->filter = $filter;
    $this->cache = null;
  }

  /**
   * Getter for saved search properties
   *
   * @return mixed Search properties used by this class
   */
  function get_search_set()
  {
    return $this->filter;
  }

  /**
   * Reset all saved results and search parameters
   */
  function reset()
  {
    $this->result = null;
    $this->filter = null;
    $this->cache  = null;
  }

  /**
   * List the current set of contact records
   *
   * @param  array   List of cols to show, Null means all
   * @param  int     Only return this number of records, use negative values for tail
   * @param  boolean True to skip the count query (select only)
   *
   * @return array Indexed list of contact records, each a hash array
   */
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

  /**
   * Search contacts
   *
   * @param mixed   $fields   The field name or array of field names to search in
   * @param mixed   $value    Search value (or array of values when $fields is array)
   * @param int     $mode     Search mode. Sum of rcube_addressbook::SEARCH_*
   * @param boolean $select   True if results are requested, False if count only
   * @param boolean $nocount  True to skip the count query (select only)
   * @param array   $required List of fields that cannot be empty
   *
   * @return object rcube_result_set Contact records and 'count' value
   */
  function search($fields, $value, $mode=0, $select=true, $nocount=false, $required=array())
  {
    $this->list_records();

    return $this->result;
  }

  /**
   * Count number of available contacts in database
   *
   * @return rcube_result_set Result object
   */
  function count()
  {
    return new rcube_result_set(1, ($this->list_page-1) * $this->page_size);
  }

  /**
   * Return the last result set
   *
   * @return mixed Result array or NULL if nothing selected yet
   */
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
