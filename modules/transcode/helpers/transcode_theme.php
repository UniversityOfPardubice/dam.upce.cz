<?php defined("SYSPATH") or die("No direct script access.") ?><?php

class transcode_theme_Core {
	
	static function resize_bottom($theme) {
		$item = $theme->item();
		$resolutions = ORM::factory("transcode_resolution")->where("item_id", "=", $item->id)->find_all();
		
		if ($resolutions->count() > 1) {
			$block = new Block();
			
			$block->css_id = "g-resolutions";
			$block->title = t("Alternative Resolutions");
			
			$view = new View("transcode_resolution_variants.html");
			$view->item = $item;
			$view->resolutions = $resolutions;
			
			$block->content = $view;
			return $block;
		} else {
		    return "";
		}
	}
	
}