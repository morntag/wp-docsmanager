/**
 * MCC Docs Editor - Tiptap-based Markdown Editor
 *
 * Provides a WYSIWYG markdown editor using Tiptap with:
 * - Starter kit extensions (bold, italic, headings, lists, code, blockquote)
 * - Link support
 * - Table support (insert, add row/column)
 * - Markdown import/export via tiptap-markdown
 * - Autosave to localStorage
 * - Toolbar with common formatting options
 *
 * @package Mcc
 * @subpackage Modules\DocsManager
 */

import { Editor } from '@tiptap/core';
import Link from '@tiptap/extension-link';
import Table from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import TextAlign from '@tiptap/extension-text-align';
import StarterKit from '@tiptap/starter-kit';
import { Markdown } from 'tiptap-markdown';
import { openDocPickerModal } from './components/doc-picker-modal.js';
import { openImageModal, openVideoModal } from './components/media-modal.js';
import Iframe from './extensions/iframe-extension.js';
import CustomImage from './extensions/image-extension.js';
import Video from './extensions/video-extension.js';

/**
 * Debounce utility
 *
 * @param {Function} func Function to debounce
 * @param {number} wait Wait time in milliseconds
 * @return {Function} Debounced function
 */
function debounce(func, wait) {
	let timeout;
	return function executedFunction(...args) {
		const later = () => {
			clearTimeout(timeout);
			func(...args);
		};
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
	};
}

/**
 * MCC Docs Editor class
 */
class MorntagDocsEditor {
	/**
	 * Constructor
	 */
	constructor() {
		this.editor = null;
		this.toolbar = null;
		this.tabs = null;
		this.editorContainer = null;
		this.markdownTextarea = null;
		this.mode = 'visual';
		this.config = null;
		this.autosaveDebounced = null;
	}

	/**
	 * Initialize the editor
	 *
	 * @param {Object} config Configuration object
	 * @param {HTMLElement} config.element Container element for the editor
	 * @param {string} config.content Initial markdown content
	 * @param {Function} config.onChange Callback when content changes
	 * @param {Function} config.onSave Callback for autosave
	 * @return {MorntagDocsEditor} Editor instance
	 */
	init(config) {
		if (!config.element) {
			console.error('MorntagDocsEditor: element is required');
			return this;
		}

		this.config = {
			element: config.element,
			content: config.content || '',
			onChange: config.onChange || (() => {}),
			onSave: config.onSave || (() => {}),
		};

		// Create autosave debounced function
		this.autosaveDebounced = debounce((markdown) => {
			this.saveToLocalStorage(markdown);
			this.config.onSave(markdown);
		}, 1000);

		// Create tabs (Visual / Markdown)
		this.createTabs();

		// Create toolbar
		this.createToolbar();

		// Create editor container
		const editorContainer = document.createElement('div');
		editorContainer.className = 'mcc-docs-editor-content';
		this.editorContainer = editorContainer;
		this.config.element.appendChild(editorContainer);

		// Create raw markdown textarea (hidden by default)
		this.markdownTextarea = document.createElement('textarea');
		this.markdownTextarea.className = 'mcc-docs-editor-markdown';
		this.markdownTextarea.spellcheck = false;
		this.markdownTextarea.style.display = 'none';
		this.markdownTextarea.addEventListener('input', () => {
			const markdown = this.markdownTextarea.value;
			this.config.onChange(markdown);
			this.autosaveDebounced(markdown);
		});
		this.config.element.appendChild(this.markdownTextarea);

		// Initialize Tiptap editor
		this.editor = new Editor({
			element: editorContainer,
			extensions: [
				StarterKit.configure({
					heading: {
						levels: [1, 2, 3],
					},
				}),
				Link.configure({
					openOnClick: false,
					HTMLAttributes: {
						target: '_blank',
						rel: 'noopener noreferrer',
					},
				}),
				Table.configure({
					resizable: false,
				}),
				TableRow,
				TableHeader,
				TableCell,
				Markdown.configure({
					html: true,
					transformPastedText: true,
					transformCopiedText: true,
				}),
				TextAlign.configure({
					types: ['heading', 'paragraph'],
				}),
				CustomImage,
				Video,
				Iframe,
			],
			content: this.config.content,
			onUpdate: ({ editor }) => {
				const markdown = editor.storage.markdown.getMarkdown();
				this.config.onChange(markdown);
				this.autosaveDebounced(markdown);
				this.updateToolbarState();
			},
			onSelectionUpdate: () => {
				this.updateToolbarState();
			},
		});

		// Initial toolbar state
		this.updateToolbarState();

		return this;
	}

