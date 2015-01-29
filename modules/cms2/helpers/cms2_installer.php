<?php defined("SYSPATH") or die("No direct script access.") ?><?php

class cms2_installer {

    private static function getversion() {
        return 1;
    }

    private static function setversion() {
        module::set_version("cms2", self::getversion());
    }

    static function install() {
        @mkdir(VARPATH . "modules/cms2");

        self::setversion();
    }

    static function uninstall() {
        dir::unlink(VARPATH . "modules/cms2");
    }

    static function upgrade($version) {
        if ($version < self::getversion()) {
            self::setversion();
        }
    }

    static function deactivate() {
        
    }

    static function activate() {
        
    }

    static function can_activate() {
        $messages = array();
        return $messages;
    }

}
