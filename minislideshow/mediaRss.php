<?php
// +---------------------------------------------------------------------------+
// |  E2  XML Audio/Video Player/Minislideshow for Gallery2                    |
// +---------------------------------------------------------------------------+
// | mediaRss.php     [v.3.0.0]                                                |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2009 Wayne Patterson [suprsidr@flashyourweb.com]            |
// +---------------------------------------------------------------------------+
// |                                                                           |
// | This program is free software; you can redistribute it and/or             |
// | modify it under the terms of the GNU General Public License               |
// | as published by the Free Software Foundation; either version 2            |
// | of the License, or (at your option) any later version.                    |
// |                                                                           |
// | This program is distributed in the hope that it will be useful,           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
// | GNU General Public License for more details.                              |
// |                                                                           |
// | You should have received a copy of the GNU General Public License         |
// | along with this program; if not, write to the Free Software Foundation,   |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.           |
// |                                                                           |
// +---------------------------------------------------------------------------+
//

    /* Connect to gallery */
    function init (){
	    require_once( 'embed.php');
	    $ret = GalleryEmbed::init(array('fullInit' => true, 'embedUri' => '/gallery2/main.php', 'g2Uri' => '/gallery2/'));
	    if ($ret) {
	        print 'GalleryEmbed::init failed, here is the error message: ' . $ret->getAsHtml();
	        exit;
	    }
	    GalleryEmbed::done(); 
    }

    /**
     * Dynamic query for tag items
     * @param int $userId
     * @param string $keyword (optional) keyword for query; get from request if not specified
     * @return array object GalleryStatus a status code
     *               array of item ids
     * @static
     */
    function getTagChildIds($userId, $tagName=null) {
	global $gallery;
	$storage =& $gallery->getStorage();

	if (!isset($tagName)) {
	    $tagName = GalleryUtilities::getRequestVariables('tagName');
	}
	if (empty($tagName)) {
	    return array(GalleryCoreApi::error(ERROR_BAD_PARAMETER), null);
	}
	GalleryCoreApi::requireOnce('modules/tags/classes/TagsHelper.class');
	/* Force case-sensitive look-up to make the query use an column index */
	list ($ret, $tagId) = TagsHelper::getTagIdFromName($tagName, true);
	if ($ret) {
	    return array($ret, null);
	}

	if (empty($tagId)) {
	    return array(null, array());
	}

	list ($ret, $query, $data) = GalleryCoreApi::buildItemQuery('TagItemMap', 'itemId',
	    '[TagItemMap::tagId] = ?', null, null, null, 'core.view', false, $userId);
	if ($ret) {
	    return array($ret, null);
	}

	list ($ret, $searchResults) = $gallery->search($query, array_merge(array($tagId), $data));
	if ($ret) {
	    return array($ret, null);
	}
	$itemIds = array();
	while ($result = $searchResults->nextResult()) {
	    $itemIds[] = $result[0];
	}
	/* Start item display loop */
	if (!empty($itemIds)) { 
	    list ($ret, $childItems) = GalleryCoreApi::loadEntitiesById( $itemIds, 'GalleryItem' );
	    if ($ret) {
	        print "Error loading childItems:" . $ret->getAsHtml();
	    }
	    if (isset ($_REQUEST['shuffle']) && $_REQUEST['shuffle'] == 'true'){
	        shuffle($childItems);
	    }
	    foreach( $childItems as $childItem ) {
	        // we need to check the disabledFlag for each in dynamic mode
	        $disabled = getDisabledFlag($childItem->getId());
	        if(!$disabled){
	            if(!($childItem->entityType == "GalleryAlbumItem")){
	                $display .= getDisplay($childItem); 
	            }
	        }
	    }
	    return $display;
	}/* End item display loop */
    }

    /**
     * Dynamic query for keyword items
     * @param int $userId
     * @param string $keyword (optional) keyword for query; get from request if not specified
     * @return array GalleryStatus a status code
     *               array of item ids
     * @static
     */
    function getKeywordChildIds($userId, $keyword) {
	global $gallery;
	$storage =& $gallery->getStorage();

	if (!isset($keyword)) {
	    $keyword = GalleryUtilities::getRequestVariables('keyword');
	}
	if (empty($keyword)) {
        return array(GalleryCoreApi::error(ERROR_BAD_PARAMETER), null);
	}

	list ($ret, $module) = GalleryCoreApi::loadPlugin('module', 'keyalbum');
	if ($ret) {
	    return array($ret, null);
	}
	list ($ret, $params) = GalleryCoreApi::fetchAllPluginParameters('module', 'keyalbum');
	if ($ret) {
	    return array($ret, null);
	}

	$keywords = $where = array();
	foreach ($module->splitKeywords($keyword, $params['split']) as $k) {
	    $keywords[] = '%' . $k . '%';
	    $where[] = '[GalleryItem::keywords] LIKE ?';
	}

	list ($ret, $query, $data) = GalleryCoreApi::buildItemQuery(
		'GalleryItem', 'id', implode(' AND ', $where),
		$params['orderBy'], $params['orderDirection'], null, 'core.view', false, $userId);
	if ($ret) {
	    return array($ret, null);
	}
	if (empty($query)) {
	    return array(null, array());
	}

	list ($ret, $searchResults) = $gallery->search($query, array_merge($keywords, $data));
	if ($ret) {
	    return array($ret, null);
	}
	$itemIds = array();
	while ($result = $searchResults->nextResult()) {
	    $itemIds[] = $result[0];
	}
	/* Start item display loop */
	if (!empty($itemIds)) {
	    list ($ret, $childItems) = GalleryCoreApi::loadEntitiesById( $itemIds, 'GalleryItem' );
	    if ($ret) {
	        print "Error loading childItems:" . $ret->getAsHtml();
	    }
	    if (isset ($_REQUEST['shuffle']) && $_REQUEST['shuffle'] == 'true'){
	        shuffle($childItems);
	    }
	    foreach( $childItems as $childItem ) {
	        /* We need to check the disabledFlag for each in dynamic mode */
	        $disabled = getDisabledFlag($childItem->getId());
	        if(!$disabled){
	            if(!($childItem->entityType == "GalleryAlbumItem")){
	                $display .= getDisplay($childItem); 
	            }				
	        }
	    }
	    return $display;
	}/* End item display loop */
    }

    /**
     * Dynamic query for dynamic items
     * @param int $userId
     * @return array object GalleryStatus a status code
     *               array of item ids
     * @static
     */
    function getDynamicChildIds($userId, $param='date', $orderBy='creationTimestamp',
	    $orderDirection=ORDER_DESCENDING, $table='GalleryEntity', $id='id') {
	global $gallery;
	$storage =& $gallery->getStorage();
	list ($ret, $params) = GalleryCoreApi::fetchAllPluginParameters('module', 'dynamicalbum');
	if ($ret) {
	    return array($ret, null);
	}
	$size = $params['size.' . $param];
	$type = $params['type.' . $param];
	if (!$size) {
	    return array(GalleryCoreApi::error(ERROR_PERMISSION_DENIED), null);
	}

	list ($show, $albumId) = GalleryUtilities::getRequestVariables('show', 'albumId');
	if (!empty($show)) {
	    $type = $show;
	}
	switch ($type) {
	case 'data':
	    $class = 'GalleryDataItem';
	    break;
	case 'all':
	    $class = 'GalleryItem';
	    break;
	case 'album':
	    $class = 'GalleryAlbumItem';
	    break;
	default:
	    return array(GalleryCoreApi::error(ERROR_BAD_PARAMETER), null);
	}
	if (!isset($table)) {
	    $table = $class;
	}

	$query = '[' . $table . '::' . $id . '] IS NOT NULL';
	if (!empty($albumId)) {
	    list ($ret, $sequence) = GalleryCoreApi::fetchParentSequence($albumId);
	    if ($ret) {
	        return array($ret, null);
	    }
	    if (!empty($sequence)) {
	        $sequence = implode('/', $sequence) . '/' . (int)$albumId . '/%';
	        $query = '[GalleryItemAttributesMap::parentSequence] LIKE ?';
	        $table = 'GalleryItemAttributesMap';
	        $id = 'itemId';
	    } else {
	        $query = '[' . $table . '::' . $id . '] <> ' . (int)$albumId;
	    }
	}
	if ($table == $class) {
	    $class = null;
	}
	list ($ret, $query, $data) = GalleryCoreApi::buildItemQuery(
		$table, $id, $query, $orderBy, $orderDirection,
		$class, 'core.view', false, $userId);
	if ($ret) {
	    return array($ret, null);
	}
	if (empty($query)) {
	    return array(null, array());
	}
	if (!empty($sequence)) {
	    array_unshift($data, $sequence);
	}

	list ($ret, $searchResults) = $gallery->search($query, $data,
		array('limit' => array('count' => $size)));
	if ($ret) {
	    return array($ret, null);
	}
	$itemIds = array();
	while ($result = $searchResults->nextResult()) {
	    $itemIds[] = $result[0];
	}
	/* Start item display loop */
	if (!empty($itemIds)) {
	    list ($ret, $childItems) = GalleryCoreApi::loadEntitiesById( $itemIds, 'GalleryItem' );
	    if ($ret) {
	        print "Error loading childItems:" . $ret->getAsHtml();
	    }
	    $display = '';
	    if (isset ($_REQUEST['shuffle']) && $_REQUEST['shuffle'] == 'true'){
	        shuffle($childItems);
	    }
	    foreach( $childItems as $childItem ) {
	        /* We need to check the disabledFlag for each in dynamic mode */
	        $disabled = getDisabledFlag($childItem->getId());
	        if(!$disabled){
	            if(!($childItem->entityType == "GalleryAlbumItem")){
	                $display .= getDisplay($childItem); 
	            }
	        }
	    }
	    return $display;
	}/* End item display loop */
    }

    function getSearchItems($id, $mime) {
	global $gallery, $userId;
	$display = '';
	$searchResults = array();
	list($Albums) = getAlbumsRecursive($id);
	foreach ($Albums as $Album){
	/* We can check for disabledFlag for the whole album */
	    if (!(getDisabledFlag($Album->getId()))){
	        list ($ret, $childIds) = GalleryCoreApi::fetchChildItemIds( $Album, $offset = null, $count = null, $userId );
	        if ($ret) {
	           print "Error finding child item ids:" . $ret->getAsHtml();
	        }
	        if (!empty($childIds)) {
	            list ($ret, $childItems) = GalleryCoreApi::loadEntitiesById( $childIds, 'GalleryItem' );
	            if ($ret) {
	                print "Error loading childItems:" . $ret->getAsHtml();
	            } 
	            foreach( $childItems as $childItem ) {
	                if(getMime($childItem) == $mime){
	                    $searchResults[] = $childItem;
	                }
	            }
	        }
	    }
	}
	/* Start item display loop */
	if (!empty($searchResults)) {
	    if (isset ($_REQUEST['shuffle']) && $_REQUEST['shuffle'] == 'true'){
	        shuffle($childItems);
	    }
	    foreach( $searchResults as $childItem ) {
	        /* We need to check the disabledFlag for each in dynamic mode */
	        $disabled = getDisabledFlag($childItem->getId());
	        if(!$disabled){
	            if(!($childItem->entityType == "GalleryAlbumItem")){
	                $display .= getDisplay($childItem); 
	            }				
	        }
	    }
	    return $display;
	}/* End item display loop */
    }

    function getAlbumsRecursive($id){
	global $gallery;
	/* Get ids of all all albums where we can add new album items */
	list ($ret, $tree) = GalleryCoreApi::fetchAlbumTree($id, null, null);
	if ($ret) {
	    return array($ret->getAsHtml(), null);
	}

	/* Load all the album entities */
	list ($ret, $albums) = GalleryCoreApi::loadEntitiesById(GalleryUtilities::arrayKeysRecursive($tree), 'GalleryAlbumItem');;
	if ($ret) {
	    $albums = array();
	}
	/* Load and Add our starting point */
	list ($ret, $startAlbum) = GalleryCoreApi::loadEntitiesById($id, 'GalleryAlbumItem');;
	if ($ret) {
	    return array($ret->getAsHtml(), null);
	}
	array_push($albums, $startAlbum );
	return array($albums);
    }

    function getRoot(){
	global $gallery;
	if (GalleryUtilities::isCompatibleWithApi(array(7,5), GalleryCoreApi::getApiVersion())) {
	    list($ret, $defaultId) = GalleryCoreApi::getDefaultAlbumId();
	    if ($ret) {
	        return array($ret, null);
	    }else{
	    	return $defaultId;
	    }
	} else {
	    list ($ret, $defaultId) = GalleryCoreApi::getPluginParameter('module', 'core', 'id.rootAlbum');
	    if ($ret) {
	        return array($ret, null);
	    }else{
	    	return $defaultId;
	    }
	}
    }

    function getAlbumList ($id) {
	global $gallery;
	$display = "";
	list ($ret, $Albums) = GalleryCoreApi::fetchAlbumTree($id, null, null);
	if(!$ret){
		list ($ret, $Albums) = GalleryCoreApi::loadEntitiesById(GalleryUtilities::arrayKeysRecursive($Albums), 'GalleryAlbumItem');
		if($ret){
			$Albums = array();
		}
	}else{
		$Albums = array();
	}
	/* Load our starting album */
	list ($ret, $startAlbum) = GalleryCoreApi::loadEntitiesById($id, 'GalleryAlbumItem');
	if (!$ret) {
	    array_unshift($Albums, $startAlbum);
		$startAlbumParentId = $startAlbum->parentId;
		list ($ret, $startAlbumParent) = GalleryCoreApi::loadEntitiesById($startAlbumParentId, 'GalleryAlbumItem');
		if(!$ret){
			array_unshift($Albums, $startAlbumParent);
		}
	}

	foreach ($Albums as $album){
		if(hasPermission($album->getId()) && !getDisabledFlag($album->getId())&& $album->canContainChildren && ($album->getId() != getRoot())){
		    $display .= "        <album>\n";
		    $display .= "           <title>" . cdata($album->getTitle()) . "</title>\n";
		    $display .= "           <parentId>" . cdata($album->parentId) . "</parentId>\n";
		    $display .= "           <owner>" . cdata(getOwner($album->ownerId, 'GalleryUser')) . "</owner>\n";
		    $display .= "            <id>" . cdata($album->getId()) . "</id>\n";
		    $display .= "        </album>\n";			
		}
	}
	return $display;
    }
	
    function getItemsRecursive ($id) {
	global $gallery;
	$display = "";
	list($albums) = getAlbumsRecursive($id);
	    if (isset ($_REQUEST['shuffle']) && $_REQUEST['shuffle'] == 'true'){
	        shuffle($albums);
	    }
	foreach ($albums as $album){
	    $display .= getItems ($album->getId());
	}
	return $display;
    }

    function getItems ($id) {
	global $gallery, $userId;
	$display = "";
	list ($ret, $entity) = GalleryCoreApi::loadEntitiesById( $id, 'GalleryItem' );
	if ($ret) {
	    print "Error loading Entity:" . $ret->getAsHtml();
	}
	/* We can check for disabledFlag for the whole album */
	$disabled = getDisabledFlag($id);
	if(!$disabled){
	    list ($ret, $childIds) = GalleryCoreApi::fetchChildItemIds( $entity, $offset = null, $count = null, $userId );
	    if ($ret) {
	       print "Error finding child item ids:" . $ret->getAsHtml();
	    }
	    list ($ret, $childItems) = GalleryCoreApi::loadEntitiesById( $childIds, 'GalleryItem' );
	    if ($ret) {
	        print "Error loading childItems:" . $ret->getAsHtml();
	    }
	    if (!empty($childItems)) { 
	        if (isset ($_REQUEST['shuffle']) && $_REQUEST['shuffle'] == 'true'){
	            shuffle($childItems);
	        }
	        foreach( $childItems as $childItem ) {
	            if(!($childItem->entityType == "GalleryAlbumItem")){
	                $display .= getDisplay($childItem);			
	            }
	        }
	    }
	return $display;
	}
    }

    /* The big display function */
    function getDisplay($item){
	global $gallery, $count, $limit;
	$itemId = $item->getId();
	if (isset ($limit) && $count == $limit) {
	    return;
	}
	$display = '';
	if(hasPermission($itemId)) {			
	    list ($ret, $thumbnailList) = GalleryCoreApi::fetchThumbnailsByItemIds(array($itemId));
	    if ($ret) {
	        return array($ret->wrap(__FILE__, __LINE__), null);
	    }
	    $display .= "        <item>\n";
	    $display .= "            <title>" . cdata(getTitle($item)) . "</title>\n";
	    $display .= "            <id>" . $itemId . "</id>\n";
	    if (isset($_REQUEST['customLink']) && $_REQUEST['customLink'] == 'true' && pluginCheck('customfield')){
		    $display .= "            <link>" . getCustomLink($item) . "</link>\n";
	    } else {
		    $display .= "            <link>" . getLink($item) . "</link>\n";
		}
	    $display .= "            <view>" . getView($item) . "</view>\n";
	    $display .= "            <thumbUrl>" . getThumbUrl($item) . "</thumbUrl>\n";
	    $display .= "            <width>" . getWidth($item) . "</width>\n";
	    $display .= "            <height>" . getHeight($item) . "</height>\n";
	    $display .= "            <mime>" . getMime($item) . "</mime>\n";
	    if (!$ret && !empty($thumbnailList)) {
	        $display .= "            <description>". cdata("<a href=\"" . getLink($item) . "\"><img border=\"0\" src=\"" . getThumbUrl($item) . "\" width=\"" . getWidth($thumbnailList[$itemId]) . "\" height=\"" . getHeight($thumbnailList[$itemId]) . "\"/></a><br/>" . getTitle($item)) ."</description>\n";
	    }
	    $display .= "            <guid isPermaLink=\"false\">" . getLink($item) . "</guid>\n";
	    $display .= "            <pubDate>" . date('r', $item->getModificationTimestamp()) . "</pubDate>\n";
	    /* Start new media rss */
	    if (isset ($_REQUEST['g2_maxImageHeight']) && isset ($_REQUEST['g2_maxImageWidth']) ) {
			list ($ret, $bestFit) = getBestImageId($item->getId());
			if ($ret) {
				print 'Error getting best-fit image: ' . $ret->getAsHtml();
			}
			$display .= "            <media:content url=\"" . getView($bestFit) . "\" type=\"" . getMime($bestFit) . "\" width=\"" . getWidth($bestFit) . "\" height=\"" . getHeight($bestFit) . "\">\n";
		}else{
			$display .= "            <media:content url=\"" . getView($item) . "\" type=\"" . getMime($item) . "\" width=\"" . getWidth($item) . "\" height=\"" . getHeight($item) . "\">\n";
		}
	    $display .= "               <media:title type=\"plain\">" . cdata(getTitle($item)) . "</media:title>\n";
	    $display .= "               <media:thumbnail url=\"" . getThumbUrl($item) . "\" width=\"" . getWidth($thumbnailList[$itemId]) . "\" height=\"" . getHeight($thumbnailList[$itemId]) . "\" time=\"" . date('r', $item->getModificationTimestamp()) . "\"/>\n";
	    if (!$ret && !empty($thumbnailList)) {
	        $display .= "               <media:description type=\"html\">" . cdata("<a href=\"" . getLink($item) . "\"><img border=\"0\" src=\"" . getThumbUrl($item) . "\" width=\"" . getWidth($thumbnailList[$itemId]) . "\" height=\"" . getHeight($thumbnailList[$itemId]) . "\"/></a><br/>" . getTitle($item)) ."</media:description>\n";
	    }
	    $display .= "            </media:content>\n";
	    $display .= "        </item>\n";
		$count++;
	}
	return $display;
    }

    /* Check if current user has view permissions */
    function hasPermission($itemId){
	global $gallery, $userId;
	if (!$userId) {
	    $userId = $gallery->getActiveUserId();
	}
	if (!$userId) {
	    list($ret,$userId) = GalleryCoreApi::getAnonymousUserId();
	}
	list ($ret, $ok) = GalleryCoreApi::hasItemPermission($itemId, 'core.view', $userId);
	if ($ret || !$ok) {
	    return false;
	}else{
	    return true;
	}
    }

    /* Check to see if a module is available */
    function pluginCheck($plugin){  
	list ($ret, $modules) = GalleryCoreApi::fetchPluginStatus('module');
	if ($ret) {
	    print "checking plugin:". $plugin . " - " . $ret->getAsHtml();
	}
	if($modules[$plugin]['active'] && $modules[$plugin]['available']){
	    return true;
	}else{
	    return false;
	}
    }

    /* Check to see if the "Prevent this album from being displayed in the Image Block" is checked */
    function getDisabledFlag($itemId) {
	$isActive = pluginCheck('imageblock');
	if($isActive){
	    list ($ret, $searchResults) = GalleryCoreApi::getMapEntry('ImageBlockDisabledMap',
	        array('itemId'), array('itemId' => (int)$itemId));
	    if ($ret) {
	        return false;
	    }
	    $result = false;
	    if ($rec = $searchResults->nextResult()) {
	        $result = (bool)$rec[0];
	    }
	    return $result;
	}else{
	    //we want to return false if the imageBlock module is not active
	    return false;
	}
    }

    function getResizes($item) {
	$itemId = $item->getId();
	list ($ret, $resizes) = GalleryCoreApi::fetchResizesByItemIds(array($itemId));
	if ($ret) {
	    print "Error loading ResizesByItemIds:" . $ret->getAsHtml();
	}
	if (isset($resizes)) {
	    foreach ($resizes as $resized) {
	        $display .= getView($resized[0]);
	    }
	}else{
	    $display .= "none";
	}
	return $display;
    }

    function getPreferred($item) {
	$id = $item->getId();
	list ($ret, $preferred) = GalleryCoreApi::fetchPreferredsByItemIds(array($id));
	if ($ret) {
	    return array($ret, null);
	}
	if (!empty($preferred[$id]))  {
	    return $preferred[$id];
	}else{
	    return $item;
	}
    }

    function getOwner($id, $type) {
	list ($ret, $entity) = GalleryCoreApi::loadEntitiesById( $id, $type );
	if ($ret) {
	    print "Error loading ownerId:" . $ret->getAsHtml();
	}
	$owner = $entity->userName;
	return $owner;
    }

    function getTitle($item) {
	return stripTags($item->getTitle());
    }

    function stripTags($tostrip) {
	GalleryCoreApi::requireOnce('lib/smarty_plugins/modifier.markup.php');
	$stripped = smarty_modifier_markup($tostrip, 'strip');
	return $stripped;
    }

    function getMime($item) {
	if(!($item->entityType == "GalleryAlbumItem")){
	    return $item->getMimeType();
	} else {
	    return "Album";
	}
    }

    function getWidth($item) {
	if(is_object($item) && ($item->entityType == "GalleryAnimationItem" || $item->entityType == "GalleryPhotoItem" || $item->entityType == "ThumbnailImage" || $item->entityType == "GalleryMovieItem" || $item->entityType == "GalleryDerivativeImage")){
	    return $item->getWidth();
	} else {
	    return 480;
	}
    }

    function getHeight($item) {
	if(is_object($item) && ($item->entityType == "GalleryAnimationItem" || $item->entityType == "GalleryPhotoItem" || $item->entityType == "ThumbnailImage" || $item->entityType == "GalleryMovieItem" || $item->entityType == "GalleryDerivativeImage")){
	    return $item->getHeight();
	} else {
	    return 160;
	}
    }

    function getRating($itemId) {
	global $gallery;
	if(pluginCheck('rating')){
		GalleryCoreApi::requireOnce('modules/rating/classes/RatingHelper.class');
		list ($ret, $ratings) = RatingHelper::fetchRatings($itemId, '');
		if($ret){
		    return null;
		}else{
		    return $ratings[$itemId]['rating'];
		}
	}
    }

    function getThumbUrl($item) {
	global $gallery;
	$urlGenerator =& $gallery->getUrlGenerator();
	$itemId = $item->getId();
	list ($ret, $thumbnail) = GalleryCoreApi::fetchThumbnailsByItemIds(array($itemId));
	if (!$ret && !empty($thumbnail)) {
	    $thumbUrl = $urlGenerator->generateUrl(
		array('view' => 'core.DownloadItem', 'itemId' => $thumbnail[$itemId]->getId(),
		      'serialNumber' => $thumbnail[$itemId]->getSerialNumber()),
		array('forceFullUrl' => true, 'forceSessionId' => true, 'htmlEntities' => true));
	}else{
	    $thumbUrl = "";
	}
	return $thumbUrl;
    }

    function getLink($item){
	global $gallery;
	$urlGenerator =& $gallery->getUrlGenerator();
	$link = $urlGenerator->generateUrl(
	    array('view' => 'core.ShowItem', 'itemId' => $item->getId()),
		array('forceFullUrl' => true, 'forceSessionId' => true));
	return $link;
    }

    function getPreferredLink($item){
	global $gallery;
	$urlGenerator =& $gallery->getUrlGenerator();
	$link = $urlGenerator->generateUrl(
	    array('view' => 'core.ShowItem', 'itemId' => $item->getId(), 'imageViewsIndex' => 0),
		array('forceFullUrl' => true, 'forceSessionId' => true));
	return $link;
    }

    function getView($item){
	global $gallery;
	$urlGenerator =& $gallery->getUrlGenerator();
	$view = $urlGenerator->generateUrl(
	    array('view' => 'core.DownloadItem', 'itemId' => $item->getId(),
		    'serialNumber' => $item->getSerialNumber()),
		array('forceFullUrl' => true, 'forceSessionId' => true, 'htmlEntities' => true));
	return $view;
	}

    function cdata($text) {
	return '<![CDATA[' . $text . ']]>';
	}

    function getBestImageId($masterId) {
	global $gallery;

	if (isset ($_REQUEST['g2_maxImageHeight'])) {
		$maxImageHeight = $_REQUEST['g2_maxImageHeight'];
	}
	if (isset ($_REQUEST['g2_maxImageWidth'])) {
		$maxImageWidth = $_REQUEST['g2_maxImageWidth'];
	}

	$potentialImages = array();

	//how about the original?
	$ret = GalleryCoreApi::assertHasItemPermission($masterId,'core.viewSource');
	if (!$ret) {
		//is there a preferred derivative of the original?
		list ($ret, $preferred) = GalleryCoreApi::fetchPreferredsByItemIds(array($masterId));
		if ($ret) {
			return array ($ret,null);
		}
		if (!empty($preferred[$masterId])) {
			$potentialImages[] = $preferred[$masterId];
		} else {
		//if no preferred, use the original	original
			list ($ret, $item) = GalleryCoreApi::loadEntitiesById($masterId);
			if ($ret) {
				return array ($ret,null);
			}
			$potentialImages[] = $item;
		}
	}
	// If the user can see resized versions consider those too
	$ret = GalleryCoreApi::assertHasItemPermission($masterId,'core.viewResizes');
	if (!$ret) {
		list ($ret, $resizes) = GalleryCoreApi::fetchResizesByItemIds(array($masterId));
		if ($ret) {
			return array($ret,null);
		}
		if (!empty($resizes)) {
			foreach ($resizes[$masterId] as $resize) {
				$potentialImages[] = $resize;
			}
		}
	}
	//can always use the thumbnail
	list($ret,$thumbs) = GalleryCoreApi::fetchThumbnailsByItemIds( array($masterId) );
	if ($ret) {
		return array ($ret,null);
	}
	$potentialImages[] = $thumbs[$masterId];

	//true if maxDimensions are taller/narrower than image, in which case width is the constraint:
	$widthbound = ( !$maxImageHeight || $potentialImages[0]->height * $maxImageWidth < $potentialImages[0]->width * $maxImageHeight ) ? 1 : 0;

	usort($potentialImages, "byWidth");

	if ( $maxImageWidth &&  $widthbound ) {
		foreach ($potentialImages as $potentialImage) {
		if ($potentialImage->width >= $maxImageWidth) {
			return array ( null, $potentialImage);	//return the first one wider than $maxImageWidth
		}
			}
	}
	elseif ( $maxImageHeight ) {
		foreach ($potentialImages as $potentialImage) {
		if ($potentialImage->height >= $maxImageHeight) {
			return array ( null, $potentialImage);	//return the first one taller than $maxImageHeight
			}
		}
	}
	$bestImage=array_pop($potentialImages);
	return array( null,  $bestImage);			//none of them big enough - use the largest
    }

    function byWidth($a, $b) {
	if ($a->width == $b->width) return 0;
		return ($a->width < $b->width ) ? -1 : 1;
    }
	
    function getCustomLink($item) {
	global $gallery;
	$item_id = $item->getId();
	GalleryCoreApi::requireOnce('modules/customfield/classes/CustomFieldHelper.class');
	list ($ret, $tmp, $param, $isContainerSettings) =
		CustomFieldHelper::fetchFieldValues(array($item), null, 'photo');
	if ($ret) {
		return getLink($item);
	}
	return ($tmp[$item_id]['customLink'])?$tmp[$item_id]['customLink']:getLink($item);
    }
	
    function itemListDisplay(){
	global $gallery;
	$itemIds = explode('|', GalleryUtilities::getRequestVariables('itemIds'));
	list ($ret, $items) = GalleryCoreApi::loadEntitiesById( $itemIds, 'GalleryItem' );
	if ($ret) {
	    print "Error loading childItems:" . $ret->getAsHtml();
	}
	$display = '';
	foreach($items as $item){
	    /* We need to check the disabledFlag for each in dynamic mode */
	    $disabled = getDisabledFlag($item->getId());
	    if(!$disabled){
	        if(!($item->entityType == "GalleryAlbumItem")){
	            $display .= getDisplay($item); 
	        }
	    }
	}
	return $display;
    }

    function xml() {
	init();
	global $gallery, $userId, $count;
	$title = '';
	$recursive = '';
	if (!$userId) {
	    $userId = $gallery->getActiveUserId();
	}
	if (!$userId) {
	    list($ret,$userId) = GalleryCoreApi::getAnonymousUserId();
	}
	if (isset ($_REQUEST['mode'])) {
	    $mode = $_REQUEST['mode'];
	}else{
		$mode = '';
	}
	if (isset ($_REQUEST['g2_itemId'])) {
	    $g2_itemId = $_REQUEST['g2_itemId'];
	    list ($ret, $item) = GalleryCoreApi::loadEntitiesById($g2_itemId, 'GalleryAlbumItem');
	    if ($ret) {
	        print "Error loading initial item:" . $ret->getAsHtml();
	    }
	    $title = getTitle($item);
	}else{
	    $title = "Gallery2 MediaRss";
	}
	if (isset ($_REQUEST['g2_view'])) {
	    $g2_view = $_REQUEST['g2_view'];
	}
	if (isset ($_REQUEST['mime'])) {
	    $mime = $_REQUEST['mime'];
	}
	if (isset ($_REQUEST['recursive'])) {
	    $recursive = $_REQUEST['recursive'];
	}
	if(isset ($_REQUEST['limit'])){
		global $limit;
		$limit = $_REQUEST['limit'];
	}
	$xml = '';
	$count = 0;
	$urlGenerator =& $gallery->getUrlGenerator();
	$link = $urlGenerator->generateUrl(array(), array('forceFullUrl' => true));
	$vm = $gallery->getPhpVm();
	list ($ret, $language) = GalleryTranslator::getDefaultLanguageCode( );
	if ($ret) {
	    $language = "en-us";
	}
	if (!$vm->headers_sent()) {
	    $vm->header('Content-Type: application/rss+xml; charset=UTF-8');
	}
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";	
	$xml .= "<rss version=\"2.0\" xmlns:media=\"http://search.yahoo.com/mrss/\">\n";
	$xml .= "    <channel>\n";
	$xml .= "        <title>" . cdata($title) . "</title>\n";
	$xml .= "        <link>" . $link . "</link>\n";
	$xml .= "        <description>" . cdata($title) . "</description>\n";
	$xml .= "        <language>" .$language. "</language>\n";
	$xml .= "        <generator>FlashYourWeb MediaRSS Generator v3.0.0</generator>\n";
	$xml .= "        <lastBuildDate>" . date('r', $vm->time()) . "</lastBuildDate>\n";
	$xml .= "        <ttl>120</ttl>\n";
	if(isset($g2_itemId)){
		$xml .= getAlbumList ($g2_itemId);
	}else{
		$xml .= getAlbumList (getRoot());
	}
	switch ($mode) {
	    case 'dynamic':
	        switch ($g2_view) {
	            case 'dynamicalbum.UpdatesAlbum':
	                $xml .= getDynamicChildIds($userId);
	            break;
	            case 'dynamicalbum.PopularAlbum':
	                $xml .= getDynamicChildIds($userId, 'views', 'viewCount', ORDER_DESCENDING, 'GalleryItemAttributesMap', 'itemId');
	            break;
	            case 'dynamicalbum.RandomAlbum':
	                $xml .= getDynamicChildIds($userId, 'random', 'random', ORDER_ASCENDING, null, 'id');
	            break;
	            case 'keyalbum.KeywordAlbum':
	            	$xml .= getKeywordChildIds($userId, $g2_keyword=null);
	            break;
	            case 'tags.VirtualAlbum':
	                $xml .= getTagChildIds($userId, $g2_tagName=null);
	            break;
	            default:
	            $xml .= getDynamicChildIds($userId);
	        }
	    break;
	    case 'search':
	        $xml .= getSearchItems($g2_itemId, $mime);
	    break;
	    case 'simple':
	        $xml .= itemListDisplay();
	    break;
	    default:
	        if(isset($g2_itemId) && $recursive){
	            $xml .= getItemsRecursive ($g2_itemId);
	        }else if(isset($g2_itemId)){
	            $xml .= getItems($g2_itemId);
	        }else{
	            $xml .= getItems(getRoot());
	        }
	}
	$xml .= "    </channel>\n";
	$xml .= "</rss>";
	echo $xml;
    }
xml();
?>