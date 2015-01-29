<?php

defined("SYSPATH") or die("No direct script access.");

class cms2_task_Core {

    static function available_tasks() {
        $tasks[] = Task_Definition::factory()
                ->callback("cms2_task::regenerate_identificators")
                ->name(t("Regenerate Identificators"))
                ->description(t("Image identificators will be newly generated"))
                ->severity(log::SUCCESS);
        return $tasks;
    }

    /**
     * Fix up item counts and delete any items that have no associated items.
     * @param Task_Model the task
     */
    static function regenerate_identificators($task) {

        $errors = array();
        try {
            $start = microtime(true);
            $last_item_id = $task->get("last_item_id", null);
            $current = 0;
            $total = 0;

            switch ($task->get("mode", "init")) {
                case "init":
                    $task->set("total", ORM::factory("item")->count_all());
                    $task->set("mode", "regenerate_identificators");
                    $task->set("completed", 0);
                    $task->set("last_item_id", 0);

                case "regenerate_identificators":
                    $completed = $task->get("completed");
                    $total = $task->get("total");
                    $last_item_id = $task->get("last_item_id");
                    $items = ORM::factory("item")->where("id", ">", $last_item_id)->find_all(1000);
                    $db = Database::instance();
                    while ($current < $total && microtime(true) - $start < 1 && $item = $items->current()) {
                        $last_item_id = $item->id;
                        $query = 'DELETE FROM {custom_fields_freetext_map} WHERE item_id = ' . $item->id . ' AND property_id = 2';
                        $db->query($query);
                        $query = 'DELETE FROM {custom_fields_freetext_multilang} WHERE item_id = ' . $item->id . ' AND property_id = 2';
                        $db->query($query);
                        $uuid = md5('eb9a4530-a349-4583-803f-1eb2636b06d9' . $item->id);
                        custom_fields::add_freetext($item, array(2 => $uuid));
                        custom_fields::update($item);
                        $completed++;
                        $items->next();
                    }
                    $task->percent_complete = $completed / $total * 100;
                    $task->set("completed", $completed);
                    $task->set("last_item_id", $last_item_id);
            }

            $task->status = t2("Examined %count item", "Examined %count items", $completed);

            if ($completed == $total) {
                $task->done = true;
                $task->state = "success";
                $task->percent_complete = 100;
            }
        } catch (Exception $e) {
            Kohana_Log::add("error", (string) $e);
            $task->done = true;
            $task->state = "error";
            $task->status = $e->getMessage();
            $errors[] = (string) $e;
        }
        if ($errors) {
            $task->log($errors);
        }
    }

}
