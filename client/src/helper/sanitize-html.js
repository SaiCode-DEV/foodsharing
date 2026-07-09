import DOMPurify from 'dompurify'

// Single hardened entry point for client-side HTML sanitization.
//
// Why centralized: only two paths render HTML on the client that is not solely
// server-sanitized -- external email bodies and client-rendered markdown
// (markdown-it runs with `html: true`). Both must share the exact same hardened
// config so a future audit or CVE fix only has to happen in one place.
//
// This is defense-in-depth: email bodies are additionally sanitized in
// Modules/Mailbox/MailboxGateway.php, and most other v-html sinks rely on the
// server-side HTMLPurifier (src/Utility/Sanitizer.php). The client layer must
// still be correct because it is the only thing standing between attacker-
// controlled email/markdown HTML and the DOM if the server layer ever regresses.

// Force safe link behavior for anything that survives sanitization. We only
// harden *external* links (different origin) so internal links -- e.g. the
// `@profile` links produced by the markdown renderer -- keep their normal
// in-app navigation behavior instead of spawning new tabs. This mirrors the
// existing external-link logic in components/Markdown/markdownRenderer.js.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName !== 'A' || !node.hasAttribute('href')) {
    return
  }
  let isExternal = false
  try {
    isExternal = new URL(node.getAttribute('href'), window.location.origin).origin !== window.location.origin
  } catch (e) {
    // Relative or malformed href -> treat as internal, leave untouched.
    isExternal = false
  }
  if (isExternal) {
    node.setAttribute('target', '_blank')
    // noopener/noreferrer: prevent reverse-tabnabbing and referrer leakage.
    // nofollow: do not lend SEO weight to links in attacker-controlled content.
    node.setAttribute('rel', 'noopener noreferrer nofollow')
  }
})

// USE_PROFILES.html keeps the standard HTML allowlist (formatting, links,
// images, lists, tables) while dropping scripts, event handlers and other
// active content. The prototype-pollution class around this option
// (CVE-2026-41238) is fixed in dompurify >= 3.4.0; keep the dependency at or
// above that line.
const CONFIG = {
  USE_PROFILES: { html: true },
}

export function sanitizeHtml (dirty) {
  return DOMPurify.sanitize(dirty ?? '', CONFIG)
}

export default sanitizeHtml
