/**
 * Doc Picker Modal - Internal doc link insertion for Tiptap editor
 *
 * Provides a modal with two tabs:
 * - "Internal Doc": searchable list of all docs grouped by type
 * - "External URL": freeform URL with display text
 */

/** @type {{ module: Array, docs: Array, custom: Array }|null} */
let docsCache = null;

/**
 * Fetch all docs from the AJAX endpoint
 *
 * Results are cached after the first successful fetch so subsequent
 * modal opens do not trigger a network request.
 *
 * @return {Promise<{ module: Array, docs: Array, custom: Array }>}
 */
async function fetchDocs() {
	if (docsCache) {
		return docsCache;
	}

	const ajaxUrl =
		typeof morntagDocs !== 'undefined'
			? morntagDocs.ajaxUrl
			: '/wp-admin/admin-ajax.php';
	const nonce = typeof morntagDocs !== 'undefined' ? morntagDocs.nonce : '';

	const body = new URLSearchParams({
		action: 'morntag_docs_list',
		nonce,
	});

	const response = await fetch(ajaxUrl, { method: 'POST', body });
	const json = await response.json();

	if (json.success && json.data) {
		docsCache = json.data;
		return docsCache;
	}

	return { module: [], docs: [], custom: [] };
}

/**
 * Render a list of doc items into a container element
 *
 * @param {HTMLElement}                          container   Target element
 * @param {Array<{id: *, title: string, url: string}>} items Doc items
 * @param {string}                               filter      Filter string (lower-case)
 * @param {{ item: Object|null }}                selection   Shared selection ref
 * @param {HTMLInputElement}                     displayInput Display text input
 * @param {HTMLButtonElement}                    insertBtn   Insert button
 */
function renderItems(
	container,
	items,
	filter,
	selection,
	displayInput,
	insertBtn,
) {
	container.innerHTML = '';

	const visible = filter
		? items.filter((item) => item.title.toLowerCase().includes(filter))
		: items;

	if (!visible.length) {
		const empty = document.createElement('p');
		empty.className = 'mcc-doc-picker-empty';
		empty.textContent = 'No results.';
		container.appendChild(empty);
		return;
	}

	visible.forEach((item) => {
		const el = document.createElement('button');
		el.type = 'button';
		el.className = 'mcc-doc-picker-item';
		el.textContent = item.title;

		if (selection.item && selection.item.id === item.id) {
			el.classList.add('is-selected');
		}

		el.addEventListener('click', () => {
			container
				.closest('.mcc-doc-picker-list-wrap')
				.querySelectorAll('.mcc-doc-picker-item')
				.forEach((b) => b.classList.remove('is-selected'));
			el.classList.add('is-selected');

			selection.item = item;

			// Prefill display text with doc title if field is empty or still
			// contains the previously selected item's title.
			if (!displayInput.value || displayInput.dataset.autoFilled === '1') {
				displayInput.value = item.title;
				displayInput.dataset.autoFilled = '1';
			}

			insertBtn.disabled = false;
		});

		container.appendChild(el);
	});
}

/**
 * Rebuild all three section lists based on the current filter value
 *
 * @param {Object}          allDocs     Grouped docs data
 * @param {HTMLElement}     listWrap    The .mcc-doc-picker-list-wrap element
 * @param {string}          filter      Lower-cased search string
 * @param {{ item: Object|null }} selection Shared selection ref
 * @param {HTMLInputElement} displayInput Display text input
 * @param {HTMLButtonElement} insertBtn  Insert button
 */
function rebuildLists(
	allDocs,
	listWrap,
	filter,
	selection,
	displayInput,
	insertBtn,
) {
	renderItems(
		listWrap.querySelector('[data-section="module"]'),
		allDocs.module,
		filter,
		selection,
		displayInput,
		insertBtn,
	);
	renderItems(
		listWrap.querySelector('[data-section="docs"]'),
		allDocs.docs,
		filter,
		selection,
		displayInput,
		insertBtn,
	);
	renderItems(
		listWrap.querySelector('[data-section="custom"]'),
		allDocs.custom,
		filter,
		selection,
		displayInput,
		insertBtn,
	);
}

/**
 * Open the doc picker modal
 *
 * @param {import('@tiptap/core').Editor} editor Tiptap editor instance
 */
