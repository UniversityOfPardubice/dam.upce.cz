<?php defined("SYSPATH") or die("No direct script access.") ?>

<? $useShadowBox = ( module::is_active("shadowbox") && module::get_var("theme_clean_canvas", "use_shadowbox") )?>
<? $useStandardFullSize = ( !$useShadowBox ) ?>

<? if (access::can("view_full", $theme->item())): ?>

<? if ( $useShadowBox ) : ?>

<!-- Use javascript to show the full size as an overlay on the current page -->
<script type="text/javascript">
Shadowbox.init({
    handleOversize: "resize",
    viewportPadding: 5
});
</script>

<? else : ?>
<!-- Use javascript to show the full size as an overlay on the current page -->
<script type="text/javascript">
  $(document).ready(function() {
    full_dims = [<?= $theme->item()->width ?>, <?= $theme->item()->height ?>];
    $(".g-fullsize-link").click(function() {
      $.gallery_show_full_size(<?= html::js_string($theme->item()->file_url()) ?>, full_dims[0], full_dims[1]);
      return false;
    });

    // After the image is rotated or replaced we have to reload the image dimensions
    // so that the full size view isn't distorted.
    $("#g-photo").bind("gallery.change", function() {
      $.ajax({
        url: "<?= url::site("items/dimensions/" . $theme->item()->id) ?>",
        dataType: "json",
        success: function(data, textStatus) {
          full_dims = data.full;
        }
      });
    });
  });
</script>
<? endif ?>
<? endif ?>

<div id="g-item">
  <?= $theme->photo_top() ?>

  <?= $theme->paginator() ?>

<? if ($useShadowBox) : ?>
  <div style="display:none">
  <? foreach ($item->parent()->children() as $i => $child): ?>
    <? if ( $child->id === $item->id ): ?>
  </div>

  <div id="g-photo">
      <?= $theme->resize_top($item) ?>
      <? if (access::can("view_full", $item)): ?>
    <a href="<?= $item->file_url() ?>" class="g-fullsize-link" rel="shadowbox[<?= $item->parent()->title ?>]" title="<?= html::purify($item->title) ?>">
      <? endif ?>
      <?= $item->resize_img(array("id" => "g-item-id-{$item->id}", "class" => "g-resize")) ?>
      <? if (access::can("view_full", $item)): ?>
    </a>
    <? endif ?>
    <?= $theme->resize_bottom($item) ?>
  </div>
  
  <div style="display:none">
	<? else :?>
      <? if (access::can("view_full", $child)): ?>
      <a href="<?= $child->file_url() ?>" class="g-fullsize-link" rel="shadowbox[<?= $item->parent()->title ?>]" title="<?= html::purify($child->title) ?>"> dummy </a>
      <? endif ?>
	<? endif ?>
  <? endforeach ?>	
  </div>
<? else : ?>

  <div id="g-photo">
    <?= $theme->resize_top($item) ?>
    <? if (access::can("view_full", $item)): ?>
    <a href="<?= $item->file_url() ?>" class="g-fullsize-link" title="<?= t("View full size")->for_html_attr() ?>">
      <? endif ?>
      <?= $item->resize_img(array("id" => "g-item-id-{$item->id}", "class" => "g-resize")) ?>
      <? if (access::can("view_full", $item)): ?>
    </a>
    <? endif ?>
    <?= $theme->resize_bottom($item) ?>
  </div> 
 <? endif ?>
  
  <div id="g-info">
    <h1><?= html::purify($item->title) ?></h1>
    <div><?= nl2br(html::purify($item->description)) ?></div>
  </div>

  <?= $theme->photo_bottom() ?>
</div>

