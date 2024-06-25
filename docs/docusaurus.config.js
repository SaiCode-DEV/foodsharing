const lightCodeTheme = require('prism-react-renderer/themes/github');
const darkCodeTheme = require('prism-react-renderer/themes/dracula');
const simplePlantUML = require("@akebifiky/remark-simple-plantuml");

module.exports = {
  title: 'Foodsharing DEV',
  tagline: 'Together for more code rescue',
  url: 'https://beta.foodsharing.de',
  baseUrl: '/',
  onBrokenLinks: 'throw',
  onBrokenMarkdownLinks: 'warn',
  favicon: 'img/foodsharing_fork.svg',

  organizationName: 'foodsharing-dev',
  projectName: 'foodsharing',
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },
  presets: [
    [
      '@docusaurus/preset-classic',
      {
        docs: {
          sidebarPath: require.resolve('./sidebars.js'),
          remarkPlugins: [simplePlantUML],
          lastVersion: 'current',
          versions: {
            current: {
              label: 'Current',
              path: 'current',
            },
          },
          editUrl: 'https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/docs/',
        },
        theme: {
          customCss: require.resolve('./src/css/custom.css'),
        },
      },
    ],
  ],
  plugins: [
    [
      '@docusaurus/plugin-content-docs',
      {
        id: 'docs-api',
        path: 'docs-api',
        routeBasePath: 'docs-api',
        sidebarPath: require.resolve('./sidebars.api.js'),
        docLayoutComponent: "@theme/DocPage",
        docItemComponent: "@theme/ApiItem",
      },
    ],
    [
      "docusaurus-plugin-openapi-docs",
      {
        id: "api-operation",
        docsPluginId: "classic",
        config: {
          api: {
            specPath: "data/api_dump.json",
            outputDir: "docs-api",
            sidebarOptions: {
              groupPathsBy: "tag",
              categoryLinkSource: "tag"
            },
          }
        }
      },
    ],
  ],
  themes: ["docusaurus-theme-openapi-docs"],
  themeConfig: {
    navbar: {
      title: 'Foodsharing',
      logo: {
        alt: 'Foodsharing LOGO',
        src: 'img/foodsharing_fork.svg',
      },
      items: [
        {
          type: 'doc',
          docId: 'intro',
          label: 'Guide',
          position: 'left',
        },
        {
          to: "docs-api/foodsharing-api",
          label: "Rest API",
          position: "left",
        },
        {
          type: 'docsVersionDropdown',
          position: 'left',
        },
        {
          href: 'https://devblog.foodsharing.de',
          label: 'Dev Blog',
          position: 'right'
        },
        {
          href: 'https://gitlab.com/foodsharing-dev/foodsharing',
          label: 'Gitlab',
          position: 'right',
        },
        {
          href: 'https://slackin.yunity.org',
          label: 'Slack',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Foodsharing Plattforms',
          items: [
            {
              label: 'Foodsharing',
              href: 'https://www.foodsharing.de',
            },
            {
              label: 'Foodsharing (Beta)',
              href: 'https://beta.foodsharing.de',
            },
            {
              label: 'Foodsharing Wiki',
              href: 'https://wiki.foodsharing.de',
            },
          ],
        },
        {
          title: 'More',
          items: [
            {
              label: 'Dev Blog',
              href: 'https://devblog.foodsharing.de',
            },
            {
              label: 'Slack (Chat)',
              href: 'https://slackin.yunity.org',
            },
          ],
        },
      ],
      copyright: `Foodsharing e.V. Built with Docusaurus.`,
    },
    tableOfContents: {
      minHeadingLevel: 2,
      maxHeadingLevel: 5,
    },
    prism: {
      theme: lightCodeTheme,
      darkTheme: darkCodeTheme,
      additionalLanguages: ['php', 'bash', 'json'],
    },
  },
};
