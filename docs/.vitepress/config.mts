import { defineConfig } from 'vitepress'
import { withPwa } from '@vite-pwa/vitepress'
import { generateSidebar } from 'vitepress-sidebar';
import type { VitePressSidebarOptions } from 'vitepress-sidebar/types';
import { generateI18nLocale, generateI18nSearch } from 'vitepress-i18n';

import MarkdownItImplicitFigures from "markdown-it-implicit-figures";
import MarkdownItPlantuml from "markdown-it-plantuml";
import { useSidebar } from 'vitepress-openapi'

import spec from '../data/api_dump.json' assert { type: 'json' }

import { join } from "node:path";
import { promises as fs } from 'node:fs'
import type { LanguageInput, RawGrammar } from 'shiki'
const loadSyntax = async (file: string, name: string, alias: string = name): Promise<LanguageInput> => {
  const src = await fs.readFile(join(__dirname, file))
  const grammar: RawGrammar = JSON.parse(src.toString())
  return { name, aliases: [name, alias], ...grammar }
}

const apiSidebar = useSidebar({ spec })

const defaultLocale = 'en'

const commonSidebarConfig: VitePressSidebarOptions = {
  documentRootPath: 'docs',
  collapsed: true,
  capitalizeFirst: true,
  hyphenToSpace: true,
  excludeByGlobPattern: ['vitepress'],
  useTitleFromFrontmatter: true,
  sortMenusByFrontmatterOrder: true,
  manualSortFileNameByPriority: [
    'intro.md',
    'getting-started.md',
    'it-tasks.md',
    'contributing',
    'backend',
    'frontend',
    'deployment',
    'glossary',
    'permissions',
  ]
}

// German dev docs dropped in !4286 due to lack of maintenance.
const defineSupportLocales = [
  { label: 'en', translateLocale: 'en' }/* ,
  { label: 'de', translateLocale: 'de' } */
]
const editLinkPattern = 'https://gitlab.com/foodsharing-dev/foodsharing/-/tree/devdocs-rewrite/docs/:path';

const sidebar = generateSidebar([
  ...['en'/*,  'de' */].map((lang) => {
    return {
      ...commonSidebarConfig,
      documentRootPath: `/${lang}`,
      resolvePath: defaultLocale === lang ? '/' : `/${lang}/`,
      ...(defaultLocale === lang ? {} : { basePath: `/${lang}/` })
    };
  })
]);
(sidebar as Record<string, any>)['/api/'] = [
  {
    text: 'API Reference',
    link: '/api/',
  },

  ...apiSidebar.generateSidebarGroups().map(group => ({
    ...group,
    collapsed: true,
    items: Array.isArray(group.items)
      ? group.items.map(item => ({
          ...item,
          link: `/api${item.link}`,
        }))
      : [],
  })),
];

