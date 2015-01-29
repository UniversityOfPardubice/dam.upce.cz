<?php

defined("SYSPATH") or die("No direct script access.");

class auth_upce_Controller extends Controller {

    /**
     * default action for the openid controller (i.e; gallery/openid/)
     */
    public function login() {
        require_once('/var/www/simplesaml/lib/_autoload.php');
        $as = new SimpleSAML_Auth_Simple('upce.cz-sp');
        $as->requireAuth();
        $attributes = $as->getAttributes();

        $username = $attributes['uid'][0];

        Kohana_Log::add("information", "[UPCE Auth] Authorising as " . $username . "\n");
        $user = user::lookup_by_name($username);
        if (is_null($user)) {
            $user = self::create_new_user($username);
        }
        if (is_null($user)) {
            Kohana_Log::add("information", "[UPCE Auth] For some reason, user information is now null!\n");
        } else {
            self::update_user($user, $attributes);
            Kohana_Log::add("information", "[UPCE Auth] logging in the user: {$user->name}\n");
            auth::login($user);
            $continue_url = Session::instance()->get("continue_url");
            url::redirect($continue_url ? $continue_url : item::root()->abs_url());
        }
        throw new Kohana_404_Exception();
    }

    private function update_user($user, $attributes) {
        if (isset($attributes['https://idp.upce.cz/celeJmenoSTituly'][0])) {
            $user->full_name = $attributes['https://idp.upce.cz/celeJmenoSTituly'][0];
        }
        if (isset($attributes['mail'][0])) {
            $user->email = $attributes['mail'][0];
        }

        echo "<pre>";
        $allGroups = identity::groups();
        $autoGroups = array();
        foreach ($allGroups as $group) {
            if (preg_match('/^auto_/', $group->name)) {
                $autoGroups[] = preg_replace('/^auto_/', '', $group->name);
            }
        }
        foreach ($attributes['groups'] as $group) {
            if (!in_array($group, $autoGroups)) {
                Kohana_Log::add("information", "[UPCE Auth] Creating group: " . 'auto_' . $group . "\n");
                identity::create_group('auto_' . $group);
            }
        }

        foreach ($attributes['groups'] as $userGroup) {
            $group = identity::lookup_group_by_name('auto_' . $userGroup);
            Kohana_Log::add("information", "[UPCE Auth] Adding " . $user->name . " to group " . $group->name . "\n");
            identity::add_user_to_group($user, $group);
        }
        
        foreach ($autoGroups as $autoGroup) {
            if (!in_array($autoGroup, $attributes['groups'])) {
                $group = identity::lookup_group_by_name('auto_' . $autoGroup);
                Kohana_Log::add("information", "[UPCE Auth] Removing " . $user->name . " from group " . $group->name . "\n");
                identity::remove_user_from_group($user, $group);
            }
        }
    }

    private function create_new_user($username) {
        $password = md5(uniqid(mt_rand(), true));
        $new_user = identity::create_user($username, $username, $password, $username . '@upce.cz' /* e-mail */);
        $new_user->admin = false;
        $new_user->guest = false;
        $new_user->save();
        return $new_user;
    }

}

?>
