/* eslint-disable eqeqeq */

import $ from 'jquery'

import Storage from '@/storage'
import { GET, isMob, pulseError } from '@/script'
import DataUser from '@/stores/user'
import conversationStore from '@/stores/conversations'
import profileStore from '@/stores/profiles'
import * as api from '@/api/conversations'
import { url } from '@/helper/urls'
import {
  plainToHtml,
} from '@/utils'

import Vue from 'vue'

import ChatComponent from '@/views/pages/Message/ChatComponent.vue'

const storage = new Storage()

const CHAT_BOX_WIDTH = 370

const conv = {

  initiated: false,

  chatboxes: null,

  isBigPageMode: false,

  /*
   * init function have to be called one time on domready
   */
  init: function () {
    if (conv.initiated === false) {
      if (GET('page') === 'msg') {
        this.isBigPageMode = true
      }
      this.initiated = true
      this.chatboxes = []

      const chats = storage.get('msg-chats')

      if (!isMob()) {
        // On desktop register opening chats in popup dialogs
        conversationStore.messagePopupOpenChatListener = chatId => { this.appendChatbox(chatId) }
      }

      if (chats != undefined) {
        for (let i = 0; i < chats.length; i++) {
          if (chats[i].id != undefined) {
            conv.appendChatbox(chats[i].id, chats[i].min)
          }
        }
      }
    }
  },

  storeOpenedChatWindows: function () {
    const ids = conv.getCids()

    if (ids.length > 0) {
      const infos = conv.getChatInfos()
      storage.set('msg-chats', infos)
    } else {
      storage.del('msg-chats')
    }
  },

  // minimize or maximize the chatbox
  togglebox: function (cid) {
    const key = conv.getKey(cid)

    if (conv.chatboxes[key].minimized) {
      conv.maxbox(cid)
    } else {
      conv.minbox(cid)
    }

    conv.storeOpenedChatWindows()
  },

  // maximoze mini box
  maxbox: function (cid) {
    const key = conv.getKey(cid)
    conv.chatboxes[key].el.children('.chatboxcontent').show()
    conv.chatboxes[key].minimized = false
  },

  // minimize a box
  minbox: function (cid) {
    const key = conv.getKey(cid)
    conv.chatboxes[key].el.children('.chatboxcontent').hide()
    conv.chatboxes[key].minimized = true
  },

  /**
   * close the chatbox to the given chatId
   */
  close: function (cid) {
    const tmp = []
    let x = 0
    for (let i = 0; i < conv.chatboxes.length; i++) {
      if (conv.chatboxes[i].id == cid) {
        conv.chatboxes[i].el.remove()
      } else {
        conv.chatboxes[i].el.css('right', `${20 + (x * CHAT_BOX_WIDTH)}px`)
        tmp.push(conv.chatboxes[i])
        x++
      }
    }

    this.chatboxes = tmp

    // re register polling service
    this.storeOpenedChatWindows()
  },

  closeAll: function () {
    for (let i = 0; i < conv.chatboxes.length; i++) {
      conv.chatboxes[i].el.remove()
    }

    this.chatboxes = []

    // re register polling service
    this.storeOpenedChatWindows()
  },

  /**
   * get the array key for given conversation_id
   */
  getKey: function (cid) {
    for (let i = 0; i < conv.chatboxes.length; i++) {
      if (conv.chatboxes[i].id == cid) {
        return i
      }
    }

    return -1
  },

  /**
   * get actic chatbox infos
   */
  getChatInfos: function () {
    const tmp = []

    for (let i = 0; i < conv.chatboxes.length; i++) {
      tmp.push({
        id: parseInt(conv.chatboxes[i].id),
        min: conv.chatboxes[i].minimized,
      })
    }

    return tmp
  },

  /**
   * get all conversation ids from active windows
   */
  getCids: function () {
    const tmp = []

    for (let i = 0; i < conv.chatboxes.length; i++) {
      tmp.push(parseInt(conv.chatboxes[i].id))
    }

    return tmp
  },

  /**
   * open settingsmenu to the given chatbox
   */
  settings: function (cid) {
    const key = this.getKey(cid)
    this.chatboxes[key].el.children('.chatboxhead').children('.settings').toggle()
  },

  /**
   * load the first content for one chatbox
   */
  initChat: async function (cid) {
    const key = this.getKey(cid)

    try {
      await conversationStore.loadConversation(cid)
      const conversation = conversationStore.conversations[cid]
      /* disable leaving chats as it currently leads to undefined logical behaviour that breaks other behaviour :D
        conv.addChatOption(cid, `<a href="#" onclick="if(confirm('Bist Du Dir sicher, dass Du den Chat verlassen möchtest? Dadurch verlierst du unwiderruflich Zugriff auf alle Nachrichten in dieser Unterhaltung.')){conv.leaveConversation(${cid});}return false;">Chat verlassen</a>`)
      */
      if (conversation.storeId === null) { // stores can't be renamed
        conv.addChatOption(cid, `<span class="optinput"><input placeholder="Chat umbenennen..." type="text" name="chatname" value="" maxlength="30" /><i onclick="conv.rename(${cid}, $(this).prev().val())" class="fas fa-arrow-circle-right"></i></span>`)
      }
      // first build a title from all the usernames
      let title = conversation.title
      if (title == null) {
        title = []
        for (const memberId of conversation.members) {
          if (memberId === DataUser.getters.getUserId() || profileStore.profiles[memberId].name === null) {
            continue
          }
          title.push(`
            <a href="${url('profile', memberId)}">
              ${plainToHtml(profileStore.profiles[memberId].name)}
            </a>
          `)
        }
        title = title.join(', ')
      } else if (conversation.storeId) {
        title = `
          <a href="${url('store', conversation.storeId)}">
            ${plainToHtml(title)}
          </a>
        `
      } else {
        title = plainToHtml(title)
      }

      if (key >= 0 && conv.chatboxes[key] !== undefined) {
        conv.chatboxes[key].el.children('.chatboxhead').children('.chatboxtitle').html(`
        <i class="fas fa-fw fa-comment fa-flip-horizontal"></i>
        ${title}
      `)
      }
    } catch (e) {
      pulseError('Fehler beim Laden der Unterhaltung')
      console.error(e)
    } finally {
      conv.storeOpenedChatWindows()
    }
  },

  rename: async function (cid, newName) {
    try {
      await api.renameConversation(cid, newName)
      const key = this.getKey(cid)
      conv.chatboxes[key].el.children('.chatboxhead').children('.chatboxtitle').html(`
        <i class="fas fa-fw fa-comment fa-flip-horizontal"></i>
        ${plainToHtml(newName)}
      `)
    } catch (e) {
      pulseError('Fehler beim Umbenennen der Unterhaltung')
      console.error(e)
    }
  },

  leaveConversation: async function (cid) {
    await api.removeUserFromConversation(cid, DataUser.getters.getUserId())
    conv.close(cid)
    conversationStore.loadConversations()
  },

  appendChatbox: function (cid, min) {
    if (this.isBigPageMode) {
      return false
    }

    if (min == undefined) {
      min = false
    }
    if (conv.getKey(cid) === -1) {
      const right = 20 + (this.chatboxes.length * CHAT_BOX_WIDTH)

      const $el = $(`
        <div id="chat-${cid}" class="chatbox ui-corner-top" style="bottom: 0px; right: ${right}px; display: block;"></div>
      `).appendTo('body')
      $el.html(`
        <div class="chatboxhead ui-corner-top">
          <div class="chatboxtitle" onclick="conv.togglebox(${cid});">
            <i class="fas fa-fw fa-spinner fa-spin"></i>
          </div>
          <ul style="display:none;" class="settings linklist linkbubble ui-shadow corner-all">
            <li>
              <a href="${url('conversations', cid)}">Alle Nachrichten</a>
            </li>
            <li>
              <a href="#" onclick="conv.closeAll();return false;">Alle Chats schließen</a>
            </li>
          </ul>
          <div class="chatboxoptions">
            <a href="#" title="Einstellungen" onclick="conv.settings(${cid});return false;">
              <i class="fas fa-fw fa-cog"></i>
            </a>
            <a title="schließen" href="#" onclick="conv.close(${cid});return false;">
              <i class="fas fa-fw fa-times"></i>
            </a>
          </div>
          <br clear="all"/>
        </div>
        <div class="chatboxcontent"></div>
      `)

      const ComponentClass = Vue.extend(ChatComponent)
      const instance = new ComponentClass({
        propsData: { popupMode: true, chatId: cid },
      })
      instance.$mount() // pass nothing
      $el.children('.chatboxcontent').append(instance.$el)

      this.chatboxes.push({
        el: $el,
        id: cid,
        minimized: false,
      })

      /*
       * do the init ajax call
       */
      this.initChat(cid)

      /*
       * register service new
       */
      if (min) {
        conv.minbox(cid)
      }
    } else {
      this.maxbox(cid)
    }
  },
  addChatOption: function (cid, el) {
    $(`#chat-${cid} .settings`).append(`<li>${el}</li>`)
  },
}
$(function () {
  if ($('body.loggedin').length > 0) {
    conv.init()
  }
})

export default conv
