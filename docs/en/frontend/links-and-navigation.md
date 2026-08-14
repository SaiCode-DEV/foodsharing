# Links and navigation

The website is served by PHP, but navigation between pages is handled by `vue-router` on the
client (see `client/src/helper/router.js`). Only the page content is exchanged — navigation,
footer, chat dock and notifications keep running, so navigating feels instant and open chats
survive.

Where the target is a real client side route, the router renders its component and nothing is
fetched as markup. Most pages are not there yet, so the router has a catch-all route as a
fallback: it requests the same url with an `X-Content-Only` header and injects the markup the
backend returns into `#app-content` (`client/src/Catchall.vue`). That is an intermediary step,
not the goal — it lets `vue-router` own every url while the pages are still rendered by PHP, and
each page that becomes a proper route (component plus data from the REST API) drops out of the
fallback and is handled by the router alone.

Either way this only works for links that go through the router. A plain `<a href="…">` always
makes the browser load a new document, which throws away the whole application state.

## Which one do I use?

| Target                                                        | Use                                       |
| ------------------------------------------------------------- | ----------------------------------------- |
| A page of this website, known while writing the template       | `<router-link :to="…">`                   |
| A url that may be internal or external at runtime              | `<FsLink :to="…">`                        |
| A bootstrap-vue component (`b-button`, `b-list-group-item`, …) | its `:to` prop instead of `:href`         |
| `mailto:`, `tel:`, an external site, a file download           | `<a :href="…">`                           |
| Nothing — the element only has a click handler                 | `<b-button>` / `<button>`, not a link      |

### `<router-link :to>`

The default for links inside our own site. `:to` takes the same path `$url()` returns:

```html
<router-link :to="$url('profile', user.id)">
  {{ user.name }}
</router-link>
```

Two things to keep in mind:

- `router-link` renders an `<a>`, so existing css and `class`, `title` or `v-b-tooltip` keep
  working unchanged.
- Listeners are component events, not dom events. A `@click` handler needs `.native`:
  `@click.native.stop`. Without `.native` the handler is silently ignored.

### `<FsLink :to>`

`client/src/components/UI/FsLink.vue` decides at runtime what the target needs. Use it when
the url comes from data instead of from the template — banner links, breadcrumbs, dashboard
entries, menu entries from the backend:

```html
<FsLink :to="link.urlShorthand ? $url(link.urlShorthand) : link.href">
  {{ $t(link.text) }}
</FsLink>
```

It renders a `router-link` for internal paths and a plain `<a>` for everything the router
cannot handle: external urls (`https:`, `mailto:`, `tel:`), in-page anchors (`#…`) and empty
targets. External links open in a new tab (with `rel="noopener noreferrer"`) unless
`:open-external-in-new-tab="false"` or an explicit `target` says otherwise. Unlike
`router-link`, `FsLink` forwards attributes and listeners to the rendered `<a>`, so a plain
`@click` works.

### bootstrap-vue components

`b-button`, `b-link`, `b-list-group-item`, `b-dropdown-item`, `b-avatar` and friends all
render a `b-link` internally, which navigates client side as soon as it gets `to` instead of
`href`:

```html
<b-button :to="$url('eventAdd', regionId)">
  {{ $t('events.add_new_event') }}
</b-button>
```

`ContainerButton` passes both props through, so `:to="…"` works there as well.

### Plain `<a>`

Keep an `<a :href="…">` when the target is not a page of this website, or when a document load
is what you want:

- `mailto:` and `tel:` links
- external sites and the wiki
- downloads and api urls (`/api/uploads/…`)
- links that deliberately open a new tab (`target="_blank"`) — the router ignores those anyway
- `/logout`, and anything else that has to hit the server as a document request

`<a href="#" @click="…">` is not a link at all. Prefer a `<b-button variant="link">` for those;
if you touch such code, migrating it is welcome.

## Pitfalls

**Nested links.** Some list entries are one big link containing smaller ones. Router links
handle that fine: the inner link calls `preventDefault()`, and the router skips a link whose
event is already handled. A nested `<a>` that is *not* a router link (a `mailto:` address, a
`PhoneButton`) has to stop the event itself, otherwise the outer router link navigates away
instead:

```html
<a :href="`mailto:${user.email}`" @click.stop>{{ user.email }}</a>
```

**Overlays stay open.** A full page load used to close modals and dropdowns implicitly. With
client side navigation the shell keeps running, so an overlay that is not part of the exchanged
page content stays visible on top of the new page. Close it when the route changes, like
`SearchBarModal` does:

```js
watch: {
  $route () {
    this.$refs.searchBarModal.hide()
  },
},
```

**Markdown and server rendered html.** Links inside `Markdown` content or inside markup that
comes from PHP are plain `<a>` elements and always reload the page. That is a known gap, not something to work around in the component.

**Never call `history.pushState` yourself.** Changing the url behind the router's back leaves
`$router.currentRoute` pointing at the previously loaded url. A later navigation to *that* url
then counts as a duplicate: the router aborts it and resets the address bar to its own stale
route, so the url and the visible page drift apart and the click does nothing. The region page
used to swap subpages that way (`isLinkingSubpages`); it is plain `router-link`s now. If you
need the url to change, let the router do it (`this.$router.push(...)`).

**Legacy pages work too.** Components mounted by `vueApply()` get the same router instance
(`client/src/vue.js`), so `router-link` works in `src/Modules/**` components as well.
