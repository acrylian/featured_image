<?php
/**
 * This plugin provides a functionality to assign an image from your albums to an Zenpage news article, category or page as a "featured image".
 * You can use this image for example for headers of your single article/page/category pages or within the news article list as a thumbnail. 
 * The benefit compared to the embedding an image within the text content statically is that you can control the size of it 
 * via your theme's layout dynamically as with gallery items.
 *
 * To use it you need to modify your theme used if it has no built in support already. 
 * 
 * Usage examples:
 * 
 * a) Object model 
 * $featuredimage = getFeaturedImage(<object of the Zenpage item>);
 * if($featuredimage) { // if an feature image exists use the object model
 *  ?>
 *  <img src="<?php echo pathurlencode($featuredimage->getThumb()); ?>" alt="<?php echo html_encode($featuredimage->getTitle()); ?>">
 *  <?php
 * }
 * 
 * b) Theme function for pages.php and news.php for the current article, category or page
 * <?php printSizedFeaturedImage(NULL,'My featured image',500); ?>
 *  
 * Requirement: Zenpage CMS plugin and a theme supporting it
 *
 * @author Malte Müller (acrylian) <info@maltem.de>
 * @copyright 2014 Malte Müller
 * @license: GPL v3 or later
 * @package plugins
 * @subpackage misc
 */
$plugin_is_filter = 5|ADMIN_PLUGIN|THEME_PLUGIN;
$plugin_description = gettext("Attach an image to a Zenpage news article, category or page.");
$plugin_author = "Malte Müller (acrylian)";
$plugin_version = '1.1.1';
$plugin_disable = (!getOption('zp_plugin_zenpage'))?gettext('The Zenpage CMS plugin is required for this and not enabled!'):false;
if(getOption('zp_plugin_zenpage')) {
  zp_register_filter('publish_article_utilities','featuredImage::getFeaturedImageSelector');
  zp_register_filter('publish_page_utilities','featuredImage::getFeaturedImageSelector');
  zp_register_filter('publish_category_utilities','featuredImage::getFeaturedImageSelector');
	zp_register_filter('remove_object','featuredImage::deleteFeaturedImage');
	zp_register_filter('admin_head', 'featuredImage::featuredImageCSS');
}

/*************************
* Backend functions 
**************************/

class featuredImage {

	static function featuredImageCSS() {
		?>
		<link rel="stylesheet" type="text/css" href="<?php echo WEBPATH.'/'.USER_PLUGIN_FOLDER; ?>/featured_image/style.css" />
		<?php
	}

