import phoneNumbers from './phone-numbers'
// these are used for generating link-paths inside vue
// e.g. $url('profile', 15)

const urls = {
  profile: (id) => `/user/${id}/profile`,
  academy: () => '/content?sub=academy',
  application: (groupId, userId) => `/region?bid=${groupId}&sub=applications&userId=${userId}`,
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
  contentNew: () => '/content?a=new',
  conversations: (conversationId = null) => `/msg${conversationId ? `?cid=${conversationId}` : ''}`,
  createBusinessCard: (data) => `/bcard?sub=makeCard&opt=${data.role}:${data.regionGroupId}`,
  dashboard: () => '/dashboard',
  dataprivacy: () => '/legal',
  education: () => '/content?sub=education',
  email: () => '/?page=email',
  event: (eventId) => `/event/${eventId}`,
  eventAdd: (regionId) => '/event/add' + (regionId ? `?bid=${regionId}` : ''),
  eventEdit: (eventId) => `/event/${eventId}/edit`,
  festival: () => '/content?sub=festival',
  foodsharepoint: (fspId) => `/fairteiler/${fspId}`,
  foodsharepointEdit: (fspId) => `/fairteiler/${fspId}/edit`, // TODO move to /fairteiler/id/edit
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
    return '/logout?ref=' + encodeURIComponent(url.pathname + url.search)
  },
  mailbox: (mailboxId = null, emailId) => {
    const params = []
    if (mailboxId) {
      params.push(`mailbox=${mailboxId}`)
    }
    if (emailId) {
      params.push(`email=${emailId}`)
    }

    let url = '/mailbox'
    if (params.length) {
      url += '?' + params.join('&')
    }
    return url
  },
  mailboxMailto: (email) => `/mailbox?mailto=${email}`,
  mailboxOldAttachment: (emailId, attachmentIndex) => `/mailbox?a=dlattach&mid=${emailId}&i=${attachmentIndex}`,
  map: ({ storeId = null, foodSharePointId = null, markers = null, center = null, zoom = null } = {}) => {
    const params = []
    if (storeId) {
      params.push(`bid=${storeId}`)
    } else if (foodSharePointId) {
      params.push(`fspId=${foodSharePointId}`)
    } else if (center) {
      params.push(`lat=${center.lat}&lon=${center.lon}`)
    }
    if (markers?.length > 0) {
      params.push(`loadMarkers=${markers}`)
    }
    if (zoom !== null) {
      params.push(`zoom=${zoom}`)
    }
    return '/karte' + (params.length > 0 ? '?' + params.join('&') : '')
  },
  newsFromIT: () => 'https://support.foodsharing.network/kb/de-de/1-aktuelle-informationen-und-storungen',
  vision: () => '/ueber-uns',
  partner: () => '/partner',
  passwordReset: () => '/password-reset',
  poll: (pollId) => `/poll?id=${pollId}`,
  pollEdit: (pollId) => `/poll?id=${pollId}&sub=edit`,
  press: () => '/presse',
  regionAdmin: () => '/regions/edit',
  oauthClientsAdmin: () => '/admin/oauthclients',
  emailBlocklistAdmin: () => '/admin/emailblocklist',
  publicRegion: (regionId) => `/region/${regionId}`,
  violations: (fsId) => `/report/user/${fsId}`,
  security: () => '/content?sub=security',
  settings: (userId) => userId ? `/user/${userId}/settings` : '/user/current/settings',
  settingsNotifications: () => '/user/current/settings?sub=info',
  settingsCalendar: () => '/user/current/settings?sub=calendar',
  settingsHygiene: () => '/user/current/settings?sub=hygiene',
  settingsChangeEmail: () => '/user/current/settings?sub=changeEmail',
  settingsPassport: () => '/user/current/settings?sub=passport',
  settingsDeleteAccount: () => '/user/current/deleteaccount',
  statistics: () => '/statistik',
  store: (storeId) => `/store/${storeId}`,
  editCategories: (type) => `/categories/${type}`,
  storeList: () => '/?page=fsbetrieb',
  storeUserList: (userId) => `/user/${userId}/stores`,
  editNameInfoUrl: () => '/region?bid=881&sub=forum&tid=58225',
  register: () => '/register',
  emailverification: () => '/emailverification',

  team: () => '/team',
  transparency: () => '/content?sub=transparency',
  upload: (uuid) => `/api/uploads/${uuid}`,

  workingGroupEdit: (groupId) => `/region?sub=edit&bid=${groupId}`,
  workingGroup: (groupId) => `/region?bid=${groupId}`,
  workshops: () => '/content?sub=workshops',
  urlencode: (url) => encodeURIComponent(`${url}`),
  donations: () => '/donation',
  donation_form: () => 'https://spenden.twingle.de/foodsharing-e-v/spendenkampagne-ueberregionale-arbeit/tw65a581c764fa1/page',
  donationAdminPage: () => '/donation/admin',
  circle_of_friends: () => 'https://spenden.foodsharing.de/freundeskreis',
  selfservice: () => '/donation/selfservice',
  resendActivationMail: () => '/login?sub=resendActivationMail',

  // javascript
  javascript: (js) => `javascript:${js}`,

  // Redirect
  relogin_and_redirect_to_url: (url) => '/relogin?url=' + encodeURIComponent(url),

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
  foodsharepointAdd: (regionId) => `/fairteiler/add/${regionId}`,
  members: (regionId) => `/region?bid=${regionId}&sub=members`,
  options: (regionId) => `/region?bid=${regionId}&sub=options`,
  passports: (regionId) => `/?page=passgen&bid=${regionId}`,
  pin: (regionId) => `/region?bid=${regionId}&sub=pin`,
  pollNew: (regionId) => `/poll?bid=${regionId}&sub=new`,
  polls: (regionId) => `/region?bid=${regionId}&sub=polls`,
  region_forum: (regionId) => `/region?bid=${regionId}&sub=forum`,
  reports: (regionId = null) => regionId ? `/report/region/${regionId}` : '/?page=report',
  statistic: (regionId) => `/region?bid=${regionId}&sub=statistic`,
  storeAdd: (regionId) => `/region/${regionId}/store/new`,
  storeEdit: (storeId) => `/?page=betrieb&a=edit&id=${storeId}`,
  stores: (regionId) => `/region/${regionId}/stores`,
  wall: (regionId) => `/region?bid=${regionId}&sub=wall`,
  workingGroups: (regionId = null) => regionId ? `/groups?p=${regionId}` : '/groups',
  achievements: (regionId) => `/region?bid=${regionId}&sub=achievements`,
  editAchievements: () => '/achievements',
  chains: () => '/chain',

  // whats new & changelog
  changelog: () => '/content?sub=changelog',
  release_notes: () => '/content?sub=releaseNotes',

  // phone
  phone_number: (phoneNumber, allowInvalid) => `tel:${phoneNumbers.callableNumber(phoneNumber, allowInvalid)}`,
  // mailto
  mail_foodsharing_network: (mail) => `${mail}@foodsharing.network`,
  mailto_mail_foodsharing_network: (mail) => `mailto:${mail}@foodsharing.network`,
  mailto_mail: (mail) => `mailto:${mail}`,

  // contact
  contact_email_common: () => 'info@foodsharing.de',
  contact_email_common_ch: () => 'info@foodsharingschweiz.ch',

  // helpdesk support
  helpdesk: () => 'https://support.foodsharing.network/kb',
  helpdesk_locked_email: () => 'https://support.foodsharing.network/help/de-de/4-e-mail/16-e-mail-sperre-im-profil',

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

  // resource mosaic
  resources: (regionId) => `/region?bid=${regionId}&sub=resources`,
  resource: (regionId, resourceId) => `/region?bid=${regionId}&sub=resources&resourceId=${resourceId}`,

  // Footer Links
  hosting: () => 'https://www.manitu.de/webhosting/',
  tafel_oesterreich: () => 'https://www.wienertafel.at',
  bmlfuw: () => 'https://www.bmlrt.gv.at',
  denns: () => 'https://www.denns-biomarkt.at',

  // Devdocs
  devdocs: () => 'https://devdocs.foodsharing.network',

  // Beta Testing
  beta: () => 'https://beta.foodsharing.de',

  // Gitlab
  git_revision: (revision) => `https://gitlab.com/foodsharing-dev/foodsharing/tree/${revision}`,

  // Social Media
  bluesky_de: () => 'https://bsky.app/profile/foodsharing.de',
  bluesky_at: () => 'https://bsky.app/profile/foodsharing.de', // GERMAN VERSION
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
  whatsapp_de: () => 'https://whatsapp.com/channel/0029VaerhFPADTOAPGeJQ71R',
  whatsapp_at: () => 'https://whatsapp.com/channel/0029VaerhFPADTOAPGeJQ71R',

  // Newsletter
  newsletter: () => 'https://listmonk.foodsharing.network/subscription/form',
}

const url = (key, ...params) => {
  if (!urls[key]) {
    console.error(new Error(`url() Error: url key '${key}' does not exist`))
    return '#'
  }
  return urls[key](...params)
}

const isNotFoodsharingDomain = (value) => value.match(/(.)+@foodsharing.network$/g) === null

export { url, urls, isNotFoodsharingDomain }
