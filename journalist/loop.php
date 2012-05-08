<?php /* Display navigation to next/previous pages when applicable */ ?>
<?php if ( $wp_query->max_num_pages > 1 ) : ?>
	<div id="nav-above" class="navigation">
		<div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts', 'twentyten' ) ); ?></div>
		<div class="nav-next"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>', 'twentyten' ) ); ?></div>
	</div><!-- #nav-above -->
<?php endif; ?>

<?php /* If there are no posts to display, such as an empty archive page */ ?>
<?php if ( ! have_posts() ) : ?>
	<div id="post-0" class="post error404 not-found">
		<h1 class="entry-title"><?php _e( 'Not Found', 'twentyten' ); ?></h1>
		<div class="entry-content">
			<p><?php _e( 'Apologies, but no results were found for the requested archive. Perhaps searching will help find a related post.', 'twentyten' ); ?></p>
			<?php get_search_form(); ?>
		</div><!-- .entry-content -->
	</div><!-- #post-0 -->
<?php endif; ?>

<?php
	/* Start the Loop.
	 *
	 * In Twenty Ten we use the same loop in multiple contexts.
	 * It is broken into three main parts: when we're displaying
	 * posts that are in the gallery category, when we're displaying
	 * posts in the asides category, and finally all other posts.
	 *
	 * Additionally, we sometimes check for whether we are on an
	 * archive page, a search page, etc., allowing for small differences
	 * in the loop on each template without actually duplicating
	 * the rest of the loop that is shared.
	 *
	 * Without further ado, the loop:
	 */


	// Bouillon : On va afficher tous les posts de la même manière
	
?>
<?php while ( have_posts() ) : the_post(); ?>
<div class="post hentry<?php if (function_exists('sticky_class')) { sticky_class(); } ?>">
<h2 id="post-<?php the_ID(); ?>" class="entry-title"><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h2>
<p class="comments"><a href="<?php comments_link(); ?>"><?php comments_number('leave a comment','one comment','% comments'); ?></a></p>

<div class="main entry-content group">
	<?php the_content('Read the rest of this entry &raquo;'); ?>
</div>

<div class="meta group">
<div class="signature">
	<p><?php the_time('d/m/Y'); ?></p>
	<p>Partagé par <?php the_category(', '); ?></p>
<!-- Commentaire SMA   <p class="author vcard">Written by <span class="fn"><?php the_author() ?></span> <span class="edit"><?php edit_post_link('Edit'); ?></span></p>
    <p><abbr class="updated" title="<?php the_time('Y-m-d\TH:i:s')?>"><?php the_time('F jS, Y'); ?> <?php _e("at"); ?> <?php the_time('g:i a'); ?></abbr></p> -->
</div>	
<div class="tags">
	<?php
		# get_the_curation();
		$mykey_values = get_post_custom_values('bouillon-commentaire', get_the_ID());
		if (sizeof($mykey_values))
		{
			print "<span style='font-size:1.2em'>Commentaire(s) des veilleurs : </span>";
			print "<dl style='padding-left:20px; margin-bottom:0px;'>";
			foreach ( $mykey_values as $key => $value ) {
				$tab_comm = split("####", $value);
				print "<dt style='float:left'>".get_cat_name($tab_comm[0])."&nbsp;:&nbsp;</dt>";
				print "<dd style='margin-bottom:0px;'>".$tab_comm[1]."</dd>";
			}
			print "</dl>";
		}
		
		if ( the_tags('<p>Mots-clés : ', ', ', '</p>') );
	?>
</div> <!-- # tags -->
</div><!-- END .hentry -->
</div>
<?php endwhile; // End the loop. Whew. ?>

<?php /* Display navigation to next/previous pages when applicable */ ?>
<?php if (  $wp_query->max_num_pages > 1 ) : ?>
				<div id="nav-below" class="navigation">
					<div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts', 'twentyten' ) ); ?></div>
					<div class="nav-next"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>', 'twentyten' ) ); ?></div>
				</div><!-- #nav-below -->
<?php endif; ?>
