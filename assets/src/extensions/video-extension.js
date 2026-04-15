import { Node } from '@tiptap/core';

const Video = Node.create({
	name: 'video',
	group: 'block',
	atom: true,

	addAttributes() {
		return {
			src: { default: null },
			controls: { default: true },
			width: { default: '100%' },
		};
	},

	parseHTML() {
		return [{ tag: 'video' }];
	},

	renderHTML({ HTMLAttributes }) {
		return ['video', { ...HTMLAttributes, controls: '' }];
	},

	addCommands() {
		return {
			setVideo:
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

export default Video;
