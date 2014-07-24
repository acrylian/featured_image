<?php 
/**
 * Part of the featured_image plugin for Zenphoto
 *
 * @author Malte Müller (acrylian) <info@maltem.de>
 * @license: GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__)))."/zp-core/admin-globals.php");
admin_securityChecks(ZENPAGE_PAGES_RIGHTS | ZENPAGE_NEWS_RIGHTS, '');
require_once(SERVERPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER."/zenpage/zenpage-template-functions.php");
 
// current featured image
if (isset($_GET['featuredimage'])) {
	$type = sanitize( $_GET['type'] );
	$id = sanitize_numeric($_GET['featuredimage']);
  $obj = getItemByID($type, $id);
  $title = $obj->getTitle();
  $image = featuredImage::getFeaturedImage($obj);
  ?>
  <h4><?php echo gettext('Current featured image for '); echo '<em>'.html_encode($title).' ('.$type.')</em>'; ?></h4>
  <p class="notebox"><?php echo gettext('<strong>Note:</strong> Your theme might need changes to support this feature.'); ?></p>
  <div id="fi_singleimage">
  <?php
  if ($image) {
    featuredImage::printFeaturedImageDialog($image,'delete');
    ?>
    <script>
			$(document).ready(function(){
				$( "#fi_deleteimage" ).click(function() {
          $.ajax({
          	type: "GET",
  					url: "<?php echo WEBPATH.'/'.USER_PLUGIN_FOLDER; ?>/featured_image/ajax.php",
  					data: { 
  						deletefeaturedimage: "<?php echo $id; ?>", 
  						type: "<?php echo $type; ?>"
  					}
          }); 
          $( "#fi_adminthumb a" ).html( '' );
          $(".fi_opener_admin").html("<?php echo gettext('Set'); ?>");
          $( "#featuredimageselector" ).dialog( "close" );
        });
		  }); 
		</script>
    <?php
  } else {
    ?>
    <p><?php echo gettext('No featured image selected.'); ?></p>
    <?php
  }
  ?>
  </div>
  <?php
}

// Set featured image
if (isset($_GET['savefeaturedimage'])) {
	$type = sanitize( $_GET['type'] );
	$itemid = sanitize_numeric( $_GET['savefeaturedimage'] );
	$obj = getItemByID($type, $itemid);
	$message = featuredImage::saveFeaturedImageSelection($obj);
}

// Remove featured image
if (isset($_GET['deletefeaturedimage'])) {
	$type = sanitize( $_GET['type'] );
	$itemid = sanitize_numeric( $_GET['deletefeaturedimage'] );
	$obj = getItemByID($type, $itemid);
	featuredImage::deleteFeaturedImage($obj);
}

// single image display for preview and setting featured image
if(isset($_GET['getimg'])) {
	$type = sanitize( $_GET['type'] );
	$id = sanitize_numeric( $_GET['getimg'] );
	$itemid = sanitize_numeric( $_GET['itemid'] );
	if(!empty($id) && !empty($itemid)) {
		$image = getItemByID('images', $id);
    featuredImage::printFeaturedImageDialog($image,'save');
    ?>
    <script>
			$(document).ready(function(){
				$( "#fi_setimage" ).click(function() {
          $.ajax({
          	type: "GET",
  					url: "<?php echo WEBPATH.'/'.USER_PLUGIN_FOLDER; ?>/featured_image/ajax.php",
  					data: { 
  						savefeaturedimage: "<?php echo $itemid; ?>", 
  						imgid: "<?php echo $id;?>",
  						type: "<?php echo $type; ?>"
  					}
          });
          $( "#fi_adminthumb a" ).html( '<img src="<?php echo pathurlencode($image->getThumb()); ?>" alt="">' );
          $(".fi_opener_admin").html("<?php echo gettext('Change'); ?>");
          $( "#fi_singleimage" ).dialog( "close" );
        });
		  }); 
		</script>
    <?php
	} else {
    ?>
    Sorry…
    <?php
  }
} 

// image thumbs of an album
if(isset($_GET['getalb']) && isset($_GET['imgpage'])) {
	$type = sanitize( $_GET['type'] );
	$itemid = sanitize_numeric( $_GET['itemid'] );
	$id = sanitize_numeric( $_GET['getalb'] );
	$imgpage = sanitize_numeric( $_GET['imgpage'] );
	if(!empty($id) && !empty($imgpage) && !empty($itemid)) {
		$obj = getItemByID('albums', $id);
		$images = $obj->getImages($imgpage); 
		$numimages = $obj->getNumImages();
		$img_per_page = getOption('images_per_page');
		$totalpages = ceil($numimages / $img_per_page);
		?>
		<script>
			$(document).ready(function(){
				$(".fi_pagenav").html(''); //clear the html so the nav is always fresh!
				var total = <?php echo $totalpages; ?>;
				var current = <?php echo $imgpage; ?>;
				var activeclass = '';
				if(total > 1) {
					if(current !== 1) {
						$(".fi_pagenav").append('<li><a href="#" title="'+(current-1)+'">prev</a></li>');
					}
					for (i = 1; i <= total; i++) {
						if(current == i) {
							activeclass = ' class = "active"';
						} else {
							activeclass = '';
						}
						$(".fi_pagenav").append('<li><a href="#" title="'+i+'"'+activeclass+'>'+i+'</a></li>');
					}
					if(current < total) {
						$(".fi_pagenav").append('<li><a href="#" title="'+(current+1)+'">next</a></li>');
					}
				}
				$( ".fi_pagenav li a" ).click(function() {
					var imgpage = $(this).attr( "title" );
					//alert("click");
					$( "#featuredimageselector #fi_content" ).load( "<?php echo WEBPATH.'/'.USER_PLUGIN_FOLDER; ?>/featured_image/ajax.php?getalb=<?php echo $id; ?>&itemid=<?php echo $itemid; ?>&type=<?php echo $type; ?>&imgpage="+imgpage);
				});
				
        //single image dialog to preview uncropped and set feature image
        $( "#fi_singleimage" ).dialog({
          autoOpen: false,
          modal: true,
          resizable: true,
          closeOnEscape: true,
          width: 640,
          height: 480
        });
        
        //dialog to preview and set an image
        $( "#featuredimageselector #fi_content a.fi_thumb" ).click(function() {
          $( "#fi_singleimage" ).html( "" );
          $( "#fi_singleimage" ).dialog( "open" );
          var id = $('img',this).attr( "title" );
          $( "#fi_singleimage" ).load( "<?php echo WEBPATH.'/'.USER_PLUGIN_FOLDER; ?>/featured_image/ajax.php?getimg="+id+"&itemid=<?php echo $itemid; ?>&type=<?php echo $type; ?>");
        });
			});
		</script>
		<h4><?php echo $obj->getTitle(); ?> (<?php echo $obj->name; ?>) – <?php echo $obj->getNumImages(); ?></h4>
		<ul class="fi_pagenav"></ul><!-- filled with the nav -->
		<?php
		if($numimages == 0) {
			?>
			<p class="notebox"><?php echo gettext('This album does not contain any images.'); ?></p>
			<?php
		} else {
			foreach($images as $image) {
				$imgobj = newImage($obj, $image);
        if (isImagePhoto($imgobj)) {
          $extraclass = " imagetype";
        } else {
          $extraclass = " nonimagetype";
        }
        ?>
				<a href="#" title="<?php echo html_encode($imgobj->getTitle().' ('.$imgobj->filename.')'); ?>" class="fi_thumb<?php echo $extraclass;?>">
          <img src="<?php echo pathurlencode($imgobj->getThumb()); ?>" alt="<?php echo html_encode($imgobj->getTitle().' ('.$imgobj->filename.')'); ?>" title="<?php echo $imgobj->getID(); ?>">
				</a>
				<?php
			}
      ?>
      <ul class="fi_pagenav"></ul><!-- filled with the nav -->
      <div id="fi_singleimage"></div>
      <?php
		}
	} // if variables not empty
} 
?>