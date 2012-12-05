<?php
/**
 * This plugin provides a functionality to assign an album thumbnail to an Zenpage news article via the backend.
 * You can use this image as an intro image to your article e.g. in the news loop. The benefit to the embedding of an image 		   			
 * within the text of your article is that you can controll the size of it using the object mode and Zenphoto's image processor
 * on your theme dynamically.
 *
 * Example usage on news.php either within the news loop or for single article display:
 * $thumb = getAttachedArticleAlbumThumb($_zp_current_zenpage_news); 
 * if($thumb) {
 *  ?><img src="<?php echo pathurlencode($thumb->getThumb()); ?>" alt="<?php echo html_encode($thumb->getTitle()); ?>" /><?php
 * }
 *
 * @author Malte Müller (acrylian)
 * @package plugins
 */
$plugin_is_filter = 5|ADMIN_PLUGIN|THEME_PLUGIN;
$plugin_description = gettext("Attach an album thumb to an news article.");
$plugin_author = "Malte Müller (acrylian)";
$plugin_version = '1.4.1.2';
if(getOption('zp_plugin_zenpage')) {
		zp_register_filter('publish_article_utilities','getAttachAlbumThumbSelector');
		zp_register_filter('new_article','saveAttachAlbumThumbSelection');
		zp_register_filter('update_article','saveAttachAlbumThumbSelection');
		zp_register_filter('remove_object','deleteAttachedArticleAlbumThumb');
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
function getAttachAlbumThumbSelector($html, $obj, $prefix='') {
	$html .= '<hr /><p>Select album for thumb attachement</p>';
	$html .= '<select size="1" style="width: 180px" id="selection_albumthumbarticle" name="selection_albumthumbarticle">';
	$selection = getAlbumThumbSelection($obj);
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
function getAlbumThumbSelection($obj) {
	$aux = $obj->getID();
	$query = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = ".db_quote('selection_albumthumbarticle')." AND `aux` = ".db_quote($aux));
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
function saveAttachAlbumThumbSelection($message,$obj) {
	if(isset($_POST['selection_albumthumbarticle'])) {
		$type = 'selection_albumthumbarticle';
		$data = sanitize($_POST['selection_albumthumbarticle']);
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
function deleteAttachedArticleAlbumThumb($allow,$obj) {
	if(get_class($obj) == 'ZenpageNews') {
		$check = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = 'selection_albumthumbarticle' AND `aux` = ".$obj->getID());
		if($check) {
			$query = query('DELETE FROM '.prefix('plugin_storage').' WHERE `aux` = '.$obj->getID().' AND `type` = "selection_albumthumbarticle"',false);
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
 * @param ubj $obj news article object
 */
function getAttachedArticleAlbumThumb($obj) {
	global $_zp_gallery;
	$album = getAlbumThumbSelection($obj);
	if(!$album || $album == 'none') {
		return NULL;
	} else {
		$newalbum = new Album($_zp_gallery,$album);
		$albumthumbimg = $newalbum->getAlbumThumbImage();
		return $albumthumbimg;
	}
}



?>