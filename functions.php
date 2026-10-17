<?php
/**
 * cotrim.dev — Console theme functions.
 *
 * @package Cotrimdev_Console
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue parent/child styles, console script, and Google Fonts.
 */
function cotrimdev_enqueue_assets() {
	$theme   = wp_get_theme();
	$version = $theme->get( 'Version' );

	wp_enqueue_style(
		'twentytwentyfive-style',
		get_template_directory_uri() . '/style.css',
		array(),
		$theme->parent() ? $theme->parent()->get( 'Version' ) : $version
	);

	wp_enqueue_style(
		'cotrimdev-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'twentytwentyfive-style' ),
		$version
	);

	wp_enqueue_style(
		'cotrimdev-console',
		get_stylesheet_directory_uri() . '/assets/css/terminal.css',
		array( 'cotrimdev-style' ),
		$version
	);

	wp_enqueue_style(
		'cotrimdev-google-fonts',
		'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_script(
		'cotrimdev-console',
		get_stylesheet_directory_uri() . '/assets/js/console.js',
		array(),
		$version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'cotrimdev_enqueue_assets' );

/**
 * Preconnect to Google Fonts for faster loads.
 */
function cotrimdev_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.googleapis.com' );
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'cotrimdev_resource_hints', 10, 2 );

/**
 * Body class used as the scoping root for console.css.
 */
function cotrimdev_body_class( $classes ) {
	$classes[] = 'cotrimdev-retro-theme';
	return $classes;
}
add_filter( 'body_class', 'cotrimdev_body_class' );

/**
 * Approximate reading time in minutes from a post's content (220 wpm).
 */
function cotrimdev_reading_time( $post_id ) {
	$content = get_post_field( 'post_content', $post_id );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	$minutes = max( 1, (int) ceil( $words / 220 ) );
	return $minutes . 'm';
}

/**
 * Pick one "primary" tag for a post for the console row: essay / note / link.
 * Falls back to the first tag slug, or 'essay' if none.
 */
function cotrimdev_primary_tag( $post_id ) {
	$allowed = array( 'essay', 'note', 'link' );
	$terms   = get_the_terms( $post_id, 'post_tag' );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( in_array( $t->slug, $allowed, true ) ) {
				return $t->slug;
			}
		}
		if ( ! empty( $terms ) ) {
			return $terms[0]->slug;
		}
	}
	return 'essay';
}

/**
 * Running index: total post count - current position (newest = highest number).
 * Cached per-request in a static array.
 */
function cotrimdev_post_number( $post_id ) {
	static $map = null;
	if ( null === $map ) {
		$all = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$map = array();
		foreach ( $all as $i => $id ) {
			$map[ (int) $id ] = $i + 1;
		}
	}
	return isset( $map[ (int) $post_id ] ) ? $map[ (int) $post_id ] : 0;
}

/**
 * Total published post count.
 */
function cotrimdev_total_posts() {
	$counts = wp_count_posts( 'post' );
	return (int) ( isset( $counts->publish ) ? $counts->publish : 0 );
}

/**
 * Render one styled row for the console post list.
 */
function cotrimdev_render_row( $post_id ) {
	$num   = cotrimdev_post_number( $post_id );
	$date  = get_the_date( 'Y.m.d', $post_id );
	$title = get_the_title( $post_id );
	$url   = get_permalink( $post_id );
	$read  = cotrimdev_reading_time( $post_id );
	$tag   = cotrimdev_primary_tag( $post_id );
	$year  = get_the_date( 'Y', $post_id );

	return sprintf(
		'<a class="con-row" href="%1$s" data-tag="%2$s" data-year="%3$s"><span class="col-num">#%4$03d</span><span class="col-date">%5$s</span><span class="col-title"><span class="con-title">%6$s</span></span><span class="col-read">%7$s</span><span class="col-tag con-tag-%2$s">[%2$s]</span></a>',
		esc_url( $url ),
		esc_attr( $tag ),
		esc_attr( $year ),
		(int) $num,
		esc_html( $date ),
		esc_html( $title ),
		esc_html( $read )
	);
}

/**
 * [con_postlist count="5"] — flat recent-posts list for the homepage.
 */
function cotrimdev_sc_postlist( $atts ) {
	$atts  = shortcode_atts( array( 'count' => 5 ), $atts, 'con_postlist' );
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['count'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	if ( empty( $posts ) ) {
		return '<div class="con-postlist"><p style="color:var(--mute);font-family:var(--sans)">No posts yet.</p></div>';
	}

	$rows = '';
	foreach ( $posts as $id ) {
		$rows .= cotrimdev_render_row( $id );
	}
	return '<div class="con-postlist">' . $rows . '</div>';
}
add_shortcode( 'con_postlist', 'cotrimdev_sc_postlist' );

/**
 * [con_archive_header] — big H1 + subhead used on the blog archive (home.html).
 */
function cotrimdev_sc_archive_header() {
	$total = cotrimdev_total_posts();
	$first = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'ASC', 'fields' => 'ids' ) );
	$start = ! empty( $first ) ? get_the_date( 'Y', $first[0] ) : '2021';

	ob_start();
	?>
	<p class="con-prompt"><span class="sign">~$</span> <span class="cmd">ls -la archive/</span> <span class="note"># <?php echo (int) $total; ?> total</span></p>
	<h1 class="con-h1-page">Writing — <span class="con-accent-green"><?php echo (int) $total; ?></span> posts since <?php echo esc_html( $start ); ?>.</h1>
	<p class="con-subhead">Essays, short notes, and links. Mostly about tools, the web, and the craft of shipping small, durable things.</p>
	<?php
	return ob_get_clean();
}
add_shortcode( 'con_archive_header', 'cotrimdev_sc_archive_header' );

