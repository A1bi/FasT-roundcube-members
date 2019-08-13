<?php

class fast_contacts extends rcube_contacts
{
  private $directory_url;
  private $directory_auth_token;
  private $labels;
  private $result;
  private $all_members;
  private $all_members_group_id = 'all_members';
  private $found_members;
  private $fast_id_prefix = 'fast_';
  private $db;
  private $rdc_contacts;

  /**
   * Object constructor
   */
  function __construct($labels)
  {
    $rcmail = rcmail::get_instance();
    $config = $rcmail->config;

    $this->directory_url = $config->get('fast_members_directory_url', 'http://localhost');
    $this->directory_auth_token = $config->get('fast_members_directory_auth_token', '');
    $this->labels = $labels;

    $this->db = $rcmail->db;
    $this->rdc_contacts = new rcube_contacts($this->db, $rcmail->user->ID);

    parent::__construct($this->db, $rcmail->user->ID);
  }

  /**
   * Setter for the current group
   * (empty, has to be re-implemented by extending class)
   */
  function set_group($gid)
  {
    $this->group_id = $gid;
    $this->rdc_contacts->set_group($gid);
  }

  /**
   * Reset all saved results and search parameters
   */
  function reset()
  {
    $this->result = null;
    $this->found_members = null;
    $this->rdc_contacts->reset();
  }

  /**
   * Set internal page size
   *
   * @param number $size Number of messages to display on one page
   */
  function set_pagesize($size)
  {
    parent::set_pagesize($size);
    $this->rdc_contacts->set_pagesize($size);
  }