// https://vitepress.dev/reference/site-config
export default withPwa(defineConfig({
  title: "foodsharing Devdocs",
  description: "Developer documentation for foodsharing",
  cleanUrls: true,
  metaChunk: true,
  ignoreDeadLinks: true,
  head: [
    ['link', { rel: 'icon', href: '/img/favicon.ico' }],
    ['meta', { name: 'keywords', content: 'foodsharing, Devdocs, Docs, API, Contribute, Dev, Development' }],
    ['meta', { property: 'og:image', content: 'https://devdocs.foodsharing.network/img/FS_Schriftzug_gw.svg' }],
    ['meta', { property: 'og:title', content: 'foodsharing Devdocs' }],
    ['meta', { property: 'og:description', content: 'Write Code. Save Food. Empowering foodsharing with Every Line' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:url', content: 'https://devdocs.foodsharing.de' }],
  ],
  rewrites: {
    'en/:rest*': ':rest*'
  },
  markdown: {

    languages: [
      // https://github.com/mechatroner/vscode_rainbow_csv/blob/master/syntaxes/csv.tmLanguage.json
      await loadSyntax('syntaxes/csv.tmLanguage.json', 'csv'),
      // https://github.com/mechatroner/vscode_rainbow_csv/blob/master/syntaxes/csv.tmLanguage.json
      await loadSyntax('syntaxes/scsv.tmLanguage.json', 'csvs'),
      // https://github.com/Huachao/vscode-restclient/blob/master/syntaxes/http.tmLanguage.json
      await loadSyntax('syntaxes/http.tmLanguage.json', 'rest'),
      await loadSyntax('syntaxes/log.tmLanguage.json', 'log'),
    ],

    config: (md) => {
      // https://www.npmjs.com/package/markdown-it-implicit-figures
      md.use(MarkdownItImplicitFigures, {
        figcaption: true,
      });
      md.use(MarkdownItPlantuml, {
        //imageFormat: 'png'
      });
      // https://github.com/gmunguia/markdown-it-plantuml/issues/35
      md.use(MarkdownItPlantuml, {
        openMarker: "```plantuml",
        closeMarker: "```",
        diagramName: "uml",
        imageFormat: "svg",
      });
      // Textik https://textik.com/
      md.use(MarkdownItPlantuml, {
        openMarker: "```ditaa",
        closeMarker: "```",
        diagramName: "ditaa",
        imageFormat: "png",
      });
    }
  },
  themeConfig: {
    logo: {
      light: 'img/FS_Schriftzug_gb.svg',
      dark: 'img/FS_Schriftzug_gw.svg',
      alt: 'foodsharing logo'
    },
    siteTitle: false,
    // https://vitepress.dev/reference/default-theme-config
    sidebar: sidebar,
    socialLinks: [
      {
        icon: { svg: '<svg xmlns="http://www.w3.org/2000/svg" width="34.72" height="32" viewBox="0 0 256 236"><path fill="#E24329" d="m128.075 236.075l47.104-144.97H80.97z"/><path fill="#FC6D26" d="M128.075 236.074L80.97 91.104H14.956z"/><path fill="#FCA326" d="M14.956 91.104L.642 135.16a9.75 9.75 0 0 0 3.542 10.903l123.891 90.012z"/><path fill="#E24329" d="M14.956 91.105H80.97L52.601 3.79c-1.46-4.493-7.816-4.492-9.275 0z"/><path fill="#FC6D26" d="m128.075 236.074l47.104-144.97h66.015z"/><path fill="#FCA326" d="m241.194 91.104l14.314 44.056a9.75 9.75 0 0 1-3.543 10.903l-123.89 90.012z"/><path fill="#E24329" d="M241.194 91.105h-66.015l28.37-87.315c1.46-4.493 7.816-4.492 9.275 0z"/></svg>' },
        link: 'https://gitlab.com/foodsharing-dev/foodsharing'
      },
      {
        icon: { svg: '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 256 256"><path fill="#E01E5A" d="M53.841 161.32c0 14.832-11.987 26.82-26.819 26.82c-14.832 0-26.819-11.988-26.819-26.82c0-14.831 11.987-26.818 26.82-26.818H53.84zm13.41 0c0-14.831 11.987-26.818 26.819-26.818c14.832 0 26.819 11.987 26.819 26.819v67.047c0 14.832-11.987 26.82-26.82 26.82c-14.83 0-26.818-11.988-26.818-26.82z"/><path fill="#36C5F0" d="M94.07 53.638c-14.832 0-26.82-11.987-26.82-26.819C67.25 11.987 79.239 0 94.07 0s26.819 11.987 26.819 26.819v26.82zm0 13.613c14.832 0 26.819 11.987 26.819 26.819c0 14.832-11.987 26.819-26.82 26.819H26.82C11.987 120.889 0 108.902 0 94.069c0-14.83 11.987-26.818 26.819-26.818z"/><path fill="#2EB67D" d="M201.55 94.07c0-14.832 11.987-26.82 26.818-26.82c14.832 0 26.82 11.988 26.82 26.82s-11.988 26.819-26.82 26.819H201.55zm-13.41 0c0 14.832-11.988 26.819-26.82 26.819c-14.831 0-26.818-11.987-26.818-26.82V26.82C134.502 11.987 146.489 0 161.32 0c14.831 0 26.819 11.987 26.819 26.819z"/><path fill="#ECB22E" d="M161.32 201.55c14.832 0 26.82 11.987 26.82 26.818c0 14.832-11.988 26.82-26.82 26.82c-14.831 0-26.818-11.988-26.818-26.82V201.55zm0-13.41c-14.831 0-26.818-11.988-26.818-26.82c0-14.831 11.987-26.818 26.819-26.818h67.25c14.832 0 26.82 11.987 26.82 26.819c0 14.831-11.988 26.819-26.82 26.819z"/></svg>' },
        link: 'https://yunity.slack.com'
      }
    ],
    search: generateI18nSearch({
      defineLocales: defineSupportLocales,
      rootLocale: defaultLocale,
      provider: 'local'
    }),
  },
  locales: generateI18nLocale({
    defineLocales: defineSupportLocales,
    rootLocale: defaultLocale,
    editLinkPattern: editLinkPattern,
    description: {
      en: 'Developer documentation for foodsharing',
      de: 'Entwicklerdokumentation für foodsharing'
    },
    themeConfig: {
      en: {
        nav: [
          {
            text: 'Guide',
            link: '/getting-started'
          },
          {
            text: 'Rest API',
            link: '/api'
          },
          {
            text: 'Wiki',
            link: 'https://wiki.foodsharing.de'
          }
        ]
      },
      de: {
        nav: [
          {
            text: 'Anleitung',
            link: '/de/getting-started'
          },
          {
            text: 'Rest API',
            link: '/api'
          },
          {
            text: 'Wiki',
            link: 'https://wiki.foodsharing.de'
          }
        ]
      },
    }
  }),
  pwa: {
    registerType: 'autoUpdate',
    includeAssets: ['favicon.ico'],
    manifest: {
      name: "foodsharing Devdocs",
      short_name: "FS Devdocs",
      theme_color: "#64AE24",
      background_color: "#1B1B1F",
    },
    pwaAssets: {
      disabled: true,
    },
    workbox: {
      globPatterns: ['*/.{css,js,html,svg,png,ico,txt,woff2}'],
    },
    experimental: {
      includeAllowlist: true,
    },
  }
}))
