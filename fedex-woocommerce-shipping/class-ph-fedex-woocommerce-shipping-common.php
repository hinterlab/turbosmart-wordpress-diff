<?php
/**
 * Common FedEx Class.
 */
if( ! defined('ABSPATH') )	exit();

if( ! class_exists('Ph_Fedex_Woocommerce_Shipping_Common') ) {
	class Ph_Fedex_Woocommerce_Shipping_Common {

		/**
		 * Get the Converted Weight.
		 * @param mixed $to_unit To Unit.
		 * @param string $from_unit (Optional) From unit if noting is passed then store dimension unit will be taken.
		 * @return float
		 */
		public static function ph_get_converted_weight( $weight, $to_unit, $from_unit=''){
			$weight = (float) $weight;
			$converted_weight = wc_get_weight( $weight, $to_unit, $from_unit );
			return apply_filters( 'ph_fedex_get_converted_weight',$converted_weight, $weight, $to_unit, $from_unit );
		}

		/**
		 * Get the Converted Dimension.
		 * @param mixed $to_unit To Unit.
		 * @param string $from_unit (Optional) From unit if noting is passed then store weight unit will be taken.
		 * @return float
		 */
		public static function ph_get_converted_dimension( $dimension, $to_unit, $from_unit='' ){
			$dimension = (float) $dimension;
			$converted_dimension = wc_get_dimension( $dimension, $to_unit, $from_unit );
			return apply_filters( 'ph_fedex_get_converted_dimension', $converted_dimension, $dimension, $to_unit, $from_unit );
		}
	}
}