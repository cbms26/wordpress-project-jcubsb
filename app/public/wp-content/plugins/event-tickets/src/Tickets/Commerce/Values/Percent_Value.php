<?php
/**
 * Percent Value.
 *
 * @since 5.18.0
 */

declare( strict_types=1 );

namespace TEC\Tickets\Commerce\Values;

use InvalidArgumentException;

/**
 * Class Percent_Value
 *
 * @since 5.18.0
 */
class Percent_Value extends Precision_Value {

	use Digit_Separators;

	/**
	 * Minimum number a percentage can be.
	 *
	 * @var float
	 */
	protected float $min_threshold = 0.0001;

	/**
	 * Percent_Value constructor.
	 *
	 * Numbers passed into this class should be written as a percent, and not a decimal. For
	 * example, for 10% you would pass in 10, not 0.1. For 5% you would pass in 5, not 0.05.
	 *
	 * @since 5.18.0
	 *
	 * @param float|int|string $value               The value to store. Can be a float, int, or numeric string. The
	 *                                              value will be divided by 100 to convert it to a percentage.
	 * @param ?string          $thousands_separator The thousands separator.
	 * @param ?string          $decimal_separator   The decimal separator.
	 *
	 * @throws InvalidArgumentException When the value is not numeric, or it is too small.
	 */
	public function __construct(
		$value,
		?string $thousands_separator = null,
		?string $decimal_separator = null
	) {
		$value = Float_Value::from_number( $value )->get() / 100;
		parent::__construct( $value, 4 );
		$this->validate_minimum_value();

		$this->thousands_separator = $thousands_separator ?? self::$separator_defaults['thousands_separator'];
		$this->decimal_separator   = $decimal_separator ?? self::$separator_defaults['decimal_separator'];
	}

	/**
	 * Get the value as a percentage.
	 *
	 * @since 5.18.0
	 *
	 * @return float
	 */
	public function get_as_percent(): float {
		return (float) ( $this->get() * 100 );
	}

	/**
	 * Get the value as a decimal.
	 *
	 * Just an alias for get().
	 *
	 * @since 5.18.0
	 *
	 * @return float
	 */
	public function get_as_decimal(): float {
		return $this->get();
	}

	/**
	 * Get the value as a string.
	 *
	 * This includes formatting the value with the % symbol and a specific amount of precision.
	 *
	 * @since 5.18.0
	 *
	 * @return string
	 */
	public function get_as_string(): string {
		return "{$this->get_formatted_number( $this->get_as_percent(), 2 )}%";
	}

	/**
	 * The __toString method allows a class to decide how it will react when it is converted to a string.
	 *
	 * @since 5.18.0
	 *
	 * @return string The value as a string.
	 */
	public function __toString() {
		return $this->get_as_string();
	}

	/**
	 * Validate that the percentage value is not below the allowed threshold.
	 *
	 * @since 5.18.0
	 *
	 * @throws InvalidArgumentException If the percentage value is smaller than the minimum allowed.
	 */
	public function validate_minimum_value(): void {
		if ( abs( $this->get() ) < $this->min_threshold ) {
			throw new InvalidArgumentException(
				sprintf( 'Percent value cannot be smaller than %.4f (%.2f%%).', $this->min_threshold, $this->min_threshold * 100 )
			);
		}
	}

	/**
	 * Set the default decimal and thousands separators.
	 *
	 * @since 5.21.0
	 *
	 * @param ?string $thousands_separator The thousands separator.
	 * @param ?string $decimal_separator   The decimal separator.
	 *
	 * @return void
	 */
	public static function set_defaults(
		?string $thousands_separator = null,
		?string $decimal_separator = null
	) {
		self::set_separator_defaults( $decimal_separator, $thousands_separator );
	}
}
