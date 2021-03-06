<?php

class AccessFunctionsTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		// before
		parent::setUp();

		// your set up methods here
	}

	public function tearDown() {
		// your tear down methods here

		// then
		parent::tearDown();
		WPGraphQL::clear_schema();
	}

	/**
	 * Tests whether custom scalars can be registered and used in the Schema
	 *
	 * @throws Exception
	 */
	public function testCustomScalarCanBeUsedInSchema() {

		$test_value = 'test';

		register_graphql_scalar( 'TestScalar', [
			'description'  => __( 'Test Scalar', 'wp-graphql' ),
			'serialize' => function( $value ) {
				return $value;
			},
			'parseValue' => function( $value ) {
				return $value;
			},
			'parseLiteral' => function( $valueNode, array $variables = null ) {
				return isset( $valueNode->value ) ? $valueNode->value : null;
			}
		] );

		register_graphql_field( 'RootQuery', 'testScalar', [
			'type'    => 'TestScalar',
			'resolve' => function() use ( $test_value ) {
				return $test_value;
			}
		] );

		$actual = graphql( [
			'query' => '
    		{
			  __type(name: "TestScalar") {
			    kind
			  }
			}
    		'
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'SCALAR', $actual['data']['__type']['kind'] );

		$actual = graphql( [
			'query' => '
    		{
			  __schema {
			    queryType {
			      fields {
			        name
			        type {
			          name
			          kind
			        }
			      }
			    }
			  }
			}
    		'
		] );

		codecept_debug( $actual );

		$fields = $actual['data']['__schema']['queryType']['fields'];

		$test_scalar = array_filter( $fields, function( $field ) {
			return $field['type']['name'] === 'TestScalar' && $field['type']['kind'] === 'SCALAR' ? $field : null;
		} );

		$this->assertNotEmpty( $test_scalar );

		$actual = graphql( [
			'query' => '
    		{
			  testScalar
			}
    		'
		] );

		codecept_debug( $actual );

		$this->assertEquals( $test_value, $actual['data']['testScalar'] );

	}

	// tests
	public function testMe() {
		$actual   = graphql_format_field_name( 'This is some field name' );
		$expected = 'thisIsSomeFieldName';
		self::assertEquals( $expected, $actual );
	}

	public function testRegisterInputField() {

		/**
		 * Register Test CPT
		 */
		register_post_type( 'test_cpt', [
			"label"               => __( 'Test CPT', 'wp-graphql' ),
			"labels"              => [
				"name"          => __( 'Test CPT', 'wp-graphql' ),
				"singular_name" => __( 'Test CPT', 'wp-graphql' ),
			],
			"description"         => __( 'test-post-type', 'wp-graphql' ),
			"supports"            => [ 'title' ],
			"show_in_graphql"     => true,
			"graphql_single_name" => 'TestCpt',
			"graphql_plural_name" => 'TestCpts',
		] );

		/**
		 * Register a GraphQL Input Field to the connection where args
		 */
		register_graphql_field(
			'RootQueryToTestCptConnectionWhereArgs',
			'testTest',
			[
				'type'        => 'String',
				'description' => 'just testing here'
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			__type( name: "RootQueryToTestCptConnectionWhereArgs" ) { 
				inputFields {
					name
				}
			} 
		}';

		$actual = graphql( [
			'query' => $query,
		] );

		/**
		 * Get an array of names from the inputFields
		 */
		$names = array_column( $actual['data']['__type']['inputFields'], 'name' );

		/**
		 * Assert that `testTest` exists in the $names (the field was properly registered)
		 */
		$this->assertTrue( in_array( 'testTest', $names ) );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToTestCptConnectionWhereArgs', 'testTest' );
		unregister_post_type( 'action_monitor' );
		WPGraphQL::clear_schema();

	}

	/**
	 * Test to make sure "testInputField" doesn't exist in the Schema already
	 * @throws Exception
	 */
	public function testFilteredInputFieldDoesntExistByDefault() {
		/**
		 * Query the "RootQueryToPostConnectionWhereArgs" Type
		 */
		$query = '
		{
		  __type(name: "RootQueryToPostConnectionWhereArgs") {
		    name
		    kind
		    inputFields {
		      name
		    }
		  }
		}
		';

		$actual = graphql([ 'query' => $query ]);

		codecept_debug( $actual );

		/**
		 * Map the names of the inputFields to be an array so we can properly
		 * assert that the input field is there
		 */
		$field_names = array_map( function( $field ) {
			return $field['name'];
		}, $actual['data']['__type']['inputFields'] );

		codecept_debug( $field_names );

		/**
		 * Assert that there is no `testInputField` on the Type already
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'testInputField', $field_names );
	}

	/**
	 * Test to make sure filtering in "testInputField" properly adds the input to the Schema
	 * @throws Exception
	 */
	public function testFilterInputFields() {

		/**
		 * Query the "RootQueryToPostConnectionWhereArgs" Type
		 */
		$query = '
		{
		  __type(name: "RootQueryToPostConnectionWhereArgs") {
		    name
		    kind
		    inputFields {
		      name
		    }
		  }
		}
		';

		/**
		 * Filter in the "testInputField"
		 */
		add_filter( 'graphql_input_fields', function( $fields, $type_name, $config, $type_registry ) {
			if ( isset( $config['queryClass'] ) && 'WP_Query' === $config['queryClass'] ) {
				$fields['testInputField'] = [
					'type' => 'String'
				];
			}
			return $fields;
		}, 10, 4 );

		$actual = graphql([ 'query' => $query ]);

		codecept_debug( $actual );

		/**
		 * Map the names of the inputFields to be an array so we can properly
		 * assert that the input field is there
		 */
		$field_names = array_map( function( $field ) {
			return $field['name'];
		}, $actual['data']['__type']['inputFields'] );

		codecept_debug( $field_names );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertContains( 'testInputField', $field_names );

	}

}