	/**
	 * Create Visual / Markdown tab bar
	 */
	createTabs() {
		this.tabs = document.createElement('div');
		this.tabs.className = 'mcc-docs-editor-tabs';

		const visualBtn = document.createElement('button');
		visualBtn.type = 'button';
		visualBtn.className = 'mcc-docs-editor-tab is-active';
		visualBtn.dataset.mode = 'visual';
		visualBtn.textContent = 'Visual';

		const markdownBtn = document.createElement('button');
		markdownBtn.type = 'button';
		markdownBtn.className = 'mcc-docs-editor-tab';
		markdownBtn.dataset.mode = 'markdown';
		markdownBtn.textContent = 'Markdown';

		[visualBtn, markdownBtn].forEach((btn) => {
			btn.addEventListener('click', (e) => {
				e.preventDefault();
				this.setMode(btn.dataset.mode);
			});
			this.tabs.appendChild(btn);
		});

		this.config.element.appendChild(this.tabs);
	}

	/**
	 * Switch between 'visual' and 'markdown' modes
	 *
	 * @param {string} mode 'visual' or 'markdown'
	 */
	setMode(mode) {
		if (mode === this.mode || !this.editor) {
			return;
		}

		if (mode === 'markdown') {
			const markdown = this.editor.storage.markdown.getMarkdown();
			this.markdownTextarea.value = markdown;
			this.editorContainer.style.display = 'none';
			this.toolbar.style.display = 'none';
			this.markdownTextarea.style.display = 'block';
		} else {
			const markdown = this.markdownTextarea.value;
			this.editor.commands.setContent(markdown);
			this.markdownTextarea.style.display = 'none';
			this.editorContainer.style.display = '';
			this.toolbar.style.display = '';
		}

		this.mode = mode;

		if (this.tabs) {
			this.tabs.querySelectorAll('.mcc-docs-editor-tab').forEach((btn) => {
				btn.classList.toggle('is-active', btn.dataset.mode === mode);
			});
		}
	}

