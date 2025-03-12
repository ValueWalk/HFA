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
	
	// Check if the post type is one of the post types we want to apply this to.
    if ( ! in_array( $post->post_type, array( 'post', 'hvs', 'business' ) ) ) {
        return $content;
    }
    
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


/**
 * This recipe restricts all content of a Custom Post Type (CPT)
 * to members only.
 *
 * This recipe assumes your CPT name is "recipe".
 * You should replace this with your CPT name for the filter name
 * and the $my_cpt variable's value.
 *
 */

function my_pmpro_restrict_all_cpt( $hasaccess, $thepost, $theuser, $post_membership_levels ) {

	// If PMPro says false already, return false.
	if ( ! $hasaccess ) {
		// Give admin access
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		} else {
			return false;
		}
	}

	if ( ! is_user_logged_in() ) {
		return false;
	}

	// Get levels
	global $pmpro_levels;
	$post_membership_levels_ids = array_keys( $pmpro_levels );

	// Set levels to restrict access to CPT for specific levels only (Optional)
	$my_post_membership_levels_ids = array( 9, 10, 11 ); // set specific  levels here, e.g. array( 1, 2, 3, 4 );

	if ( ! empty( $my_post_membership_levels_ids ) ) {
		$post_membership_levels_ids = $my_post_membership_levels_ids;
	}

	$theuser->membership_levels = pmpro_getMembershipLevelsForUser( $theuser->ID );

	$mylevelids = array();

	foreach ( $theuser->membership_levels as $curlevel ) {
		$mylevelids[] = $curlevel->id;
	}
	if ( count( $theuser->membership_levels ) > 0 && count( array_intersect( $mylevelids, $post_membership_levels_ids ) ) > 0 ) {
		//the users membership id is one that will grant access
		$hasaccess = true;
	} else {
		//user isn't a member of a level with access
		$hasaccess = false;
	}

	return $hasaccess;
}
// set the filter name to your post type name: pmpro_has_membership_access_filter_[post_type]
add_filter( 'pmpro_has_membership_access_filter_hvs', 'my_pmpro_restrict_all_cpt', 10, 4 );

function disable_pmpro_cpt_redirect() {
	$my_cpt = 'hvs'; // Set your custom post type name here

	// check if post belongs to the CPT
	if ( is_singular() && get_post_type() === $my_cpt ) {
		if ( has_action( 'template_redirect', 'pmprocpt_template_redirect' ) ) {
			remove_action( 'template_redirect', 'pmprocpt_template_redirect' );
		}
	}
}
add_action( 'wp', 'disable_pmpro_cpt_redirect' );
