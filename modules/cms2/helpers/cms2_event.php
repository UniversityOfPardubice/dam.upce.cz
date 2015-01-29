<?php defined("SYSPATH") or die("No direct script access.") ?><?php

class cms2_event_Core {

    static function add_photos_form_completed($item, $form) {
        $input = new Form_Input('cf_2');
        $uuid = self::item_uuid($item);
        $input->value = $uuid;
        $form->custom_fields->inputs['cf_2'] = $input;

        Kohana_Log::add("error", "cms2_event_Core::add_photos_form_completed " . $item->id . " / " . $uuid);
    }

    static function item_created($item) {
        try {
            Kohana_Log::add("error", "cms2_event_Core::item_created running");
            self::_item_changed($item);
            $uuid = self::item_uuid($item);
            Kohana_Log::add("error", "cms2_event_Core::item_created " . $item->id . " / " . $uuid);
            custom_fields::add_freetext($item, array(2 => $uuid));
            custom_fields::update($item);
            Kohana_Log::add("error", "cms2_event_Core::item_created success");
        } catch (Exception $e) {
            Kohana_Log::add("error", "cms2_event_Core::item_created failed");
            Kohana_Log::add("error", $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    static function item_uuid($item) {
        return md5('eb9a4530-a349-4583-803f-1eb2636b06d9' . $item->id);
    }

    static function item_updated_data_file($item) {
        self::_item_changed($item);
    }

    static function item_deleted($item) {
        self::_item_changed($item);
    }

    static function item_edit_form_completed($item, $form) {
        self::_item_changed($item);
    }

    static function _item_changed($item) {
        if (is_dir(VARPATH . "modules/cms2/" . $item->id)) {
            self::rrmdir(VARPATH . "modules/cms2/" . $item->id);
        }
        if (is_dir(VARPATH . "modules/cms2/httpd/" . $item->id)) {
            self::rrmdir(VARPATH . "modules/cms2/httpd/" . $item->id);
        }
    }

    static function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir")
                        self::rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

}