/**
 * [con_archive_list] — year-grouped, filterable list.
 */
function cotrimdev_sc_archive_list( $atts ) {
	$atts = shortcode_atts( array( 'archive' => '0' ), $atts, 'con_archive_list' );

	$query = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
	);

	if ( '1' === (string) $atts['archive'] && ( is_tag() || is_category() ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			);
		}
	}

	$post_ids = get_posts( $query );

	if ( empty( $post_ids ) ) {
		return '<div class="con-postlist"><p style="color:var(--mute);font-family:var(--sans)">No posts yet.</p></div>';
	}

	// Group by year.
	$groups = array();
	foreach ( $post_ids as $id ) {
		$y = get_the_date( 'Y', $id );
		$groups[ $y ][] = $id;
	}
	krsort( $groups );

	$out  = '<div class="con-postlist">';
	foreach ( $groups as $year => $ids ) {
		$count = count( $ids );
		$out  .= sprintf(
			'<div data-year-group="%1$s"><div class="con-year"># %1$s — %2$d post%3$s</div>',
			esc_attr( $year ),
			$count,
			1 === $count ? '' : 's'
		);
		foreach ( $ids as $id ) {
			$out .= cotrimdev_render_row( $id );
		}
		$out .= '</div>';
	}
	$out .= '</div>';
	return $out;
}
add_shortcode( 'con_archive_list', 'cotrimdev_sc_archive_list' );

/**
 * Single-post shortcodes: breadcrumbs, meta strip, dek, tags, prev/next.
 */

function cotrimdev_sc_post_breadcrumbs() {
	$id  = get_the_ID();
	$num = cotrimdev_post_number( $id );
	return sprintf(
		'<div class="con-breadcrumbs"><a href="%1$s">~</a><span class="sep"> / </span><a href="%2$s">blog</a><span class="sep"> / </span><span class="cur">#%3$03d</span></div>',
		esc_url( home_url( '/' ) ),
		esc_url( home_url( '/blog' ) ),
		(int) $num
	);
}
add_shortcode( 'con_post_breadcrumbs', 'cotrimdev_sc_post_breadcrumbs' );

function cotrimdev_sc_post_meta() {
	$id   = get_the_ID();
	$date = get_the_date( 'Y.m.d', $id );
	$tag  = cotrimdev_primary_tag( $id );
	$read = cotrimdev_reading_time( $id );

	$terms = get_the_terms( $id, 'post_tag' );
	$tags  = '';
	if ( is_array( $terms ) ) {
		foreach ( $terms as $t ) {
			$tags .= sprintf(
				' <a class="tag" href="%1$s">#%2$s</a>',
				esc_url( get_term_link( $t ) ),
				esc_html( $t->slug )
			);
		}
	}

	return sprintf(
		'<div class="con-meta">%1$s <span class="dot">·</span> %2$s <span class="dot">·</span> %3$s read%4$s</div>',
		esc_html( $date ),
		esc_html( $tag ),
		esc_html( str_replace( 'm', ' min', $read ) ),
		$tags
	);
}
add_shortcode( 'con_post_meta', 'cotrimdev_sc_post_meta' );

function cotrimdev_sc_post_dek() {
	$id      = get_the_ID();
	$excerpt = get_post_field( 'post_excerpt', $id );
	if ( empty( $excerpt ) ) {
		return '';
	}
	return '<p class="con-dek">' . esc_html( $excerpt ) . '</p>';
}
add_shortcode( 'con_post_dek', 'cotrimdev_sc_post_dek' );

function cotrimdev_sc_post_tags() {
	$id    = get_the_ID();
	$terms = get_the_terms( $id, 'post_tag' );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '';
	}
	$links = '';
	foreach ( $terms as $t ) {
		$links .= sprintf(
			'<a href="%1$s">#%2$s</a>',
			esc_url( get_term_link( $t ) ),
			esc_html( $t->slug )
		);
	}
	return '<div class="con-post-tags">' . $links . '</div>';
}
add_shortcode( 'con_post_tags', 'cotrimdev_sc_post_tags' );

function cotrimdev_sc_post_prevnext() {
	$prev = get_previous_post();
	$next = get_next_post();
	if ( ! $prev && ! $next ) {
		return '';
	}

	$out  = '<div class="con-prevnext">';
	if ( $prev ) {
		$pnum = cotrimdev_post_number( $prev->ID );
		$out .= sprintf(
			'<a class="con-prevnext-card con-prevnext-card--prev" href="%1$s"><div class="con-prevnext-eyebrow">← prev · #%2$03d</div><div class="con-prevnext-title"><span class="con-title">%3$s</span></div></a>',
			esc_url( get_permalink( $prev->ID ) ),
			(int) $pnum,
			esc_html( get_the_title( $prev->ID ) )
		);
	} else {
		$out .= '<span></span>';
	}
	if ( $next ) {
		$nnum = cotrimdev_post_number( $next->ID );
		$out .= sprintf(
			'<a class="con-prevnext-card con-prevnext-card--next" href="%1$s"><div class="con-prevnext-eyebrow">next · #%2$03d →</div><div class="con-prevnext-title"><span class="con-title">%3$s</span></div></a>',
			esc_url( get_permalink( $next->ID ) ),
			(int) $nnum,
			esc_html( get_the_title( $next->ID ) )
		);
	} else {
		$out .= '<span></span>';
	}
	$out .= '</div>';
	return $out;
}
add_shortcode( 'con_post_prevnext', 'cotrimdev_sc_post_prevnext' );