export function openDocPickerModal(editor) {
	// -----------------------------------------------------------------------
	// Build overlay + modal shell
	// -----------------------------------------------------------------------
	const overlay = document.createElement('div');
	overlay.className = 'mcc-media-modal-overlay';

	const modal = document.createElement('div');
	modal.className = 'mcc-media-modal mcc-doc-picker-modal';

	// Header
	const header = document.createElement('div');
	header.className = 'mcc-media-modal-header';
	header.innerHTML =
		'<h3>Insert Link</h3><button type="button" class="mcc-media-modal-close">&times;</button>';
	modal.appendChild(header);

	// Tabs
	const tabs = document.createElement('div');
	tabs.className = 'mcc-media-modal-tabs';
	tabs.innerHTML = `
		<button type="button" class="mcc-media-modal-tab is-active" data-tab="internal">Internal Doc</button>
		<button type="button" class="mcc-media-modal-tab" data-tab="external">External URL</button>
	`;
	modal.appendChild(tabs);

	// -----------------------------------------------------------------------
	// Internal Doc tab
	// -----------------------------------------------------------------------
	const internalContent = document.createElement('div');
	internalContent.className = 'mcc-media-modal-content';
	internalContent.dataset.tab = 'internal';
	internalContent.innerHTML = `
		<div class="mcc-doc-picker-search-wrap mcc-media-modal-field">
			<input type="search" class="mcc-doc-picker-search" placeholder="Search docs…" />
		</div>
		<div class="mcc-doc-picker-list-wrap">
			<p class="mcc-doc-picker-loading">Loading…</p>
			<div class="mcc-doc-picker-section">
				<h4 class="mcc-doc-picker-section-header">Module Documentation</h4>
				<div class="mcc-doc-picker-items" data-section="module"></div>
			</div>
			<div class="mcc-doc-picker-section">
				<h4 class="mcc-doc-picker-section-header">Development Documentation</h4>
				<div class="mcc-doc-picker-items" data-section="docs"></div>
			</div>
			<div class="mcc-doc-picker-section">
				<h4 class="mcc-doc-picker-section-header">Custom Documentation</h4>
				<div class="mcc-doc-picker-items" data-section="custom"></div>
			</div>
		</div>
		<div class="mcc-media-modal-field" style="margin-top:16px;">
			<label>Display Text</label>
			<input type="text" class="mcc-doc-picker-display-text" placeholder="Link text" />
		</div>
		<button type="button" class="button button-primary mcc-doc-picker-insert-btn mcc-media-modal-insert-btn" disabled>
			Insert Link
		</button>
	`;
	modal.appendChild(internalContent);

	// -----------------------------------------------------------------------
	// External URL tab
	// -----------------------------------------------------------------------
	const externalContent = document.createElement('div');
	externalContent.className = 'mcc-media-modal-content';
	externalContent.dataset.tab = 'external';
	externalContent.style.display = 'none';
	externalContent.innerHTML = `
		<div class="mcc-media-modal-field">
			<label>URL</label>
			<input type="url" class="mcc-doc-picker-ext-url" placeholder="https://example.com" />
		</div>
		<div class="mcc-media-modal-field">
			<label>Display Text</label>
			<input type="text" class="mcc-doc-picker-ext-display" placeholder="Link text" />
		</div>
		<button type="button" class="button button-primary mcc-doc-picker-ext-insert-btn mcc-media-modal-insert-btn" disabled>
			Insert Link
		</button>
	`;
	modal.appendChild(externalContent);

	overlay.appendChild(modal);
	document.body.appendChild(overlay);

	// -----------------------------------------------------------------------
	// Wire up close
	// -----------------------------------------------------------------------
	const close = () => overlay.remove();
	header
		.querySelector('.mcc-media-modal-close')
		.addEventListener('click', close);
	overlay.addEventListener('click', (e) => {
		if (e.target === overlay) close();
	});

	// -----------------------------------------------------------------------
	// Tab switching (reuse existing media-modal pattern)
	// -----------------------------------------------------------------------
	tabs.addEventListener('click', (e) => {
		const tab = e.target.closest('.mcc-media-modal-tab');
		if (!tab) return;
		tabs
			.querySelectorAll('.mcc-media-modal-tab')
			.forEach((t) => t.classList.remove('is-active'));
		tab.classList.add('is-active');
		modal.querySelectorAll('.mcc-media-modal-content').forEach((c) => {
			c.style.display = c.dataset.tab === tab.dataset.tab ? '' : 'none';
		});
	});

	// -----------------------------------------------------------------------
	// Internal tab wiring
	// -----------------------------------------------------------------------
	const searchInput = internalContent.querySelector('.mcc-doc-picker-search');
	const listWrap = internalContent.querySelector('.mcc-doc-picker-list-wrap');
	const loadingEl = internalContent.querySelector('.mcc-doc-picker-loading');
	const displayInput = internalContent.querySelector(
		'.mcc-doc-picker-display-text',
	);
	const insertBtn = internalContent.querySelector('.mcc-doc-picker-insert-btn');

	// Prefill display text from selected editor text.
	const { from, to } = editor.state.selection;
	const selectedText = editor.state.doc.textBetween(from, to, ' ');
	if (selectedText) {
		displayInput.value = selectedText;
	}

	/** @type {{ item: Object|null }} */
	const selection = { item: null };

	// Hide section divs until data loads.
	listWrap.querySelectorAll('.mcc-doc-picker-section').forEach((s) => {
		s.style.display = 'none';
	});

	// Fetch and render.
	fetchDocs()
		.then((allDocs) => {
			loadingEl.remove();
			listWrap.querySelectorAll('.mcc-doc-picker-section').forEach((s) => {
				s.style.display = '';
			});
			rebuildLists(allDocs, listWrap, '', selection, displayInput, insertBtn);

			searchInput.addEventListener('input', () => {
				const filter = searchInput.value.toLowerCase().trim();
				rebuildLists(
					allDocs,
					listWrap,
					filter,
					selection,
					displayInput,
					insertBtn,
				);
			});
		})
		.catch(() => {
			loadingEl.textContent = 'Failed to load docs.';
		});

	// Track manual edits so we don't keep overwriting the user's text.
	displayInput.addEventListener('input', () => {
		displayInput.dataset.autoFilled = '0';
	});

	insertBtn.addEventListener('click', () => {
		if (!selection.item) return;
		const href = selection.item.url;
		const text = displayInput.value.trim() || selection.item.title;
		insertLink(editor, href, text);
		close();
	});

	// -----------------------------------------------------------------------
	// External tab wiring
	// -----------------------------------------------------------------------
	const extUrl = externalContent.querySelector('.mcc-doc-picker-ext-url');
	const extDisplay = externalContent.querySelector(
		'.mcc-doc-picker-ext-display',
	);
	const extInsertBtn = externalContent.querySelector(
		'.mcc-doc-picker-ext-insert-btn',
	);

	// Prefill external display text too.
	if (selectedText) {
		extDisplay.value = selectedText;
	}

	// Prefill URL if there is already a link at the cursor.
	const existingHref = editor.getAttributes('link').href;
	if (existingHref) {
		extUrl.value = existingHref;
		extInsertBtn.disabled = false;
	}

	extUrl.addEventListener('input', () => {
		extInsertBtn.disabled = !extUrl.value.trim();
	});

	extInsertBtn.addEventListener('click', () => {
		const href = extUrl.value.trim();
		if (!href) return;
		const text = extDisplay.value.trim();
		insertLink(editor, href, text);
		close();
	});
}

/**
 * Insert (or update) a link in the editor
 *
 * When text is selected the link mark is applied to the selection.
 * When the cursor is a caret and display text is provided the text is
 * inserted first then the link mark is applied to the new text.
 *
 * @param {import('@tiptap/core').Editor} editor  Tiptap editor instance
 * @param {string}                        href    Link URL
 * @param {string}                        text    Display text (may be empty)
 */
function insertLink(editor, href, text) {
	const { from, to } = editor.state.selection;
	const hasSelection = from !== to;

	if (hasSelection) {
		// Apply link to selected text.
		editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
	} else if (text) {
		// Insert plain text first, then select it and apply the link mark.
		// Avoids HTML insertion which causes tiptap-markdown to double-encode
		// ampersands in URLs (& → &amp; in markdown → &amp;amp; in rendered HTML).
		editor.chain().focus().insertContent(text).run();
		const endPos = editor.state.selection.from;
		const startPos = endPos - text.length;
		editor
			.chain()
			.focus()
			.setTextSelection({ from: startPos, to: endPos })
			.setLink({ href })
			.run();
	} else {
		// Fallback: just set the link mark at cursor.
		editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
	}
}
