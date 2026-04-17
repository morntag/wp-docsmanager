/**
 * Media Modal - Image & Video insertion for Tiptap editor
 *
 * Provides modals with Upload (WP Media Library) and Link (URL) tabs
 * for inserting images and videos into the documentation editor.
 */

/**
 * Parse a video URL and return embed info
 *
 * @param {string} url Video URL
 * @return {{ type: string, src: string }|null} Embed info or null
 */
function parseVideoUrl(url) {
	if (!url) return null;

	// YouTube: youtube.com/watch?v=ID or youtu.be/ID
	let match = url.match(
		/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
	);
	if (match) {
		return {
			type: 'iframe',
			src: `https://www.youtube.com/embed/${match[1]}`,
		};
	}

	// Vimeo: vimeo.com/ID
	match = url.match(/vimeo\.com\/(\d+)/);
	if (match) {
		return {
			type: 'iframe',
			src: `https://player.vimeo.com/video/${match[1]}`,
		};
	}

	// Direct video file
	if (/\.(mp4|webm|ogg)(\?.*)?$/i.test(url)) {
		return { type: 'video', src: url };
	}

	return null;
}

/**
 * Create the modal DOM structure
 *
 * @param {string} title Modal title
 * @param {string} mediaType 'image' or 'video'
 * @return {HTMLElement} Modal overlay element
 */
function createModal(title, mediaType) {
	const overlay = document.createElement('div');
	overlay.className = 'mcc-media-modal-overlay';

	const modal = document.createElement('div');
	modal.className = 'mcc-media-modal';

	// Header
	const header = document.createElement('div');
	header.className = 'mcc-media-modal-header';
	header.innerHTML = `<h3>${title}</h3><button type="button" class="mcc-media-modal-close">&times;</button>`;
	modal.appendChild(header);

	// Tabs
	const tabs = document.createElement('div');
	tabs.className = 'mcc-media-modal-tabs';
	tabs.innerHTML = `
		<button type="button" class="mcc-media-modal-tab is-active" data-tab="upload">Upload</button>
		<button type="button" class="mcc-media-modal-tab" data-tab="link">Link</button>
	`;
	modal.appendChild(tabs);

	// Upload tab content
	const uploadContent = document.createElement('div');
	uploadContent.className = 'mcc-media-modal-content';
	uploadContent.dataset.tab = 'upload';
	uploadContent.innerHTML = `
		<p>Select from the WordPress Media Library:</p>
		<button type="button" class="button button-primary mcc-media-modal-upload-btn">
			Open Media Library
		</button>
	`;
	modal.appendChild(uploadContent);

	// Link tab content
	const linkContent = document.createElement('div');
	linkContent.className = 'mcc-media-modal-content';
	linkContent.dataset.tab = 'link';
	linkContent.style.display = 'none';

	const placeholder =
		mediaType === 'image'
			? 'https://example.com/image.jpg'
			: 'https://www.youtube.com/watch?v=...';
	linkContent.innerHTML = `
		<div class="mcc-media-modal-field">
			<label>URL</label>
			<input type="url" class="mcc-media-modal-url" placeholder="${placeholder}" />
		</div>
		${
			mediaType === 'image'
				? `
		<div class="mcc-media-modal-field">
			<label>Alt Text</label>
			<input type="text" class="mcc-media-modal-alt" placeholder="Describe the image" />
		</div>
		`
				: ''
		}
		<div class="mcc-media-modal-preview"></div>
		<button type="button" class="button button-primary mcc-media-modal-insert-btn" disabled>
			Insert
		</button>
	`;
	modal.appendChild(linkContent);

	overlay.appendChild(modal);

	// Tab switching
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

	// Close modal
	const close = () => overlay.remove();
	header
		.querySelector('.mcc-media-modal-close')
		.addEventListener('click', close);
	overlay.addEventListener('click', (e) => {
		if (e.target === overlay) close();
	});

	return overlay;
}

/**
 * Open the image insertion modal
 *
 * @param {import('@tiptap/core').Editor} editor Tiptap editor instance
 */
