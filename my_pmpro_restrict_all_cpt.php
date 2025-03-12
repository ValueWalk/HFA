<?php
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