	/**
	 * gets a dropdown selector with all toplevel albums whose album thumb you can assign to an article
	 *
	 * @param string $html
	 * @param object $obj Zenpage article, category or page object to assign the thumb
	 * @param string $prefix (not used)
	 * @return string
	 */
	static function getFeaturedImageSelector($html, $obj, $prefix='') {
		global $_zp_gallery;
		$selection = featuredImage::getFeaturedImageSelection($obj);
		if($selection) {
			$buttontext = gettext('Change');
		} else {
			$buttontext = gettext('Set');
		}
		$itemtype = featuredImage::getFeaturedImageType($obj);
		switch($itemtype) {
			case 'featuredimage_article':
				$type = 'news'; // this is needed for the getItemByID() function using in Ajax calls
				$itemid = $obj->getID();
				break;
			case 'featuredimage_category':
				$type = 'news_categories';
				$itemid = $obj->getID();
				break;
			case 'featuredimage_page': 
				$type = 'pages';
				$itemid = $obj->getID();
				break;
		}
		// admin utility box button to call the dialog
		$html .= '<hr /><h3>Feature image</h3>';
		$fimage = featuredImage::getFeaturedImage($obj);
		$imghtml = '';
		if($fimage) {
			$imghtml = '<img src="'.html_encode($fimage->getThumb()).'" alt="">';
		}
		$html .= '<div id="fi_adminthumb"><a href="#" class="fi_opener">'.$imghtml.'</a></div>';
		$html .= '<p class="buttons fi_adminbuttons"><button class="fi_opener fi_opener_admin">'.$buttontext.'</button>';
    $html .= '</p>';
		// this is part of the dialog window
		$albumshtml = '<ul>';
		$albumshtml .= '<li><a href="#" class="active fi_image">'.gettext('Current featured image').'</a></li>';
		$albums = $_zp_gallery->getAlbums();
		foreach($albums as $album) {
			$albobj = newAlbum($album);
			if($albobj->getNumImages() == 0) {
				$albumshtml .= '<li>'.$albobj->getTitle().' <small>('.$albobj->getNumImages().')</small>';
			} else {
				$albumshtml .= '<li><a href="#" title="'.$albobj->getID().'" class="fi_album">'.$albobj->getTitle().'</a> <small>('.$albobj->getNumImages().')</small>';
			}
			$albumshtml .= featuredImage::getFeaturedImageSubalbums($albobj);
			$albumshtml .= '</li>';
		}
		$albumshtml .= '</ul>';
		$html .= '
			<div id="featuredimageselector">
				<div id="fi_albumlist">'.$albumshtml.'</div>
				<div id="fi_content"><h4>'.gettext('Current featured image').'</h4></div>
			</div>';
		$html .= '<script>
		$(document).ready(function(){
			var winwidth = $(window).width();
			var winheight = $(window).height();
			if(winwidth-150 != 0) {
				winwidth = winwidth-150;
			}
			if(winheight-150 != 0) {
				winheight = winheight-150;
			}
			$( "#featuredimageselector" ).dialog({
				autoOpen: false,
				modal: true,
				resizable: true,
				title: "Select a featured image",
				closeOnEscape: true,
				width: winwidth,
				height: winheight
			});
 
			$( ".fi_opener" ).click(function() {
				$( "#featuredimageselector" ).dialog( "open" );
				$( "#featuredimageselector #fi_content" ).html("");
				$( "#featuredimageselector #fi_content" ).load( "'.WEBPATH.'/'.USER_PLUGIN_FOLDER.'/featured_image/ajax.php?featuredimage='.$itemid.'&type='.$type.'");
			
				//current featured image
				$( "#featuredimageselector #fi_albumlist li a.fi_image" ).click(function() {
					var linktitle = $(this).attr( "title" );
					$("#fi_albumlist li a").removeClass( "active" );
					$(this).addClass( "active" );
					$( "#featuredimageselector #fi_content" ).load( "'.WEBPATH.'/'.USER_PLUGIN_FOLDER.'/featured_image/ajax.php?featuredimage='.$itemid.'&type='.$type.'");
				});
			
				// thumbs of an album
				$( "#featuredimageselector #fi_albumlist li a.fi_album" ).click(function() {
					var id = $(this).attr( "title" );
					$("#fi_albumlist li a").removeClass( "active" );
					$(this).addClass( "active" );
					$( "#featuredimageselector #fi_content" ).load( "'.WEBPATH.'/'.USER_PLUGIN_FOLDER.'/featured_image/ajax.php?getalb="+id+"&itemid='.$itemid.'&type='.$type.'&imgpage=1" );
				});       
				return false;
			});
     
		});
		</script>';
		return $html;
	}
	
	/**
	 * Gets the subalbums of the album object $albobj recursively
	 * @param object $albobj album object
	 */
	private static function getFeaturedImageSubalbums(&$albobj) {
		if($albobj->getNumAlbums() != 0) {
			$html = '<ul>';
			$albums = $albobj->getAlbums();
			foreach($albums as $album) {
				$obj = newAlbum($album);
				if($obj->getNumImages() == 0) {
					$html .= '<li>'.$obj->getTitle().' <small>('.$obj->getNumImages().')</small>';
				} else {
					$html .= '<li><a href="#" title="'.$obj->getID().'" class="fi_album">'.$obj->getTitle().'</a> <small>('.$obj->getNumImages().')</small>';
				}
				$html .= featuredImage::getFeaturedImageSubalbums($obj);
				$html .= '</li>';
			}
			$html .= '</ul>';
			return $html;
		}
	}

