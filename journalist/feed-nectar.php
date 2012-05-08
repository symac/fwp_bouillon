<?php
/**
 * RSS2 Feed Template for displaying RSS2 Posts feed.
 *
 * @package WordPress
 */

header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>

<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	<?php do_action('rss2_ns'); ?>
>

<channel>
	<title><?php bloginfo_rss('name'); wp_title_rss(); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss('url') ?></link>
	<description><?php bloginfo_rss("description") ?></description>
	<lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></lastBuildDate>
	<language><?php echo get_option('rss_language'); ?></language>
	<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
	<?php do_action('rss2_head'); ?>
  <?php
    // On va modifier la requete pour que seules les posts avec plus de deux veilleurs apparaissent
    $args = array(
    'meta_query'=> array(
      array(
        'key' => 'bouillon_nb_veilleurs',
        'compare' => '>',
        'value' => 1,
        'type' => 'numeric',
      )
    ),
    'posts_per_page' => 100
    );
    query_posts( $args );


  ?>
	<?php while( have_posts()) : the_post(); ?>
	<item>
    <?php
      $nb = get_post_meta(get_the_ID(), "bouillon_nb_veilleurs", true);
      
      if ($nb > 1):
    ?>
		<title><?php the_title_rss() ?></title>
		<link><?php the_permalink_rss() ?></link>
		<comments><?php comments_link_feed(); ?></comments>
		<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
		<dc:creator><?php the_author() ?></dc:creator>
		<?php
			the_category_rss('rss2');
			// print_r(the_post());
		?>

		<guid isPermaLink="false"><?php the_guid(); ?></guid>
		<description><![CDATA[<?php
			$veilleurs = get_the_category_list(", ", "", get_the_ID());
			print "Partagé par $veilleurs";
			$mykey_values = get_post_custom_values('bouillon-commentaire', get_the_ID());
			if (sizeof($mykey_values))
			{
				print "";
				print "<dl>";
				foreach ( $mykey_values as $key => $value ) {
					$tab_comm = split("####", $value);
					print "<dt'>Commentaire de ".get_cat_name($tab_comm[0])."&nbsp;:&nbsp;</dt>";
					print "<dd'>".$tab_comm[1]."</dd>";
				}
				print "</dl>";
			}
			the_excerpt();
		?>]]></description>
		<content:encoded><![CDATA[<?php the_content_feed('rss2') ?>]]></content:encoded>
		<wfw:commentRss><?php echo esc_url( get_post_comments_feed_link(null, 'rss2') ); ?></wfw:commentRss>
		<slash:comments><?php echo get_comments_number(); ?></slash:comments>
    <?php
      endif;
    ?>
	</item>
	<?php endwhile; ?>
</channel>
</rss>
