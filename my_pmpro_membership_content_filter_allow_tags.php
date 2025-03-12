<?php
/**
 * Allow HTML tags in member content when filtering member-restricted  content.
 * This is achieved by commenting out the line running the wp_strip_all_tags function
 * for filtered member-only content.
 */

// First, let's remove the default pmpro_membership_content_filter.
function remove_pmpro_membership_content_filter() {
	if ( ! function_exists( 'pmpro_membership_content_filter' ) ) {
		return;
	}
	remove_filter( 'the_content', 'pmpro_membership_content_filter', 5 );
}
add_action( 'init', 'remove_pmpro_membership_content_filter' );

// Now let's add our own filter to allow HTML tags in member content.
add_filter( 'the_content', 'my_pmpro_membership_content_filter_allow_tags', 9 );

function my_pmpro_membership_content_filter_allow_tags( $content, $skipcheck = false ) {
	global $post, $current_user;

	if ( ! function_exists( 'pmpro_membership_content_filter' ) ) {
		return $content;
	}

	if ( ! $skipcheck ) {
		$hasaccess = pmpro_has_membership_access( null, null, true );
		if ( is_array( $hasaccess ) ) {
			//returned an array to give us the membership level values
			$post_membership_levels_ids   = $hasaccess[1];
			$post_membership_levels_names = $hasaccess[2];
			$hasaccess                    = $hasaccess[0];
		}
	}

	/**
	 * Filter to let other plugins change how PMPro filters member content.
	 * If anything other than false is returned, that value will overwrite
	 * the $content variable and no further processing is done in this function.
	 */
	$content_filter = apply_filters( 'my_pmpro_membership_content_filter_allow_tags', false, $content, $hasaccess );
	if ( $content_filter !== false ) {
		return $content_filter;
	}

	if ( $hasaccess ) {
		//all good, return content
		return $content;
	} else {
		//if show excerpts is set, return just the excerpt
		if ( pmpro_getOption( 'showexcerpts' ) ) {
		    
			//show excerpt
			global $post;
			if ( $post->post_excerpt ) {
				//defined excerpt
				$content = wpautop( $post->post_excerpt );
			} elseif ( strpos( $content, '<span id="more-' . $post->ID . '"></span>' ) !== false ) {
				//more tag
				$pos     = strpos( $content, '<span id="more-' . $post->ID . '"></span>' );
				$content = substr( $content, 0, $pos );
			} elseif ( strpos( $content, 'class="more-link">' ) !== false ) {
				//more link
				$content = preg_replace( '/\<a.*class\="more\-link".*\>.*\<\/a\>/', '', $content );
			} elseif ( strpos( $content, '<!-- wp:more -->' ) !== false ) {
				//more block
				$pos     = strpos( $content, '<!-- wp:more -->' );
				$content = substr( $content, 0, $pos );
			} elseif ( strpos( $content, '<!--more-->' ) !== false ) {
				//more tag
				$pos     = strpos( $content, '<!--more-->' );
				$content = substr( $content, 0, $pos );
			} else {
				//auto generated excerpt. pulled from wp_trim_excerpt
				$content = strip_shortcodes( $content );
				$content = str_replace( ']]>', ']]&gt;', $content );
				// $content = wp_strip_all_tags( $content ); -> So HTML stripping is off
				$excerpt_length = apply_filters( 'excerpt_length', 100 );
				// $words          = preg_split( "/[\n\r\t ]+/", $content, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY );  -> so line break does not get removed
				$words          = preg_split( "/[\r\t ]+/", $content, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY );

				// Check if there's a link in the first 100 words
				$excerpt_text = implode( ' ', array_slice( $words, 0, 100 ) );
				if ( preg_match( '/<a\s[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $excerpt_text ) ) {
					// Extend limit if a link is found
					$excerpt_length = 120;
					$words = preg_split( "/[\r\t ]+/", $content, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY );
				}

				if ( count( $words ) > $excerpt_length ) {
					array_pop( $words );
					$content = implode( ' ', $words );
					$content = $content . '... ';
				} else {
					$content = implode( ' ', $words ) . '... ';
				}

				$content = wpautop( $content );
			}
			
		} else {
			//else hide everything
			$content = '';
		}
        
		$content = pmpro_get_no_access_message( $content, $post_membership_levels_ids, $post_membership_levels_names );

	}

	return $content;
}
