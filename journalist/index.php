<?php get_header(); ?>

<div id="content">
<?php get_template_part('loop', 'index'); ?>


<div class="navigation group">
	<div class="alignleft"><?php next_posts_link('&laquo; Older Entries') ?></div>
	<div class="alignright"><?php previous_posts_link('Newer Entries &raquo;') ?></div>
</div>

</div> 

<?php get_sidebar(); ?>

<?php get_footer(); ?>
