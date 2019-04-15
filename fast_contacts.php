<?php

class fast_contacts extends rcube_addressbook
{
  private $directory_url;
  private $directory_auth_token;
  private $labels;
  private $all_members_group_id = 1;

  /**
   * Object constructor
   */
  function __construct($labels)
  {
    $config = rcmail::get_instance()->config;
    $this->directory_url = $config->get('fast_members_directory_url', 'http://localhost');
    $this->directory_auth_token = $config->get('fast_members_directory_auth_token', '');
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
   *
   * @return array Indexed list of contact records, each a hash array
   */
  function list_records($cols = null, $subset = 0)
  {
    if (!$this->result) {
      $this->result = $this->query_members(null, $subset);
    }

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
    $query = [];

    $mapping = ['firstname' => 'first_name', 'surname' => 'last_name'];
    $all_fields = ['first_name', 'last_name', 'email'];

    if (is_array($fields)) {
      foreach ($fields as $field) {
        $key = $mapping[$field] ?: $field;
        $query[$key] = $value;
      }
    } else {
      foreach ($all_fields as $field) {
        $query[$field] = $value;
      }
    }

    $this->result = $this->query_members(null, null, $query);

    return $this->result;
  }

  /**
   * Count number of available contacts in database
   *
   * @return rcube_result_set Result object
   */
  function count()
  {
    if (!isset($this->cache['count'])) {
      $this->list_records();
    }
    $count = $this->cache['count'];

    return new rcube_result_set($count, ($this->list_page-1) * $this->page_size);
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
  function get_record($id, $assoc = false)
  {
    // return cached result
    if ($this->result && ($first = $this->result->first()) && $first['ID'] == $id) {
      return $assoc ? $first : $this->result;
    }

    $this->result = $this->query_members($id);

    return $assoc ? $this->result->first() : $this->result;
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

    $group = $this->get_group();
    $name = $group['name'];

    if (
      !$search
      || ($mode & rcube_addressbook::SEARCH_STRICT && $search === $name)
      || ($mode & rcube_addressbook::SEARCH_PREFIX && stripos($name, $search) === 0)
      || (!($mode & rcube_addressbook::SEARCH_STRICT)
         && !($mode & rcube_addressbook::SEARCH_PREFIX)
         && stripos($name, $search) !== false)) {

      $result[] = $group;
    }

    return $result;
  }

  /**
   * Get group properties such as name and email address(es)
   *
   * @param string $group_id Group identifier
   *
   * @return array Group properties as hash array
   */
  function get_group($group_id = null)
  {
    $name = $this->labels['all_members'];

    return [
      'ID' => $this->all_members_group_id,
      'name' => $name
    ];
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

  /**
   * query API endpoint to get member data
   */
  private function query_members($id = null, $subset = 0, $query = null)
  {
    $ch = curl_init();

    if ($query) {
      $params = '?' . http_build_query($query);
    }
    $url = $this->directory_url . ($id ? "/{$id}" : '') . $params;

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'X-Authorization: ' . $this->directory_auth_token
    ]);

    $content = curl_exec($ch);
    $okay = !curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200;
    curl_close($ch);

    $result = new rcube_result_set();

    if ($okay) {
      $members = json_decode($content);
      $result->count = count($members);

      if (!$id) {
        $members = $this->limit_result($members, $result, $subset);
      }

      foreach ($members as $member) {
        $result->add([
          'ID' => $member->id,
          'email' => $member->email,
          'firstname' => $member->first_name,
          'surname' => $member->last_name
        ]);
      }

      $this->cache['count'] = $result->count;

      return $result;

    } else {
      $this->set_error(self::ERROR_NO_CONNECTION, $error);

      return false;
    }
  }

  private function limit_result($members, $result, $subset) {
    $start = ($this->list_page - ($subset < 0 ? 0 : 1)) * $this->page_size;
    $length = $subset != 0 ? abs($subset) : $this->page_size;
    $result->first = $start;
    return array_slice($members, $start, $length);
  }
}
