<?php

/**
 * FasT Members Directory
 *
 * Makes all members with their e-mail addresses accessable from the address book
 *
 * @version 0.1
 * @author Albrecht Oster
 */

require_once 'fast_contacts.php';

class fast_members_directory extends rcube_plugin
{
  public $task = 'addressbook|mail';
  private $book_id = 'fast_members';
  private $book_name;

  function init()
  {
    $this->load_config();

    $rcmail = rcmail::get_instance();
    $config = $rcmail->config;

    if (!in_array($rcmail->user->get_username(), $config->get('fast_members_accounts', array()))) {
      return;
    }

    $this->add_texts('i18n/');
    $this->book_name = $this->gettext('book_name');

    $this->add_hook('addressbooks_list', array($this, 'addressbooks_list'));
    $this->add_hook('addressbook_get', array($this, 'addressbook_get'));

    $sources = (array) $config->get('autocomplete_addressbooks', array('sql'));
    $sources[] = $this->book_id;
    $config->set('autocomplete_addressbooks', $sources);
  }

  function addressbooks_list($params)
  {
    $params['sources'][] = array(
      'id' => $this->book_id,
      'name' => $this->book_name,
      'readonly' => true,
      'groups' => true
    );

    return $params;
  }

  function addressbook_get($params)
  {
    if ($params['id'] === $this->book_id) {
      $labels = [
        'name' => $this->book_name,
        'all_members' => $this->gettext('all_members')
      ];

      $params['instance'] = new fast_contacts($labels);
    }

    return $params;
  }
}