	/**
	 * Create and render the toolbar
	 */
	createToolbar() {
		this.toolbar = document.createElement('div');
		this.toolbar.className = 'mcc-docs-editor-toolbar';

		const buttons = [
			{
				title: 'Bold',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>',
				command: 'bold',
				action: () => this.editor.chain().focus().toggleBold().run(),
			},
			{
				title: 'Italic',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>',
				command: 'italic',
				action: () => this.editor.chain().focus().toggleItalic().run(),
			},
			{
				title: 'Strikethrough',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4H9a3 3 0 0 0-3 3c0 2 1.5 3 3 3"/><line x1="4" y1="12" x2="20" y2="12"/><path d="M15 12c1.5 0 3 1 3 3a3 3 0 0 1-3 3H8"/></svg>',
				command: 'strike',
				action: () => this.editor.chain().focus().toggleStrike().run(),
			},
			{ type: 'separator' },
			{
				title: 'Align Left',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>',
				command: 'left',
				action: () => this.editor.chain().focus().setTextAlign('left').run(),
			},
			{
				title: 'Align Center',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>',
				command: 'center',
				action: () => this.editor.chain().focus().setTextAlign('center').run(),
			},
			{
				title: 'Align Right',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>',
				command: 'right',
				action: () => this.editor.chain().focus().setTextAlign('right').run(),
			},
			{ type: 'separator' },
			{
				title: 'Heading 1',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h8"/><path d="M4 4v16"/><path d="M12 4v16"/><path d="M17 12l3-2v10"/></svg>',
				command: 'heading',
				level: 1,
				action: () =>
					this.editor.chain().focus().toggleHeading({ level: 1 }).run(),
			},
			{
				title: 'Heading 2',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h8"/><path d="M4 4v16"/><path d="M12 4v16"/><path d="M21 18h-4c0-4 4-3 4-6 0-1.5-2-2.5-4-1"/></svg>',
				command: 'heading',
				level: 2,
				action: () =>
					this.editor.chain().focus().toggleHeading({ level: 2 }).run(),
			},
			{
				title: 'Heading 3',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h8"/><path d="M4 4v16"/><path d="M12 4v16"/><path d="M17.5 10.5c1.7-1 3.5 0 3.5 1.5a2 2 0 0 1-2 2"/><path d="M17 17.5c2 1.5 4 .3 4-1.5a2 2 0 0 0-2-2"/></svg>',
				command: 'heading',
				level: 3,
				action: () =>
					this.editor.chain().focus().toggleHeading({ level: 3 }).run(),
			},
			{ type: 'separator' },
			{
				title: 'Bullet List',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4.5" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1" fill="currentColor" stroke="none"/></svg>',
				command: 'bulletList',
				action: () => this.editor.chain().focus().toggleBulletList().run(),
			},
			{
				title: 'Ordered List',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><text x="3" y="7.5" font-size="7" font-weight="600" fill="currentColor" stroke="none" font-family="system-ui">1</text><text x="3" y="13.5" font-size="7" font-weight="600" fill="currentColor" stroke="none" font-family="system-ui">2</text><text x="3" y="19.5" font-size="7" font-weight="600" fill="currentColor" stroke="none" font-family="system-ui">3</text></svg>',
				command: 'orderedList',
				action: () => this.editor.chain().focus().toggleOrderedList().run(),
			},
			{ type: 'separator' },
			{
				title: 'Blockquote',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>',
				command: 'blockquote',
				action: () => this.editor.chain().focus().toggleBlockquote().run(),
			},
			{
				title: 'Code Block',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
				command: 'codeBlock',
				action: () => this.editor.chain().focus().toggleCodeBlock().run(),
			},
			{ type: 'separator' },
			{
				title: 'Link',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
				command: 'link',
				action: () => this.toggleLink(),
			},
			{
				title: 'Horizontal Rule',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="2" y1="12" x2="22" y2="12"/></svg>',
				command: 'horizontalRule',
				action: () => this.editor.chain().focus().setHorizontalRule().run(),
			},
			{ type: 'separator' },
			{
				title: 'Image',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
				command: 'image',
				action: () => openImageModal(this.editor),
			},
			{
				title: 'Video',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="15" height="16" rx="2"/><path d="M17 10l5-3v10l-5-3z"/></svg>',
				command: 'video',
				action: () => openVideoModal(this.editor),
			},
			{ type: 'separator' },
			{
				title: 'Insert Table',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/><line x1="15" y1="9" x2="15" y2="21"/></svg>',
				command: 'insertTable',
				action: () => this.insertTable(),
			},
			{
				title: 'Add Row',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="11" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="3" x2="9" y2="14"/><line x1="15" y1="3" x2="15" y2="14"/><line x1="12" y1="18" x2="12" y2="24"/><line x1="9" y1="21" x2="15" y2="21"/></svg>',
				command: 'addRowAfter',
				action: () => this.editor.chain().focus().addRowAfter().run(),
			},
			{
				title: 'Add Column',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="11" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="3" y1="9" x2="14" y2="9"/><line x1="3" y1="15" x2="14" y2="15"/><line x1="18" y1="12" x2="24" y2="12"/><line x1="21" y1="9" x2="21" y2="15"/></svg>',
				command: 'addColumnAfter',
				action: () => this.editor.chain().focus().addColumnAfter().run(),
			},
			{ type: 'separator' },
			{
				title: 'Delete Row',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="11" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="3" x2="9" y2="14"/><line x1="15" y1="3" x2="15" y2="14"/><line x1="9" y1="18" x2="15" y2="24"/><line x1="15" y1="18" x2="9" y2="24"/></svg>',
				command: 'deleteRow',
				action: () => this.editor.chain().focus().deleteRow().run(),
			},
			{
				title: 'Delete Column',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="11" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="3" y1="9" x2="14" y2="9"/><line x1="3" y1="15" x2="14" y2="15"/><line x1="17" y1="15" x2="23" y2="21"/><line x1="23" y1="15" x2="17" y2="21"/></svg>',
				command: 'deleteColumn',
				action: () => this.editor.chain().focus().deleteColumn().run(),
			},
			{
				title: 'Delete Table',
				icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/><line x1="15" y1="9" x2="15" y2="21"/><g transform="translate(12,12)"><line x1="-4" y1="-4" x2="4" y2="4" stroke-width="2.5"/><line x1="4" y1="-4" x2="-4" y2="4" stroke-width="2.5"/></g></svg>',
				command: 'deleteTable',
				action: () => this.editor.chain().focus().deleteTable().run(),
			},
		];

		buttons.forEach((btn) => {
			if (btn.type === 'separator') {
				const separator = document.createElement('span');
				separator.className = 'mcc-docs-editor-toolbar-separator';
				this.toolbar.appendChild(separator);
			} else {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'mcc-docs-editor-toolbar-btn';
				button.title = btn.title;
				button.innerHTML = btn.icon;
				button.dataset.command = btn.command;
				if (btn.level) {
					button.dataset.level = btn.level;
				}
				button.addEventListener('click', (e) => {
					e.preventDefault();
					btn.action();
				});
				this.toolbar.appendChild(button);
			}
		});

		this.config.element.appendChild(this.toolbar);
	}

