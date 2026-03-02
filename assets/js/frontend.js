/**
 * WD Market Multi-Warehouse — Frontend JS
 */

/* global wdmwData */

(function () {
	'use strict';

	/**
	 * Auto-submit warehouse filter on change.
	 */
	function initWarehouseFilter() {
		const filterSelect = document.getElementById( 'wdmw_warehouse' );

		if ( ! filterSelect ) {
			return;
		}

		filterSelect.addEventListener( 'change', () => {
			filterSelect.closest( 'form' ).submit();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', initWarehouseFilter );
} )();
