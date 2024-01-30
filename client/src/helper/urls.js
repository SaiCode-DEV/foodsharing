import phoneNumbers from './phone-numbers'
// these are used for generating link-paths inside vue
// e.g. $url('profile', 15)

const urls = {
  profile: (id) => `/profile/${id}`,
  profileNotes: (fsId) => `/profile/${fsId}/notes`,
  academy: () => '/content?sub=academy',
  application: (groupId, userId) => `/?page=application&bid=${groupId}&fid=${userId}`,
  basket: (basketId) => `/essenskoerbe/${basketId}`,
  baskets: () => '/essenskoerbe',
  blog: () => '/news',
  blogAdd: () => '/blog?sub=add',
  blogEdit: (blogId) => `/blog?sub=edit&id=${blogId}`,
  blogList: () => '/blog?sub=manage',
  claims: () => '/content?sub=forderungen',
  communitiesAustria: () => '/content?sub=communitiesAustria',
  communitiesGermany: () => '/content?sub=communitiesGermany',
  communitiesSwitzerland: () => '/content?sub=communitiesSwitzerland',
  contact: () => '/content?sub=contact',
  contentEdit: () => '/content',
  contentEditEntry: (id) => `/content?a=edit&id=${id}`,
  contentNew: () => '/content?a=neu',
  conversations: (conversationId = null) => `/?page=msg${conversationId ? `&cid=${conversationId}` : ''}`,
  dashboard: () => '/?page=dashboard',
  dataprivacy: () => '/?page=legal',
  donate: () => '/unterstuetzung',
  email: () => '/?page=email',
  event: (eventId) => `/?page=event&id=${eventId}`,
  eventEdit: (eventId) => `/?page=event&id=${eventId}&sub=edit`,
  festival: () => '/content?sub=festival',
  foodsharepoint: (fspId) => `/?page=fairteiler&sub=ft&id=${fspId}`,
  foodsaverEdit: (fsId) => `/?page=foodsaver&a=edit&id=${fsId}`,
  fsstaedte: () => '/content?sub=fsstaedte',
  home: () => '/',
  imprint: () => '/impressum',
  infos: () => '/content?sub=infohub',
  infosCompany: () => '/fuer-unternehmen',
  international: () => '/content?sub=international',
  joininfo: () => '/content?sub=joininfo',
  leeretonne: () => '/content?sub=leeretonne',
  login: () => '/?page=login',
  logout: () => {
    const url = new URL(window.location.href)
    return '/?page=logout&ref=' + encodeURIComponent(url.pathname + url.search)
  },
  mailbox: (mailboxId = null) => `/?page=mailbox${mailboxId ? `&show=${mailboxId}` : ''}`,
  mailboxManage: () => '/?page=mailbox&a=manage',
  mailboxMailto: (email) => `/?page=mailbox&mailto=${email}`,
  mailboxOldAttachment: (emailId, attachmentIndex) => `/?page=mailbox&a=dlattach&mid=${emailId}&i=${attachmentIndex}`,
  map: () => '/karte',
  newsFromIT: () => 'https://foodsharing.freshdesk.com/support/solutions/folders/77000160479',
  vision: () => '/ueber-uns',
  partner: () => '/partner',
  passwordReset: () => '/?page=login&sub=passwordReset',
  poll: (pollId) => `/?page=poll&id=${pollId}`,
  pollEdit: (pollId) => `/?page=poll&id=${pollId}&sub=edit`,
  press: () => '/content?sub=presse',
  region: () => '/?page=region',
  releaseNotes: () => '/content?sub=releaseNotes',
  violations: (fsId) => `/?page=report&sub=foodsaver&id=${fsId}`,
  security: () => '/content?sub=security',
  settings: () => '/?page=settings',
  settingsCalendar: () => '/?page=settings&sub=calendar',
  settingsNotifications: () => '/?page=settings&sub=info',
  statistics: () => '/statistik',
  store: (storeId) => `/store/${storeId}`,
  storeList: () => '/?page=fsbetrieb',
  storeOwnList: () => '/?page=betrieb&a=own',

  team: () => '/team',
  transparency: () => '/content?sub=transparency',
  upload: (uuid) => `/api/uploads/${uuid}`,

  workingGroupEdit: (groupId) => `/?page=groups&sub=edit&id=${groupId}`,
  workshops: () => '/content?sub=workshops',
  urlencode: (url) => encodeURIComponent(`${url}`),
  donations: () => 'https://spenden.foodsharing.de',
  circle_of_friends: () => 'https://spenden.foodsharing.de/freundeskreis',
  selfservice: () => 'https://spenden.foodsharing.de/selfservice',
  resendActivationMail: () => '/?page=login&a=resendActivationMail',

  // javascript
  javascript: (js) => `javascript:${js}`,

  // Redirect
  relogin_and_redirect_to_url: (url) => '/?page=relogin&url=' + encodeURIComponent(url),

  // region id
  forum: (regionId, subforumId = 0, threadId = null, postId = null, newThread = false) => {
    const str = [`/?page=bezirk${regionId ? `&bid=${regionId}` : ''}`]
    if (subforumId === 1) {
      str.push('&sub=botforum')
    } else {
      str.push('&sub=forum')
    }
    if (threadId) {
      str.push(`&tid=${threadId}`)
    }
    if (postId) {
      str.push(`&pid=${postId}#post-${postId}`)
    }
    if (newThread) {
      str.push('&newthread=1')
    }
    return str.join('')
  },

  // simplified url for forum threads
  forumThread: (regionId, threadId, postId = null) => {
    return url('forum', regionId, 0, threadId, postId)
  },
  events: (regionId) => `/?page=bezirk&bid=${regionId}&sub=events`,
  foodsaverList: (regionId) => `/?page=foodsaver&bid=${regionId}`,
  foodsharepoints: (regionId) => `/?page=bezirk&bid=${regionId}&sub=fairteiler`,
  members: (regionId) => `/?page=bezirk&bid=${regionId}&sub=members`,
  options: (regionId) => `/?page=bezirk&bid=${regionId}&sub=options`,
  passports: (regionId) => `/?page=passgen&bid=${regionId}`,
  pin: (regionId) => `/?page=bezirk&bid=${regionId}&sub=pin`,
  pollNew: (regionId) => `/?page=poll&bid=${regionId}&sub=new`,
  polls: (regionId) => `/?page=bezirk&bid=${regionId}&sub=polls`,
  region_forum: (regionId) => `/?page=bezirk&bid=${regionId}&sub=forum`,
  reports: (regionId = null) => regionId ? `/?page=report&bid=${regionId}` : '/?page=report',
  statistic: (regionId) => `/?page=bezirk&bid=${regionId}&sub=statistic`,
  storeAdd: (regionId = null) => regionId ? `/?page=betrieb&a=new&bid=${regionId}` : '/?page=betrieb&a=new',
  storeEdit: (storeId) => `/?page=betrieb&a=edit&id=${storeId}`,
  stores: (regionId) => `/?page=betrieb&bid=${regionId}`,
  wall: (regionId) => `/?page=bezirk&bid=${regionId}&sub=wall`,
  workingGroups: (regionId = null) => regionId ? `/?page=groups&p=${regionId}` : '/?page=groups',
  subGroups: (parentGroupId) => parentGroupId ? `/?page=groups&p=${parentGroupId}` : '/?page=groups',

  // whats new & changelog
  changelog: () => '/content?sub=changelog',
  release_notes: () => '/content?sub=releaseNotes',

  // phone
  phone_number: (phoneNumber, allowInvalid) => `tel:${phoneNumbers.callableNumber(phoneNumber, allowInvalid)}`,
  // mailto
  mail_foodsharing_network: (mail) => `${mail}@foodsharing.network`,
  mailto_mail_foodsharing_network: (mail) => `mailto:${mail}@foodsharing.network`,

  // freshdesk support
  freshdesk: () => 'https://foodsharing.freshdesk.com/support/home',
  freshdesk_locked_email: () => 'https://foodsharing.freshdesk.com/support/solutions/articles/77000299947-e-mail-sperre-im-profil',

  // wiki
  wiki: () => 'https://wiki.foodsharing.de/',
  wiki_guide: () => 'https://wiki.foodsharing.de/Hygiene-Ratgeber_f%C3%BCr_Lebensmittel',
  wiki_create_region: () => 'https://wiki.foodsharing.de/Bezirk_gr%C3%BCnden_oder_reaktivieren',
  wiki_voting: () => 'https://wiki.foodsharing.de/Abstimmungs-Modul',
  wiki_calendar: () => 'https://wiki.foodsharing.de/Kalenderexport',
  wiki_grundsaetze: () => 'https://wiki.foodsharing.de/Grundsätze',
  wiki_legal_agreement: () => 'https://wiki.foodsharing.de/Rechtsvereinbarung',

  //
  quiz_admin_edit: () => '/?page=quiz',
  quiz_learning_video: () => 'https://youtu.be/9Fk6MHC-M1o',
  quiz_foodsaver: () => '/?page=settings&sub=up_fs',
  quiz_store_manager: () => '/?page=settings&sub=up_bip',
  quiz_ambassador: () => '/?page=settings&sub=up_bot',

  // Footer Links
  hosting: () => 'https://www.manitu.de/webhosting/',
  wiener_tafel: () => 'https://www.wienertafel.at',
  bmlfuw: () => 'https://www.bmlrt.gv.at',
  denns: () => 'https://www.denns-biomarkt.at',
  chains: () => '/?page=chain',

  // Devdocs
  devdocs: () => 'https://devdocs.foodsharing.network',

  // Beta Testing
  beta: () => 'https://beta.foodsharing.de',
  beta_testing_forum: () => 'https://beta.foodsharing.de/?page=bezirk&bid=734&sub=forum',

  // Gitlab
  git_revision: (revision) => `https://gitlab.com/foodsharing-dev/foodsharing/tree/${revision}`,

  // Social Media
  bluesky_de: () => 'https://bsky.app/profile/foodsharing.bsky.social',
  bluesky_at: () => 'https://bsky.app/profile/foodsharing.bsky.social', // GERMAN VERSION
  linkedin_de: () => 'https://www.linkedin.com/company/foodsharingde',
  linkedin_at: () => 'https://www.linkedin.com/company/foodsharingde', // GERMAN VERSION
  youtube_de: () => 'https://www.youtube.com/user/foodsharingtv',
  youtube_at: () => 'https://www.youtube.com/user/foodsharingtv', // GERMAN VERSION
  instagram_de: () => 'https://www.instagram.com/foodsharing_de',
  instagram_at: () => 'https://instagram.com/foodsharing.at',
  facebook_de: () => 'https://www.facebook.com/foodsharing.de',
  facebook_at: () => 'https://www.facebook.com/oesterreichfoodsharing',
  tiktok_de: () => 'https://www.tiktok.com/@foodsharing.de',
  tiktok_at: () => 'https://www.tiktok.com/@foodsharing.de', // GERMAN VERSION
}

const url = (key, ...params) => {
  if (!urls[key]) {
    console.error(new Error(`url() Error: url key '${key}' does not exist`))
    return '#'
  }
  return urls[key](...params)
}

const isFoodsharingDomain = (value) => value.match(/(.)+@foodsharing.network$/g)

export { url, urls, isFoodsharingDomain }
