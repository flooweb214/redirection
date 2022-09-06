<?php

namespace Redirection\FileIO;

use Redirection\Group;
use Redirection\Redirect;

class Json extends FileIO {
	public function force_download() {
		parent::force_download();

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $this->export_filename( 'json' ) . '"' );
	}

	public function get_data( array $items, array $groups ) {
		$version = \Redirection\Plugin\Settings\red_get_plugin_data( REDIRECTION_FILE );

		$items = array(
			'plugin' => array(
				'version' => trim( $version['Version'] ),
				'date' => date( 'r' ),
			),
			'groups' => $groups,
			'redirects' => array_map( function( $item ) {
				return $item->to_json();
			}, $items ),
		);

		return wp_json_encode( $items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
	}

	public function load( $group, $filename, $data ) {
		global $wpdb;

		$count = 0;
		$json = @json_decode( $data, true );
		if ( $json === false ) {
			return 0;
		}

		// Import groups
		$groups = [];

		if ( isset( $json['groups'] ) ) {
			foreach ( $json['groups'] as $group ) {
				$new_group = Group\Group::get( $group['id'] );
				if ( ! $new_group ) {
					$new_group = Group\Group::create( $group['name'], $group['module_id'], $group['enabled'] ? true : false );
				}

				if ( $new_group ) {
					$groups[ $group['id'] ] = $new_group;
				}
			}
		}

		unset( $json['groups'] );

		// Import redirects
		if ( isset( $json['redirects'] ) ) {
			foreach ( $json['redirects'] as $pos => $redirect ) {
				unset( $redirect['id'] );

				if ( ! isset( $groups[ $redirect['group_id'] ] ) ) {
					// Group is not listed so create one for it
					$group = Group\Group::create( 'Group', 1 );
					$groups[ $group->get_id() ] = $group;
					$redirect['group_id'] = $group->get_id();
				}

				if ( $redirect['match_type'] === 'url' && isset( $redirect['action_data'] ) && ! is_array( $redirect['action_data'] ) ) {
					$redirect['action_data'] = array( 'url' => $redirect['action_data'] );
				}

				$redirect['group_id'] = $groups[ $redirect['group_id'] ];
				Redirect\Redirect::create( $redirect );
				$count++;

				// Helps reduce memory usage
				unset( $json['redirects'][ $pos ] );
				$wpdb->queries = array();
				$wpdb->num_queries = 0;
			}
		}

		return $count;
	}
}
