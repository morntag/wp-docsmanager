import { Node } from '@tiptap/core';

const Iframe = Node.create({
	name: 'iframe',
	group: 'block',
	atom: true,

	addAttributes() {
		return {
			src: { default: null },
			width: { default: '100%' },
			height: { default: '315' },
			frameborder: { default: '0' },
			allowfullscreen: { default: true },
			allow: { default: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' },
		};
	},

	parseHTML() {
		return [{ tag: 'iframe' }];
	},

	renderHTML({ HTMLAttributes }) {
		return [
			'div',
			{ class: 'iframe-wrapper' },
			['iframe', { ...HTMLAttributes, allowfullscreen: '' }],
		];
	},

	addCommands() {
		return {
			setIframe:
				(attrs) =>
				({ commands }) => {
					return commands.insertContent({
						type: this.name,
						attrs,
					});
				},
		};
	},
});

export default Iframe;
