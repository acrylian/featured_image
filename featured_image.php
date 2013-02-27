<?php
/**
 * This plugin provides a functionality to assign an album thumbnail from any top level albums to an Zenpage news article as a "featured image"
 * You can use this image as an intro image to your article e.g. in the news loop. The benefit compared to the embedding an image 		   			
 * within the text of your article statically is that you can controll the size of it using the object mode and Zenphoto's image processor
 * on your theme dynamically.
 *
 * Example usage on news.php either within the news loop or for single article display using the Zenphoto object model:
 * $thumb = getFeaturedImage($_zp_current_zenpage_news); 
 * if($thumb) {
 *  ?><img src="<?php echo pathurlencode($thumb->getThumb()); ?>" alt="<?php echo html_encode($thumb->getTitle()); ?>" /><?php
 * }
 * Requirement: Zenpage CMS plugin and a theme supporting it
 *
 * @author Malte Müller (acrylian)
 * @package plugins
 */
$plugin_is_filter = 5|ADMIN_PLUGIN|THEME_PLUGIN;
$plugin_description = gettext("Attach an album thumb to an news article.");
$plugin_author = "Malte Müller (acrylian)";
$plugin_version = '1.4.4';
$plugin_disable = (!getOption('zp_plugin_zenpage'))?gettext('The Zenpage CMS plugin is required for this and not enabled!'):false;
if(getOption('zp_plugin_zenpage')) {
		zp_register_filter('publish_article_utilities','getFeaturedImageSelector');
		zp_register_filter('new_article','saveFeaturedImageSelection');
		zp_register_filter('update_article','saveFeaturedImageSelection');
		zp_register_filter('remove_object','deleteFeaturedImage');
}

/*************************
* Backend functions 
**************************

/**
 * gets a dropdown selector with all toplevel albums whose album thumb you can assign to an article
 *
 * @param string $html
 * @param object $obj Object of the item to assign the thumb
 * @param string $prefix (not used)
 * @return string
 */
function getFeaturedImageSelector($html, $obj, $prefix='') {
	$html .= '<hr /><p>Select album for thumb attachement</p>';
	$html .= '<select size="1" style="width: 180px" id="featuredimage" name="featuredimage">';
	$selection = getFeaturedImageSelection($obj);
	$noneselected = ' selected="selected"';
	if(!$selection || $selection == 'none') { 
		$noneselected = '';
	}
	$html .= '<option'.$noneselected.' value="none">None</option>';
	$gallery = new Gallery();
	if($gallery->getNumAlbums() != 0) {
		echo "test";
		$albums = $gallery->getAlbums(0);
		foreach($albums as $album) {
			$albobj = new Album($gallery,$album);
			$selected = '';
			if($selection == $album) { 
				$selected = ' selected="selected"';
			}
			$html .= '<option'.$selected.' value="'.$album.'">'.pathurlencode($albobj->getTitle()).'</option>';
		}
	}
	$html .= '</select>';
	return $html;
}

/**
 * Gets the album selection of this article
 * @param object $obj Object of the item to assign the thumb
 */
function getFeaturedImageSelection($obj) {
	$aux = $obj->getID();
	$query = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = ".db_quote('featuredimage_article')." AND `aux` = ".db_quote($aux));
	if($query) {
		return $query['data'];
	} else {
		return false;
	}
}

/**
 * Saves the album selection of this article
 * @param string $message Message (returns empty or an error)
 * @param object $obj Object of the item to assign the thumb
 */
function saveFeaturedImageSelection($message,$obj) {
	if(isset($_POST['featuredimage'])) {
		$type = 'featuredimage_article';
		$data = sanitize($_POST['featuredimage']);
		$aux = $obj->getID();
		$query = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = ".db_quote($type)." AND `aux` = ".db_quote($aux));
		if($query) {
			$query = query("UPDATE ".prefix('plugin_storage')." SET `data` = ".db_quote($data)." WHERE `type` = ".db_quote($type)." AND `aux` = ".db_quote($aux));
		} else {
			$query = query("INSERT INTO ".prefix('plugin_storage')." (`type`,`data`,`aux`) VALUES (".db_quote($type).",".db_quote($data).",".db_quote($aux).")");
		}
		if (!$query) {
			$message .= '<p class="errorbox">'.sprintf(gettext('Query failure: %s'),db_error()).'</p>';
		}
	} 
	return $message;
}

/**
 * Removes albumthumb as entries from plugin_storage table if their object is removed.
 *
 * @param bool $allow we just return this since we have no need to abort the remove
 * @param object $obj the object being removed
 * @return bool
 */
function deleteFeaturedImage($allow,$obj) {
	if(get_class($obj) == 'ZenpageNews') {
		$check = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = 'featuredimage_article' AND `aux` = ".$obj->getID());
		if($check) {
			$query = query('DELETE FROM '.prefix('plugin_storage').' WHERE `aux` = '.$obj->getID().' AND `type` = "featuredimage_article"',false);
		}
	}
	return $allow;
}

/*************************
* Frontend theme function
**************************

/**
 * Returns the album thumb image object if one assigned
 *
 * @param ubj $obj news article object you want the featured image
 */
function getFeaturedImage($obj) {
	global $_zp_gallery;
	$album = getFeaturedImage($obj);
	if(!$album || $album == 'none') {
		return NULL;
	} else {
		$newalbum = new Album($_zp_gallery,$album);
		$albumthumbimg = $newalbum->getAlbumThumbImage();
		return $albumthumbimg;
	}
}



?>