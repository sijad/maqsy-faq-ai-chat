=== Maqsy: FAQ Manager and AI Chat ===
Contributors: sijad
Tags: faq, accordion, ai chat, chatbot, structured data
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Manage FAQs as a post type, drop them in with a shortcode or block, and let an optional AI chat widget answer visitors from that content.

== Description ==

Maqsy lets you write and organise frequently asked questions as a dedicated
post type, then place any FAQ on a page with a shortcode or a block. Each FAQ
outputs clean, accessible markup plus `FAQPage` JSON-LD structured data so search
engines can understand it.

It also ships an **optional** AI chat widget: a small floating button that, when
a visitor clicks it, lazy-loads a chat UI whose answers are grounded in your
published FAQs. The chat feature is off by default and does nothing until you add
an API key.

= FAQ management =

* FAQs are a private custom post type (`maqsy`) — not publicly browsable on their own.
* Each FAQ holds an ordered list of question/answer pairs, edited with a drag-to-reorder UI and the standard WordPress editor for answers.
* Two display templates: **Simple** (a definition list) and **Accordion** (WAI-ARIA disclosure pattern with keyboard support).

= Placing a FAQ =

Shortcode:

`[maqsy id="123"]`
`[maqsy id="123" template="accordion"]`

Block: insert the **FAQ** block, pick a FAQ, and optionally override the template.
Both render through the same code path.

= Structured data =

On singular views, each rendered FAQ emits `schema.org/FAQPage` JSON-LD built from
its question/answer text. Output is limited to singular views (not archives,
feeds or search) to follow Google's one-FAQPage-per-URL guidance.

= AI chat widget (optional) =

Configure a provider and API key under **FAQs → AI Chat Settings** and turn on
"Enable AI chat". The always-loaded launcher script is tiny; the chat
interface is only downloaded when a
visitor actually opens the chat. Answers are grounded in your published FAQs plus
any site/branding instructions you enter in settings, and the public endpoint is
rate-limited per IP.

The built-in provider works with any OpenAI-compatible `/chat/completions` API
(OpenAI, OpenRouter, or a self-hosted compatible endpoint — you choose the base
URL). Other plugins can register additional providers via the
`maqsy_ai_providers` filter.

== External services ==

This plugin's AI chat feature is **optional and disabled by default**. It does
not contact any external service unless a site administrator enables the AI chat
and saves an API key.

When the AI chat is enabled and a visitor sends a message, the plugin makes a
server-to-server request to the AI API endpoint you configure ("API base URL" in
settings). By default this is OpenRouter (`https://openrouter.ai/api/v1`); you
can change it to OpenAI (`https://api.openai.com/v1`) or any other
OpenAI-compatible endpoint.

**What is sent, and when:**

* On each visitor chat message: the visitor's message text, the text of your published FAQ posts, and any "Site & branding instructions" you entered — sent to the configured endpoint together with your API key, so the model can generate a reply.
* When an administrator clicks "Fetch models" on the settings page: a request to the endpoint's `/models` route, authenticated with the API key entered in the form.

No data is sent to the plugin's author or to WordPress.org. The plugin does not
add analytics, tracking, or telemetry.

Because you choose the endpoint, review the terms and privacy policy of whichever
provider you configure. For the two presets:

* OpenRouter — Terms: https://openrouter.ai/terms ; Privacy: https://openrouter.ai/privacy
* OpenAI — Terms: https://openai.com/policies/terms-of-use/ ; Privacy: https://openai.com/policies/privacy-policy/

== Installation ==

1. Upload the `maqsy-faq-ai-chat` folder to `/wp-content/plugins/`, or install the plugin through the Plugins screen in WordPress.
2. Activate the plugin through the **Plugins** screen.
3. Open the new **FAQs** menu and add a FAQ with one or more question/answer pairs.
4. Add `[maqsy id="…"]` (the shortcode is shown on the FAQ edit screen) to a page or post, or insert the **FAQ** block.
5. (Optional) To enable the AI chat, go to **FAQs → AI Chat Settings**, enter an API key for an OpenAI-compatible provider, and turn on "Enable AI chat".

== Frequently Asked Questions ==

= Do I need an API key to use the plugin? =

No. FAQ management, the shortcode, the block, and the structured-data output all
work with no configuration. An API key is only required for the optional AI chat
widget.

= Where does the AI chat get its answers? =

From the text of your published FAQ posts plus any instructions you add in
settings. The system prompt instructs the model not to invent facts that aren't
in that content. See the "External services" section for exactly what is sent.

= Does the plugin work without JavaScript? =

The Simple template and the structured data are pure server-side HTML. The
Accordion template's expand/collapse needs JavaScript. The AI chat widget needs
JavaScript.

= What happens to my data when I uninstall? =

Deleting the plugin removes all FAQ posts it created and its settings option.
Deactivating does not delete anything.

= Can I add my own AI provider? =

Yes. Implement `Maqsy_AI_Provider` and add it via the `maqsy_ai_providers`
filter. The filters `maqsy_ai_system_prompt`, `maqsy_ai_before_request`
and `maqsy_ai_after_response` are also available.

== Screenshots ==

1. The AI Chat Settings screen.
2. The chat widget, shown in dark and light mode.
3. Editing a FAQ: drag-to-reorder question/answer pairs, with the ready-to-paste shortcode.
4. The FAQs admin list.
5. The FAQ block in the editor, set to the Accordion template.
6. The FAQ block in the editor, set to the Simple template.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Source code and build process ==

Compiled assets in `build/` are generated from the human-readable sources
included in this package:

* `src/` — admin, front-end and block scripts/styles, built with `@wordpress/scripts` (webpack). See `webpack.config.js`.
* `chat-runtime/src/` — the lazy-loaded chat UI (React 19 + TypeScript), bundled with esbuild, and its Tailwind stylesheet. See `package.json` and `tailwind.config.js`.

To build from source:

`npm install`
`npm --prefix chat-runtime install`
`npm run build`

Development repository: https://github.com/sijad/maqsy-faq-ai-chat

Bundled third-party libraries in the chat bundle (all MIT-licensed): React and
ReactDOM, scheduler, use-sync-external-store, @floating-ui, @shadcn/react, clsx,
tailwind-merge.