	/**
	 * Gets the album selection of this article
	 * @param object $obj Zenpage article, category or page object to assign the thumb
	 */
	static function getFeaturedImageSelection($obj) {
		$type = featuredImage::getFeaturedImageType($obj);
		if($type) {
			$itemid = $obj->getID();
			$query = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = ".db_quote($type)." AND `aux` = ".db_quote($itemid));
			if($query) {
				return $query['data'];
			} else {
				return false;
			}
		}
		return false;
	}

	/**
	 * Saves the album selection of this item
	 * @param object $obj Zenpage article, category or page object to assign the thumb
	 */
	static function saveFeaturedImageSelection($obj) {
		$type = featuredImage::getFeaturedImageType($obj);
		$message = '';
		if(isset($_GET['imgid']) && $type) {
			$data = sanitize_numeric($_GET['imgid']);
			$itemid = $obj->getID();
			$query = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = ".db_quote($type)." AND `aux` = ".db_quote($itemid));
			if($query) {
				$query = query("UPDATE ".prefix('plugin_storage')." SET `data` = ".db_quote($data)." WHERE `type` = ".db_quote($type)." AND `aux` = ".db_quote($itemid ));
			} else {
				$query = query("INSERT INTO ".prefix('plugin_storage')." (`type`,`data`,`aux`) VALUES (".db_quote($type).",".db_quote($data).",".db_quote($itemid ).")");
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
	 * @param object $obj Zenpage article, category or page object being removed
	 * @return bool
	 */
	static function deleteFeaturedImage($obj) {
		$type = featuredImage::getFeaturedImageType($obj);
		if($type) {
			$itemid = $obj->getID();
			$check = query_single_row("SELECT `data` FROM ".prefix('plugin_storage')." WHERE `type` = ".db_quote($type)." AND `aux` = ".db_quote($itemid));
			if($check) {
				$query = query('DELETE FROM '.prefix('plugin_storage').' WHERE `aux` = '.db_quote($itemid).' AND `type` = '.db_quote($type).'',false);
			}
		}
	}
	
	/**
	 * Checks the class of $obj and returns the Zenpage item type "featuredimage_article/category/page"
	 * Returns false if no Zenpage object is passed
	 *
	 * @param object $obj Zenpage article, category or page object
	 * @return mixed
	 */
	static function getFeaturedImageType($obj) {
		if(is_object($obj)) {
			switch(get_class($obj)) {
				case 'ZenpageNews':
					return 'featuredimage_article'; 
				case 'ZenpageCategory':
					return 'featuredimage_category';
				case 'ZenpagePage':
					return 'featuredimage_page';
				default: 
					return false;
			}
		} 
	}
	/**
	 * Function to be used in the dialog box for displaying the single image 
	 * for setting the featured image and the current featured image 
	 *
	 * @param object $obj the object of the image
	 * @param string $buttontype "save" for save button and "delete" for delete button
	 */
	static function printFeaturedImageDialog($imgobj,$buttontype) {
		$album = $imgobj->getAlbum();
		if (isImagePhoto($imgobj)) {
			$url = pathurlencode($imgobj->getSizedImage(400));
		} else {
			$url = pathurlencode($imgobj->getThumb());
		}
		?>
		<div id="fi_imagepreview">
			<img src="<?php echo $url; ?>" alt="<?php echo html_encode($imgobj->getTitle()); ?>" class="fi_thumb">
		</div>
		<div id="fi_imageinfo">
			<h5><?php echo html_encode($imgobj->getTitle().' ('.$imgobj->filename.')'); ?></h5>
			<p><?php echo gettext('Album: '); ?><?php echo html_encode($album->getTitle().' ('.$album->name.')'); ?></p>
			<?php 
				switch($buttontype) {
					case 'save':
						$buttontext = gettext('Set feature image');
						$buttonid = 'fi_setimage';
						break;
					case 'delete':
						$buttontext = gettext('Remove feature image');
						$buttonid = 'fi_deleteimage';
						break;
				}
				?>
			<p class="buttons"><button id="<?php echo $buttonid; ?>"><?php echo $buttontext; ?></button></p>
    	<br class="clearfix">
		</div>
		<?php
	}

	/**
	 * Returns the album thumb image object if one assigned
	 *
	 * @param obj/strong $obj Zenpage article, category or page object you want the featured image of
	 */
	static function getFeaturedImage($obj) {
		$imgobj = '';
		$imagedata = featuredImage::getFeaturedImageSelection($obj);
		if ($imagedata) {
			if (is_numeric($imagedata)) {
				$imgobj = getItemByID('images', $imagedata);
			} else {
				//fallback for existing entries using the old albumthumb only way 
				//where the album name was stored
				$album = newAlbum($imagedata);
				$imgobj = $album->getAlbumThumbImage();
			}
			if (is_object($imgobj)) {
				return $imgobj;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

}

    /* ***********************
     * Frontend theme function
     * ***********************/

      /**
     * Returns the image object if an featured image is assigned, otherwise false
     *
     * @param obj/string $obj Object of the Zenpage article, category you want the featured image to get
     * return mixed
     */
    function getFeaturedImage($obj = NULL) {
      global $_zp_current_zenpage_news, $_zp_current_category, $_zp_current_zenpage_page, $_zp_gallery_page;
      if (is_null($obj)) {
        switch ($_zp_gallery_page) {
          case 'news.php':
            if (is_object($_zp_current_category) && !is_object($_zp_current_zenpage_news)) {
              $obj = $_zp_current_category;
            } else if (is_object($_zp_current_zenpage_news)) {
              $obj = $_zp_current_zenpage_news;
            } 
            break;
          case 'pages.php':
            if (is_object($_zp_current_zenpage_page)) {
              $obj = $_zp_current_zenpage_page;
            }
            break;
        }
      }
      $img = featuredImage::getFeaturedImage($obj);
      return $img;
    }

 
    /**
     *  Print a custom sized version of this image based on the parameters. Multimedia itemas don't use these.
     *
     * @param obj/string $obj Object of the Zenpage page, article or category you want the featured image to get. If you set it to NULL on news.php or pages.php it will try to get the image for the current page, news article or category
     * @param int $size size
     * @param int $width width
     * @param int $height height
     * @param int $cropw crop width
     * @param int $croph crop height
     * @param int $cropx crop x axis
     * @param int $cropy crop y axis
     * @param bool $thumbStandin set to true to treat as thumbnail
     * @param bool $effects set to desired image effect (e.g. 'gray' to force gray scale)
     * @return string
     */
    function printSizedFeaturedImage($obj = NULL,$alt, $size, $width = NULL, $height = NULL, $cropw = NULL, $croph = NULL, $cropx = NULL, $cropy = NULL, $class = NULL, $id = NULL, $thumbStandin = false, $effects = NULL) {
      $image = getFeaturedImage($obj);
      if ($image) {
        if(isImagePhoto($image)) {
          if(empty($alt)) $alt = $image->getTitle();
          if(!empty($class)) $class = ' class="'.$class.'"';
          if(!empty($id)) $id = ' id="'.$id.'"';
          $url = $image->getCustomImage($size, $width, $height, $cropw, $croph, $cropx, $cropy, $thumbStandin, $effects);
          ?>
          <img src="<?php echo pathurlencode($url); ?>"<?php echo $class.$id; ?> alt="<?php echo html_encode($alt); ?>">
          <?php
        } else {
          echo $image->getBody();
        }
      }
    }
?>
