<?php
/**
 * Sanity checks for the seeded forces list and name normalisation.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class ForcesDataTest
 */
final class ForcesDataTest extends TestCase {

	/**
	 * Seed list.
	 *
	 * @var array
	 */
	private $forces;

	protected function setUp(): void {
		$this->forces = require GCO_PD_DIR . 'includes/data/forces.php';
	}

	public function test_there_are_43_forces_with_unique_slugs_and_names(): void {
		$this->assertCount( 43, $this->forces );
		$this->assertCount( 43, array_unique( array_column( $this->forces, 'slug' ) ) );
		$this->assertCount( 43, array_unique( array_column( $this->forces, 'name' ) ) );
	}

	public function test_fields_fit_their_columns(): void {
		foreach ( $this->forces as $force ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]+$/', $force['slug'] );
			$this->assertLessThanOrEqual( 80, strlen( $force['slug'] ) );
			$this->assertLessThanOrEqual( 120, strlen( $force['name'] ) );
			$this->assertLessThanOrEqual( 255, strlen( $force['aliases'] ) );
			$this->assertContains( $force['country'], array( 'England', 'Wales' ) );
		}
	}

	public function test_four_welsh_forces(): void {
		$wales = array_filter( $this->forces, static fn( $f ) => 'Wales' === $f['country'] );
		$this->assertCount( 4, $wales );
	}

	public function test_names_normalise_for_matching(): void {
		$this->assertSame( 'avonandsomerset', GCO_PD_Repository::normalize_name( 'Avon & Somerset' ) );
		$this->assertSame( 'avonandsomerset', GCO_PD_Repository::normalize_name( 'Avon and Somerset' ) );
		$this->assertSame( 'gmp', GCO_PD_Repository::normalize_name( ' GMP ' ) );
		$this->assertSame( 'dyfedpowys', GCO_PD_Repository::normalize_name( 'Dyfed-Powys' ) );
	}

	public function test_every_force_is_reachable_by_its_npcc_short_name_and_aliases(): void {
		$map = array();
		foreach ( $this->forces as $i => $force ) {
			$names = array( $force['name'], $force['slug'] );
			if ( '' !== $force['aliases'] ) {
				$names = array_merge( $names, explode( ',', $force['aliases'] ) );
			}
			foreach ( $names as $name ) {
				$key = GCO_PD_Repository::normalize_name( $name );
				if ( isset( $map[ $key ] ) ) {
					$this->assertSame( $map[ $key ], $i, "Name '{$name}' maps to two forces." );
				}
				$map[ $key ] = $i;
			}
		}
		foreach ( array( 'GMP', 'TVP', 'Met Police', 'Met', 'Avon & Somerset', 'Devon & Cornwall', 'Hampshire' ) as $short ) {
			$this->assertArrayHasKey( GCO_PD_Repository::normalize_name( $short ), $map, $short );
		}
	}
}
