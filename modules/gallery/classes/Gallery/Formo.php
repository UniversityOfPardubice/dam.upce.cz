<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2013 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class Gallery_Formo extends Formo_Core_Formo {
  /**
   * Add a CSRF element into every form that uses Access::verify_csrf() for validation.
   *
   * @see  Formo::__construct()
   */
  public function __construct(array $array=null) {
    parent::__construct($array);

    // If the driver is form (i.e. the parent form instead of a field within it), add the CSRF.
    // The "can_be_empty" argument means that, if the csrf field is empty in $_POST (i.e. illegal
    // access), go ahead and set the form entry as as null instead of passing along the pre-filled
    // value to validate (which would indicate legal access).
    if ($this->get("driver") == "form") {
      $this->add("csrf", "input|hidden", Access::csrf_token());
      $this->csrf
        ->set("can_be_empty", true)
        ->add_rule("Access::verify_csrf", array(":value"));
    }
  }

  /**
   * Override Formo::add_rule() to allow us to define error messages inline.  We use this
   * approach instead of using Kohana message files.
   * @todo: consider recasting this as a patch to send upstream to the Formo project.
   */
  public function add_rule($rule, $params=null, $error_message=null) {
    // If add_rule() is called using an array, separate it into its parts for clarity.
    if (is_array($rule)) {
      return $this->add_rule($rule[0], Arr::get($rule, 1), Arr::get($rule, 2, $params));
    }

    if (isset($error_message)) {
      $this->_error_messages[$rule] = $error_message;
    }

    return parent::add_rule($rule, $params);
  }
}