	/**
	 * Update toolbar button states based on current editor state
	 */
	updateToolbarState() {
		if (!this.editor || !this.toolbar) {
			return;
		}

		const isInsideTable = this.editor.isActive('table');

		const buttons = this.toolbar.querySelectorAll(
			'.mcc-docs-editor-toolbar-btn',
		);
		buttons.forEach((btn) => {
			const command = btn.dataset.command;
			let isActive = false;

			if (command === 'heading' && btn.dataset.level) {
				isActive = this.editor.isActive('heading', {
					level: parseInt(btn.dataset.level),
				});
			} else if (command === 'link') {
				isActive = this.editor.isActive('link');
			} else if (
				command === 'left' ||
				command === 'center' ||
				command === 'right'
			) {
				isActive = this.editor.isActive({ textAlign: command });
			} else {
				isActive = this.editor.isActive(command);
			}

			if (isActive) {
				btn.classList.add('is-active');
			} else {
				btn.classList.remove('is-active');
			}

			// Disable table-manipulation buttons when not inside a table
			if (
				command === 'addRowAfter' ||
				command === 'addColumnAfter' ||
				command === 'deleteRow' ||
				command === 'deleteColumn' ||
				command === 'deleteTable'
			) {
				if (isInsideTable) {
					btn.classList.remove('is-disabled');
					btn.disabled = false;
				} else {
					btn.classList.add('is-disabled');
					btn.disabled = true;
				}
			}
		});
	}

	/**
	 * Show a modal dialog for table dimensions and insert a table
	 */
	insertTable() {
		const overlay = document.createElement('div');
		overlay.className = 'mcc-media-modal-overlay';

		overlay.innerHTML = `
			<div class="mcc-media-modal" style="width:360px">
				<div class="mcc-media-modal-header">
					<h3>Insert Table</h3>
					<button type="button" class="mcc-media-modal-close">&times;</button>
				</div>
				<div class="mcc-media-modal-content">
					<div style="display:flex;gap:16px">
						<div class="mcc-media-modal-field" style="flex:1">
							<label>Rows</label>
							<input type="number" class="mcc-table-rows" value="3" min="1" />
						</div>
						<div class="mcc-media-modal-field" style="flex:1">
							<label>Columns</label>
							<input type="number" class="mcc-table-cols" value="3" min="1" />
						</div>
					</div>
					<div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end">
						<button type="button" class="button mcc-table-cancel">Cancel</button>
						<button type="button" class="button button-primary mcc-table-insert">Insert</button>
					</div>
				</div>
			</div>
		`;

		const close = () => document.body.removeChild(overlay);

		overlay
			.querySelector('.mcc-media-modal-close')
			.addEventListener('click', close);
		overlay.querySelector('.mcc-table-cancel').addEventListener('click', close);

		overlay.addEventListener('click', (e) => {
			if (e.target === overlay) {
				close();
			}
		});

		overlay.querySelector('.mcc-table-insert').addEventListener('click', () => {
			const rows = Math.max(
				1,
				parseInt(overlay.querySelector('.mcc-table-rows').value, 10) || 3,
			);
			const cols = Math.max(
				1,
				parseInt(overlay.querySelector('.mcc-table-cols').value, 10) || 3,
			);
			close();
			this.editor
				.chain()
				.focus()
				.insertTable({ rows, cols, withHeaderRow: true })
				.run();
		});

		document.body.appendChild(overlay);
		overlay.querySelector('.mcc-table-rows').focus();
	}