  /**
   * Set internal list page
   *
   * @param number $page Page number to list
   */
  function set_page($page)
  {
    $this->list_page = $page;
    $this->rdc_contacts->set_page($page);
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
   * List the current set of contact records
   *
   * @param  array   List of cols to show, Null means all
   * @param  int     Only return this number of records, use negative values for tail
   *
   * @return array Indexed list of contact records, each a hash array
   */
  function list_records($cols = null, $subset = 0)
  {
    $start = ($this->list_page - ($subset < 0 ? 0 : 1)) * $this->page_size;
    $length = $subset != 0 ? abs($subset) : $this->page_size;
    $rdc_count = $this->rdc_contacts->count();
    if ($rdc_count->count > $start) {
      $this->result = $this->rdc_contacts->list_records($cols, $subset);
    } else {
      $this->result = $rdc_count;
    }

    $start = max(0, $start - $rdc_count->count);
    $length -= count($this->result->records);

    $members = $this->found_members;
    if (!isset($members)) {
      $members = $this->query_members();
    }

    if ($this->group_id && $this->group_id !== $this->all_members_group_id) {
      $ids = $this->get_all_contact_ids_for_group();

      $members = array_filter($members, function($member) use(&$ids) {
        return in_array($member['ID'], $ids);
      });
    }

    $this->result->count += count($members);

    $members = array_slice($members, $start, $length);
    foreach ($members as $member) {
      $this->result->add($member);
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
    $this->result = $this->rdc_contacts->search($fields, $value, $mode, $select, $nocount, $required);

    $query = [];

    $mapping = ['firstname' => 'first_name', 'surname' => 'last_name'];
    $all_fields = ['first_name', 'last_name', 'email'];

    if (is_array($fields) && !in_array('words', $fields)) {
      foreach ($fields as $field) {
        $key = $mapping[$field] ?: $field;
        $query[$key] = $value;
      }
    } else {
      foreach ($all_fields as $field) {
        $query[$field] = $value;
      }
    }

    $this->found_members = $this->query_members(null, $query);
    foreach ($this->found_members as $member) {
      if ($select) $this->result->add($member);
      $this->result->count++;
    }

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
    if ($this->id_belongs_to_fast($id)) {
      $id = $this->trim_fast_id($id);
      $this->result = new rcube_result_set(1);
      $this->result->add($this->query_members($id)[0]);

      return $assoc ? $this->result->first() : $this->result;

    } else {
      $record = parent::get_record($id, $assoc);
      $this->result = parent::get_result();
      return $record;
    }
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
    $result = parent::list_groups($search, $mode);

    $group = $this->get_group($this->all_members_group_id);
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
  function get_group($group_id)
  {
    if ($group_id == $this->all_members_group_id) {
      return [
        'ID' => $this->all_members_group_id,
        'name' => $this->labels['all_members']
      ];
    }

    return parent::get_group($group_id);
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
    $result = parent::get_record_groups($id);

    if ($this->id_belongs_to_fast($id)) {
      $result[$this->all_members_group_id] = $this->labels['all_members'];
    }

    return $result;
  }

  /**
   * Add the given contact records the a certain group
   *
   * @param string       Group identifier
   * @param array|string List of contact identifiers to be added
   *
   * @return int Number of contacts added
   */
  function add_to_group($group_id, $ids)
  {
    if ($group_id === $this->all_members_group_id) {
      $this->set_error(self::ERROR_VALIDATE, '');

      return false;
    }

    return parent::add_to_group($group_id, $ids);
  }

  /**
   * Remove the given contact records from a certain group
   *
   * @param string       $group_id Group identifier
   * @param array|string $ids      List of contact identifiers to be removed
   *
   * @return int Number of deleted group members
   */
  function remove_from_group($group_id, $ids)
  {
    if ($group_id === $this->all_members_group_id) {
      $this->set_error(self::ERROR_VALIDATE, '');

      return false;
    }

    if (!is_array($ids)) {
      $ids = explode(self::SEPARATOR, $ids);
    }

    $ids = $this->db->array2list($ids);

    $sql_result = $this->db->query(
      "DELETE FROM " . $this->get_group_members_table() .
      " WHERE `contactgroup_id` = ?".
      " AND `contact_id` IN ($ids)",
      $group_id
    );

    return $this->db->affected_rows($sql_result);
  }

  /**
   * Count number of available contacts in database
   *
   * @return rcube_result_set Result object
   */
  function count()
  {
    $count = $this->rdc_contacts->count();

    $this->query_members();

    if ($this->group_id && $this->group_id !== $this->all_members_group_id) {
      $ids = $this->get_all_contact_ids_for_group();

      foreach ($this->all_members as $member) {
        if (in_array($member['ID'], $ids)) {
          $count->count++;
        }
      }

    } else {
      $count->count += count($this->all_members);
    }

    return $count;
  }

  /**
   * query API endpoint to get member data
   */
  private function query_members($id = null, $query = null)
  {
    if (!$id && !$query && $this->all_members) {
      return $this->all_members;
    }

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

    $result = [];

    if ($okay) {
      $members = json_decode($content);

      foreach ($members as $member) {
        $result[] = [
          'ID' => 'fast_' . $member->id,
          'email' => $member->email,
          'firstname' => $member->first_name,
          'surname' => $member->last_name,
          'readonly' => true
        ];
      }

      if (!$id && !$query) {
        $this->all_members = $result;
      }

      return $result;

    } else {
      $this->set_error(self::ERROR_NO_CONNECTION, $error);

      return false;
    }
  }

  private function get_all_contact_ids_for_group() {
    $sql_result = $this->db->query(
      "SELECT contact_id FROM {$this->get_group_members_table()}
       WHERE contactgroup_id = ?
       AND contact_id LIKE '{$this->fast_id_prefix}%'",
      $this->group_id);

    $ids = [];
    while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
      $ids[] = $sql_arr['contact_id'];
    }

    return $ids;
  }

  private function id_belongs_to_fast($id) {
    return strpos($id, $this->fast_id_prefix) === 0;
  }

  private function trim_fast_id($id) {
    return str_replace($this->fast_id_prefix, '', $id);
  }

  private function get_group_members_table() {
    return $this->db->table_name('contactgroupmembers', true);
  }
}
