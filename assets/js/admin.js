/**
 * Documentation Admin JavaScript
 *
 * @package Morntag\WpDocsManager
 */

(function ($) {
	'use strict';

	$( document ).ready(
		function () {

			// Search functionality
			var searchTimeout;
			$( '#mcc-docs-search' ).on(
				'input',
				function () {
					var query = $( this ).val();

					clearTimeout( searchTimeout );
					searchTimeout = setTimeout(
						function () {
							searchDocumentation( query );
						},
						300
					);
				}
			);

			/**
			 * Search documentation via AJAX.
			 *
			 * @param {string} query Search query string.
			 */
			function searchDocumentation(query) {
				if (query.length < 3) {
					$( '.mcc-docs-nav-items li' ).show();
					return;
				}

				$.ajax(
					{
						url: morntagDocs.ajaxUrl,
						type: 'POST',
						data: {
							action: 'morntag_docs_search',
							nonce: morntagDocs.nonce,
							query: query
						},
						success: function (response) {
							if (response.success) {
								displaySearchResults( response.data );
							}
						}
					}
				);
			}

			/**
			 * Display search results by showing/hiding nav items.
			 *
			 * For custom doc matches, also reveal all ancestor li nodes in the
			 * tree so the matched item is visible regardless of collapse state.
			 *
			 * @param {Array} results Array of search result objects.
			 */
			function displaySearchResults(results) {
				// Hide all items first.
				$( '.mcc-docs-nav-items li' ).hide();

				// Show matching items.
				results.forEach(
					function (result) {
						if (result.type === 'custom') {
							var $item = $( 'a[href*="doc_id=' + result.id + '"]' ).closest( 'li' );
							$item.show();

							// Walk up the tree revealing all ancestor doc parent nodes.
							$item.parents( 'li.mcc-docs-has-children' ).each(
								function () {
									$( this ).show().removeClass( 'collapsed' );
								}
							);
						} else {
							$( 'a[href*="path=' + encodeURIComponent( result.path ) + '"]' ).closest( 'li' ).show();
						}
					}
				);
			}

			// Add current class to active navigation item.
			var currentUrl = window.location.href;
			$( '.mcc-docs-nav-items a' ).each(
				function () {
					if (currentUrl.indexOf( $( this ).attr( 'href' ) ) !== -1) {
						$( this ).parent().addClass( 'current' );
					}
				}
			);

			// Section collapse/expand functionality.
			var STORAGE_KEY      = 'mcc_docs_collapsed_sections';
			var DOCS_STORAGE_KEY = 'mcc_docs_collapsed_docs';

			/**
			 * Get collapsed sections from localStorage.
			 *
			 * @return {Array} Array of collapsed section IDs.
			 */
			function getCollapsedSections() {
				var stored = localStorage.getItem( STORAGE_KEY );
				return stored ? JSON.parse( stored ) : [];
			}

			/**
			 * Save collapsed sections to localStorage.
			 *
			 * @param {Array} sections Array of collapsed section IDs.
			 */
			function saveCollapsedSections(sections) {
				localStorage.setItem( STORAGE_KEY, JSON.stringify( sections ) );
			}

			/**
			 * Get collapsed doc IDs from localStorage.
			 *
			 * Returns null when no data has been stored yet (first visit).
			 *
			 * @return {Array|null} Array of collapsed doc ID strings, or null on first visit.
			 */
			function getCollapsedDocs() {
				var stored = localStorage.getItem( DOCS_STORAGE_KEY );
				return stored ? JSON.parse( stored ) : null;
			}

			/**
			 * Save collapsed doc IDs to localStorage.
			 *
			 * @param {Array} docIds Array of doc ID strings.
			 */
			function saveCollapsedDocs(docIds) {
				localStorage.setItem( DOCS_STORAGE_KEY, JSON.stringify( docIds ) );
			}

			// Initialize section collapsed state from localStorage.
			var collapsed = getCollapsedSections();
			$( '.mcc-docs-nav-section' ).each(
				function () {
					var section = $( this ).find( 'h3' ).data( 'section' );
					if (collapsed.indexOf( section ) !== -1) {
						$( this ).addClass( 'collapsed' );
					}
				}
			);

			// Auto-expand section containing current doc.
			$( '.mcc-docs-nav-items .current' ).closest( '.mcc-docs-nav-section' ).removeClass( 'collapsed' );

			// Toggle section on header click.
			$( '.mcc-docs-nav-section h3' ).on(
				'click',
				function () {
					var $section  = $( this ).closest( '.mcc-docs-nav-section' );
					var sectionId = $( this ).data( 'section' );

					$section.toggleClass( 'collapsed' );

					// Update localStorage.
					var collapsed = getCollapsedSections();
					var index     = collapsed.indexOf( sectionId );
					if ($section.hasClass( 'collapsed' )) {
						if (index === -1) {
							collapsed.push( sectionId );
						}
					} else {
						if (index !== -1) {
							collapsed.splice( index, 1 );
						}
					}
					saveCollapsedSections( collapsed );
				}
			);

			// ---------------------------------------------------------------
			// Doc-level tree collapse/expand.
			// ---------------------------------------------------------------

			/**
			 * Collapse a parent doc li and persist the state.
			 *
			 * @param {jQuery} $li      The parent doc <li> element.
			 * @param {Array}  docIds   Current array of collapsed doc IDs (mutated in place).
			 */
			function collapseDoc($li, docIds) {
				var id = String( $li.data( 'doc-id' ) );
				$li.addClass( 'collapsed' );
				if (docIds.indexOf( id ) === -1) {
					docIds.push( id );
				}
			}

			/**
			 * Expand a parent doc li and persist the state.
			 *
			 * @param {jQuery} $li      The parent doc <li> element.
			 * @param {Array}  docIds   Current array of collapsed doc IDs (mutated in place).
			 */
			function expandDoc($li, docIds) {
				var id    = String( $li.data( 'doc-id' ) );
				var index = docIds.indexOf( id );
				$li.removeClass( 'collapsed' );
				if (index !== -1) {
					docIds.splice( index, 1 );
				}
			}

			// Initialise doc-tree collapsed state.
			var storedDocIds = getCollapsedDocs();

			if (storedDocIds !== null) {
				// Restore previously saved state.
				$( 'li.mcc-docs-has-children' ).each(
					function () {
						var id = String( $( this ).data( 'doc-id' ) );
						if (storedDocIds.indexOf( id ) !== -1) {
							$( this ).addClass( 'collapsed' );
						}
					}
				);

				// Always ensure the ancestor chain of the current item is expanded.
				$( 'li.current' ).parents( 'li.mcc-docs-has-children' ).each(
					function () {
						var id    = String( $( this ).data( 'doc-id' ) );
						var index = storedDocIds.indexOf( id );
						$( this ).removeClass( 'collapsed' );
						if (index !== -1) {
							storedDocIds.splice( index, 1 );
						}
					}
				);
				saveCollapsedDocs( storedDocIds );
			} else {
				// First visit: collapse all parent docs except those in the
				// ancestor chain of the currently active item.
				var $ancestorIds = [];
				$( 'li.current' ).parents( 'li.mcc-docs-has-children' ).each(
					function () {
						$ancestorIds.push( String( $( this ).data( 'doc-id' ) ) );
					}
				);

				var initialCollapsed = [];
				$( 'li.mcc-docs-has-children' ).each(
					function () {
						var id = String( $( this ).data( 'doc-id' ) );
						if ($ancestorIds.indexOf( id ) === -1) {
							$( this ).addClass( 'collapsed' );
							initialCollapsed.push( id );
						}
					}
				);
				saveCollapsedDocs( initialCollapsed );
			}

			// Toggle doc parent on toggle icon click.
			$( document ).on(
				'click',
				'.mcc-docs-doc-toggle',
				function (e) {
					e.stopPropagation();
					var $li      = $( this ).closest( 'li.mcc-docs-has-children' );
					var docIds   = getCollapsedDocs() || [];

					if ($li.hasClass( 'collapsed' )) {
						expandDoc( $li, docIds );
					} else {
						collapseDoc( $li, docIds );
					}
					saveCollapsedDocs( docIds );
				}
			);

			// Print functionality.
			$( '.mcc-docs-print' ).on(
				'click',
				function () {
					window.print();
				}
			);

			// Smooth scroll for TOC links.
			$( '.mcc-docs-toc a' ).on(
				'click',
				function (e) {
					e.preventDefault();
					var target = $( $( this ).attr( 'href' ) );
					if (target.length) {
						$( 'html, body' ).animate(
							{
								scrollTop: target.offset().top - 20
							},
							500
						);
					}
				}
			);

			// Add IDs to headings for TOC anchors.
			$( '.mcc-docs-content-body h1, .mcc-docs-content-body h2, .mcc-docs-content-body h3' ).each(
				function () {
					var text = $( this ).text();
					var id   = text.toLowerCase().replace( /[^\w\s]/gi, '' ).replace( /\s+/g, '-' );
					$( this ).attr( 'id', id );
				}
			);

		}
	);

})( jQuery );
