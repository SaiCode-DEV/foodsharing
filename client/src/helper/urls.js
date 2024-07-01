import phoneNumbers from './phone-numbers'
// these are used for generating link-paths inside vue
// e.g. $url('profile', 15)

const urls = {
  profile: (id) => `/user/${id}/profile`,
  academy: () => '/content?sub=academy',
  application: (groupId, userId) => `/?page=application&bid=${groupId}&fid=${userId}`,
  applications: (groupId) => `/region?bid=${groupId}&sub=applications`,
  basket: (basketId) => `/essenskoerbe/${basketId}`,
  baskets: () => '/essenskoerbe',
  blog: () => '/news',
  blogAdd: () => '/blog?sub=add',
  blogPost: (blogId) => `/blog/${blogId}`,
  blogEdit: (blogId) => `/blog?sub=edit&id=${blogId}`,
  blogList: () => '/blog?sub=manage',
  claims: () => '/content?sub=forderungen',
  communities: () => '/content?sub=communities',
  contact: () => '/content?sub=contact',
  contentEdit: () => '/content',
  contentEditEntry: (id) => `/content?a=edit&id=${id}`,
  contentNew: () => '/content?a=neu',
  conversations: (conversationId = null) => `/?page=msg${conversationId ? `&cid=${conversationId}` : ''}`,
  createBusinessCard: (data) => `/user/current/settings?sub=makeCard&opt=${data.role}:${data.regionGroupId}`,
  dashboard: () => '/?page=dashboard',
  dataprivacy: () => '/legal',
  donate: () => '/unterstuetzung',
  email: () => '/?page=email',
  event: (eventId) => `/event/${eventId}`,
  eventAdd: (regionId) => '/event/add' + (regionId ? `?bid=${regionId}` : ''),
  eventEdit: (eventId) => `/event/${eventId}/edit`,
  festival: () => '/content?sub=festival',
  foodsharepoint: (fspId) => `/fairteiler/${fspId}`,
  fsstaedte: () => '/content?sub=fsstaedte',
  home: () => '/',
  imprint: () => '/impressum',
  infos: () => '/content?sub=infohub',
  infosCompany: () => '/fuer-unternehmen',
  joininfo: () => '/content?sub=joininfo',
  leeretonne: () => '/content?sub=leeretonne',
  login: (ref = null) => '/login' +
    ((ref !== null && ref.length > 0) ? '?ref=' + encodeURIComponent(`${ref}`) : ''),
  logout: () => {
    const url = new URL(window.location.href)
    return '/?page=logout&ref=' + encodeURIComponent(url.pathname + url.search)
  },
  mailbox: (mailboxId = null, emailId) => {
    let url = '/?page=mailbox'
    if (mailboxId) {
      url += `&mailbox=${mailboxId}`
    }
    if (emailId) {
      url += `&email=${emailId}`
    }
    return url
  },
  mailboxManage: () => '/?page=mailbox&a=manage',
  mailboxMailto: (email) => `/?page=mailbox&mailto=${email}`,
  mailboxOldAttachment: (emailId, attachmentIndex) => `/?page=mailbox&a=dlattach&mid=${emailId}&i=${attachmentIndex}`,
  map: ({ storeId = null, foodSharePointId = null, markers = null } = {}) => {
    const params = []
    if (storeId) {
      params.push(`bid=${storeId}`)
    } else if (foodSharePointId) {
      params.push(`fspId=${foodSharePointId}`)
    }
    if (markers?.length > 0) {
      params.push(`loadMarkers=${markers}`)
    }
    return '/karte' + (params.length > 0 ? '?' + params.join('&') : '')
  },
  newsFromIT: () => 'https://foodsharing.freshdesk.com/support/solutions/folders/77000160479',
  vision: () => '/ueber-uns',
  partner: () => '/partner',
  passwordReset: () => '/login?sub=passwordReset',
  poll: (pollId) => `/?page=poll&id=${pollId}`,
  pollEdit: (pollId) => `/?page=poll&id=${pollId}&sub=edit`,
  press: () => '/content?sub=presse',
  regionAdmin: () => '/regions/edit',
  region: (regionId) => regionId ? `/region?bid=${regionId}` : '/?page=region',
  releaseNotes: () => '/content?sub=releaseNotes',
  violations: (fsId) => `/?page=report&sub=foodsaver&id=${fsId}`,
  security: () => '/content?sub=security',
  settings: (userId) => userId ? `/user/${userId}/settings` : '/user/current/settings',
  settingsNotifications: () => '/user/current/settings?sub=info',
  settingsCalendar: () => '/user/current/settings?sub=calendar',
  statistics: () => '/statistik',
  store: (storeId) => `/store/${storeId}`,
  storeList: () => '/?page=fsbetrieb',
  storeUserList: (userId) => `/user/${userId}/stores`,

  team: () => '/team',
  transparency: () => '/content?sub=transparency',
  upload: (uuid) => `/api/uploads/${uuid}`,

  workingGroupEdit: (groupId) => `/?page=groups&sub=edit&id=${groupId}`,
  workingGroup: (groupId) => `/region?bid=${groupId}`,
  workshops: () => '/content?sub=workshops',
  urlencode: (url) => encodeURIComponent(`${url}`),
  donations: () => 'https://spenden.foodsharing.de',
  donation_form: () => 'https://spenden.twingle.de/foodsharing-e-v/spendenkampagne-ueberregionale-arbeit/tw65a581c764fa1/page',
  circle_of_friends: () => 'https://spenden.foodsharing.de/freundeskreis',
  selfservice: () => 'https://spenden.foodsharing.de/selfservice',
  resendActivationMail: () => '/login?sub=resendActivationMail',

  // javascript
  javascript: (js) => `javascript:${js}`,

  // Redirect
  relogin_and_redirect_to_url: (url) => '/?page=relogin&url=' + encodeURIComponent(url),

  // region id
  forum: (regionId, subforumId = 0, threadId = null, postId = null, newThread = false) => {
    const str = [`/region?${regionId ? `bid=${regionId}&` : ''}sub=${subforumId === 1 ? 'botforum' : 'forum'}`]
    if (threadId) str.push(`tid=${threadId}`)
    if (postId) str.push(`pid=${postId}`)
    if (newThread) str.push('newthread=1')
    return str.join('&')
  },

  // simplified url for forum threads
  forumThread: (regionId, threadId, postId = null) => {
    return url('forum', regionId, 0, threadId, postId)
  },
  events: (regionId) => `/region?bid=${regionId}&sub=events`,
  foodsharepoints: (regionId) => `/region?bid=${regionId}&sub=fairteiler`,
  foodsharepointAdd: (regionId) => `/fairteiler?bid=${regionId}&sub=add`,
  members: (regionId) => `/region?bid=${regionId}&sub=members`,
  options: (regionId) => `/region?bid=${regionId}&sub=options`,
  passports: (regionId) => `/?page=passgen&bid=${regionId}`,
  pin: (regionId) => `/region?bid=${regionId}&sub=pin`,
  pollNew: (regionId) => `/?page=poll&bid=${regionId}&sub=new`,
  polls: (regionId) => `/region?bid=${regionId}&sub=polls`,
  region_forum: (regionId) => `/region?bid=${regionId}&sub=forum`,
  reports: (regionId = null) => regionId ? `/?page=report&bid=${regionId}` : '/?page=report',
  statistic: (regionId) => `/region?bid=${regionId}&sub=statistic`,
  storeAdd: (regionId) => `/region/${regionId}/store/new`,
  storeEdit: (storeId) => `/?page=betrieb&a=edit&id=${storeId}`,
  stores: (regionId) => `/region/${regionId}/stores`,
  wall: (regionId) => `/region?bid=${regionId}&sub=wall`,
  workingGroups: (regionId = null) => regionId ? `/?page=groups&p=${regionId}` : '/?page=groups',
  subGroups: (parentGroupId) => parentGroupId ? `/?page=groups&p=${parentGroupId}` : '/?page=groups',
  achievements: (regionId) => `/region?bid=${regionId}&sub=achievements`,

  // whats new & changelog
  changelog: () => '/content?sub=changelog',
  release_notes: () => '/content?sub=releaseNotes',

  // phone
  phone_number: (phoneNumber, allowInvalid) => `tel:${phoneNumbers.callableNumber(phoneNumber, allowInvalid)}`,
  // mailto
  mail_foodsharing_network: (mail) => `${mail}@foodsharing.network`,
  mailto_mail_foodsharing_network: (mail) => `mailto:${mail}@foodsharing.network`,
  mailto_mail: (mail) => `mailto:${mail}`,

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

  // quiz
  quiz_admin_edit: (quizId) => '/quiz/edit' + (quizId ? `/${quizId}` : ''),
  quiz_learning_video: () => 'https://youtu.be/9Fk6MHC-M1o',
  rise_role: (role) => '/user/current/settings?sub=rise_role' + (role ? `&role=${role}` : ''),
  quiz_foodsaver: () => urls.rise_role(1),
  quiz_store_manager: () => urls.rise_role(2),
  quiz_ambassador: () => urls.rise_role(3),

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
  beta_testing_forum: () => 'https://beta.foodsharing.de/region?bid=734&sub=forum',

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
