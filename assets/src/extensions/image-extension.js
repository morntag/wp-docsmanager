import Image from '@tiptap/extension-image';

const CustomImage = Image.configure({
	inline: false,
	allowBase64: false,
});

export default CustomImage;
