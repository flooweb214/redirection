<?php

use Redirection\FileIO;
use Redirection\Redirect;
use Redirection\Group;

require dirname( __FILE__ ) . '/../../includes/file-io/fileio-json.php';

class JsonTest extends WP_UnitTestCase {
	public function testExportEmpty() {
		$json = new FileIO\Json();
		$data = json_decode( $json->get_data( array(), array() ) );

		$this->assertTrue( empty( $data->groups ) );
		$this->assertTrue( empty( $data->redirects ) );
		$this->assertTrue( isset( $data->plugin->version ) );
	}

	public function testExportNew() {
		$json = new FileIO\Json();
		$redirects = [ new Redirect\Redirect( (object)[ 'url' => 'source', 'match_type' => 'url', 'id' => 1, 'action_type' => 'url' ] ) ];
		$groups = [ (new Group\Group( (object)[ 'name' => 'group', 'id' => 1 ] ) )->to_json() ];

		$data = json_decode( $json->get_data( $redirects, $groups ) );

		$this->assertEquals( 'source', $data->redirects[ 0 ]->url );
		$this->assertEquals( 1, $data->groups[ 0 ]->id );
	}

	public function testImportBad() {
		$json = new FileIO\Json();
		$data = $json->load( 0, 'thing', 'x' );
		$this->assertEquals( 0, $data );
	}

	public function testImport() {
		global $wpdb;

		$import = array(
			'groups' => array(
				array(
					'name' => 'groupx',
					'id' => 1,
					'module_id' => 1,
					'enabled' => true,
				),
			),
			'redirects' => array(
				array(
					'url' => '/source1',
					'id' => 1,
					'group_id' => 5,
					'match_type' => 'url',
					'action_type' => 'url',
					'action_data' => array( 'url' => '/test' ),
				),
			),
		);

		$json = new FileIO\Json();
		$data = $json->load( 0, 'thing', wp_json_encode( $import ) );
		$this->assertEquals( 1, $data );

		$group = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}redirection_groups ORDER BY id DESC LIMIT 1" );
		$redirect = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}redirection_items ORDER BY id DESC LIMIT 1" );

		$this->assertEquals( 'Group', $group->name );
		$this->assertEquals( '/source1', $redirect->url );
		$this->assertEquals( 1, $redirect->group_id );
	}
}
