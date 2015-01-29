<?php defined("SYSPATH") or die("No direct script access.") ?>
<style>
input[type="text"] {
  width: 95%;
}
</style>
<h1 style="display: none;"><?= t("MiniSlide Show") ?></h1>
<div id="g-mini-slideshow">
<embed src="<?= module::get_var("minislideshow", "slideshow_url") ?>" width="485" height="300"
 align="middle" pluginspage="http://www.macromedia.com/go/getflashplayer"
type="application/x-shockwave-flash" name="minislide" wmode="transparent"
 allowFullscreen="true" allowScriptAccess="always" quality="high"
flashvars="xmlUrl=<?= url::site("rss/feed/gallery/album/" . $item_id) ?><?=$slideshow_params ?>"></embed>
</div>
