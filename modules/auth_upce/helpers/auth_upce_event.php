<?php defined("SYSPATH") or die("No direct script access.");

class auth_upce_event_Core {

  /**
  * remove the default login link and use our own
  */
  static function user_menu($menu, $theme) {
    $user = identity::active_user();
    if ($user->guest) {
      // disable the default login
      $menu->remove('user_menu_login');
      // add ours
      $menu->append(Menu::factory("link")
                    ->id("user_menu_openid")
                    ->css_id("g-openid-menu")
                    ->url(url::site("auth_upce/login"))
                    ->label(t("Login")));
    }
  }
}
