/**
 * WD Market Multi-Warehouse — Admin JS
 */

(function () {
	'use strict';

	/**
	 * Auto-calculate total stock when warehouse stock inputs change.
	 */
	function initStockCalculator() {
		const stockContainer = document.querySelector( '.wdmw-warehouse-stock' );

		if ( ! stockContainer ) {
			return;
		}

		const stockInputs = stockContainer.querySelectorAll(
			'input[name^="wdmw_warehouse_stock"]'
		);
		const totalDisplay = stockContainer.querySelector(
			'.wdmw-total-stock'
		);

		if ( ! totalDisplay || stockInputs.length === 0 ) {
			return;
		}

		stockInputs.forEach( ( input ) => {
			input.addEventListener( 'input', () => {
				updateTotalStock( stockInputs, totalDisplay );
			} );
		} );
	}

	function updateTotalStock( inputs, display ) {
		let total = 0;
		inputs.forEach( ( input ) => {
			total += parseInt( input.value, 10 ) || 0;
		} );
		display.textContent = total;
	}

	document.addEventListener( 'DOMContentLoaded', initStockCalculator );
} )();
