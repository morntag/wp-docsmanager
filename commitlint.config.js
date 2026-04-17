module.exports = {
	extends: ['@commitlint/config-conventional'],
	rules: {
		'type-enum': [
			2,
			'always',
			[
				'feat',
				'fix',
				'docs',
				'chore',
				'ci',
				'refactor',
				'style',
				'agent',
				'wip',
			],
		],
		'subject-case': [2, 'always', 'lower-case'],
		'scope-empty': [0, 'never'],
		'header-max-length': [2, 'always', 100],
	},
};