	/**
	 * Toggle link — opens the doc picker modal
	 *
	 * When the cursor is already inside a link the external URL tab is
	 * pre-filled with the existing href so it can be edited or replaced.
	 * To remove a link entirely, users can open the modal and clear the
	 * URL field, or use the toolbar's active-state button to unset.
	 */
	toggleLink() {
		// If already on a link, let a second click unset it directly
		// so the toolbar toggle still feels snappy.
		if (this.editor.isActive('link')) {
			this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
			return;
		}

		openDocPickerModal(this.editor);
	}

	/**
	 * Get current content as markdown
	 *
	 * @return {string} Markdown content
	 */
	getMarkdown() {
		if (!this.editor) {
			return '';
		}
		if (this.mode === 'markdown' && this.markdownTextarea) {
			return this.markdownTextarea.value;
		}
		return this.editor.storage.markdown.getMarkdown();
	}

	/**
	 * Set content from markdown
	 *
	 * @param {string} content Markdown content
	 */
	setMarkdown(content) {
		if (!this.editor) {
			return;
		}
		this.editor.commands.setContent(content);
	}

	/**
	 * Save to localStorage
	 *
	 * @param {string} markdown Markdown content
	 */
	saveToLocalStorage(markdown) {
		const docId = this.getDocumentId();
		if (docId) {
			try {
				localStorage.setItem(`mcc-docs-draft-${docId}`, markdown);
			} catch (e) {
				console.error('MorntagDocsEditor: Failed to save to localStorage', e);
			}
		}
	}

	/**
	 * Get document ID from URL or data attribute
	 *
	 * @return {string|null} Document ID
	 */
	getDocumentId() {
		// Try to get from URL params
		const urlParams = new URLSearchParams(window.location.search);
		const postId = urlParams.get('post');
		if (postId) {
			return postId;
		}

		// Try to get from element data attribute
		if (
			this.config &&
			this.config.element &&
			this.config.element.dataset.docId
		) {
			return this.config.element.dataset.docId;
		}

		return null;
	}

	/**
	 * Destroy the editor and clean up
	 */
	destroy() {
		if (this.editor) {
			this.editor.destroy();
			this.editor = null;
		}
		if (this.toolbar && this.toolbar.parentNode) {
			this.toolbar.parentNode.removeChild(this.toolbar);
			this.toolbar = null;
		}
		if (this.tabs && this.tabs.parentNode) {
			this.tabs.parentNode.removeChild(this.tabs);
			this.tabs = null;
		}
		if (this.markdownTextarea && this.markdownTextarea.parentNode) {
			this.markdownTextarea.parentNode.removeChild(this.markdownTextarea);
			this.markdownTextarea = null;
		}
		this.editorContainer = null;
		this.config = null;
		this.autosaveDebounced = null;
	}
}

// Create singleton instance and attach to window immediately
(function () {
	const instance = new MorntagDocsEditor();

	window.MorntagDocsEditor = {
		init: function (config) {
			return instance.init(config);
		},
		getMarkdown: function () {
			return instance.getMarkdown();
		},
		setMarkdown: function (content) {
			return instance.setMarkdown(content);
		},
		setMode: function (mode) {
			return instance.setMode(mode);
		},
		destroy: function () {
			return instance.destroy();
		},
	};
})();