export function openImageModal(editor) {
	const overlay = createModal('Insert Image', 'image');
	document.body.appendChild(overlay);

	const urlInput = overlay.querySelector('.mcc-media-modal-url');
	const altInput = overlay.querySelector('.mcc-media-modal-alt');
	const preview = overlay.querySelector('.mcc-media-modal-preview');
	const insertBtn = overlay.querySelector('.mcc-media-modal-insert-btn');
	const uploadBtn = overlay.querySelector('.mcc-media-modal-upload-btn');

	// URL input preview
	if (urlInput) {
		urlInput.addEventListener('input', () => {
			const url = urlInput.value.trim();
			if (url) {
				preview.innerHTML = `<img src="${url}" alt="Preview" style="max-width:100%;max-height:200px;" />`;
				insertBtn.disabled = false;
			} else {
				preview.innerHTML = '';
				insertBtn.disabled = true;
			}
		});
	}

	// Insert from URL
	if (insertBtn) {
		insertBtn.addEventListener('click', () => {
			const src = urlInput.value.trim();
			const alt = altInput ? altInput.value.trim() : '';
			if (src) {
				editor.chain().focus().setImage({ src, alt }).run();
				overlay.remove();
			}
		});
	}

	// WP Media Library
	if (uploadBtn) {
		uploadBtn.addEventListener('click', () => {
			const frame = wp.media({
				title: 'Select Image',
				library: { type: 'image' },
				multiple: false,
			});
			frame.on('select', () => {
				const attachment = frame.state().get('selection').first().toJSON();
				editor
					.chain()
					.focus()
					.setImage({ src: attachment.url, alt: attachment.alt || '' })
					.run();
				overlay.remove();
			});
			frame.open();
		});
	}
}

/**
 * Open the video insertion modal
 *
 * @param {import('@tiptap/core').Editor} editor Tiptap editor instance
 */
export function openVideoModal(editor) {
	const overlay = createModal('Insert Video', 'video');
	document.body.appendChild(overlay);

	const urlInput = overlay.querySelector('.mcc-media-modal-url');
	const preview = overlay.querySelector('.mcc-media-modal-preview');
	const insertBtn = overlay.querySelector('.mcc-media-modal-insert-btn');
	const uploadBtn = overlay.querySelector('.mcc-media-modal-upload-btn');

	// URL input preview
	if (urlInput) {
		urlInput.addEventListener('input', () => {
			const url = urlInput.value.trim();
			const parsed = parseVideoUrl(url);
			if (parsed) {
				if (parsed.type === 'iframe') {
					preview.innerHTML = `<iframe src="${parsed.src}" width="100%" height="200" frameborder="0" allowfullscreen></iframe>`;
				} else {
					preview.innerHTML = `<video src="${parsed.src}" controls style="max-width:100%;max-height:200px;"></video>`;
				}
				insertBtn.disabled = false;
			} else if (url) {
				preview.innerHTML =
					'<p style="color:#999;">Paste a YouTube, Vimeo, or direct video URL (.mp4, .webm, .ogg)</p>';
				insertBtn.disabled = true;
			} else {
				preview.innerHTML = '';
				insertBtn.disabled = true;
			}
		});
	}

	// Insert from URL
	if (insertBtn) {
		insertBtn.addEventListener('click', () => {
			const url = urlInput.value.trim();
			const parsed = parseVideoUrl(url);
			if (parsed) {
				if (parsed.type === 'iframe') {
					editor.commands.setIframe({ src: parsed.src });
				} else {
					editor.commands.setVideo({ src: parsed.src });
				}
				overlay.remove();
			}
		});
	}

	// WP Media Library
	if (uploadBtn) {
		uploadBtn.addEventListener('click', () => {
			const frame = wp.media({
				title: 'Select Video',
				library: { type: 'video' },
				multiple: false,
			});
			frame.on('select', () => {
				const attachment = frame.state().get('selection').first().toJSON();
				editor.commands.setVideo({ src: attachment.url });
				overlay.remove();
			});
			frame.open();
		});
	}
}
