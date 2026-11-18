=== Maqsy: FAQ Manager and AI Chat ===
Contributors: sijad
Tags: faq, ai chat, chatbot, quiz, lead generation
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

FAQs with shortcode/block, plus an optional AI chat that answers from your FAQs and runs conversational quizzes that recommend the right solution.

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

= Conversational quizzes =

Turn the chat into a guided assistant that asks visitors a few questions and
recommends the most relevant product, plan, or service.

* **Quiz builder** (FAQs → Quizzes): create, edit, preview, publish and manage any number of quizzes.
* **Question types**: multiple choice (one or several answers) and open-ended. Any question can be conditional — only asked when earlier answers match.
* **Branching**: send visitors to a later question (or straight to the result) based on their answer.
* **Personalized outcomes**: each outcome has a message (with `{name}` and answer placeholders), recommendations, an offer, a call-to-action button, and an optional landing page — shown as a button or opened automatically after a cancellable countdown. Outcomes are chosen by rules first, then by points that answers award, then a fallback.
* **AI-assisted**: draft a complete quiz from your goals and business context.
* **In the chat**: visitors start quizzes from the chat's "Help me choose" menu, from a card the AI offers when a visitor needs a recommendation, by sending one of the quiz's chat keywords (for example ACCESS) as a message, from the `[maqsy_quiz id="123"]` button shortcode, or from any `#maqsy-quiz-123` link. Each quiz can have its own start-button label, such as "Find my plan". Questions come one at a time; visitors can skip optional questions, pause to ask the chat something, resume (even after reloading the page), restart, or end the quiz.
* **Leads**: open-ended questions can capture a name, email, or phone number (validated), optionally emailed to you on completion. The `maqsy_quiz_completed` action lets you push them into a CRM.
* **Analytics** (FAQs → Quiz Analytics): starts, completions, drop-offs, leads and CTA clicks; a daily trend; outcome and start-source breakdowns; a per-question funnel with answer distributions; and a leads/responses table with CSV export.

Quiz responses are stored in two custom database tables and are included in
WordPress's personal data export and erasure tools (matched by email).

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
* When the chat's system prompt is built: the titles and descriptions of published quizzes that allow AI suggestions (so the assistant can offer them).
* When an administrator uses "Generate with AI" in the quiz builder: the goal, audience and business context they enter, the site instructions, and (if chosen) the text of published FAQs.

No data is sent to the plugin's author or to WordPress.org. The plugin does not
send analytics, tracking, or telemetry anywhere; quiz analytics are stored only in
your own WordPress database.

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

Both FAQ templates and the structured data work without JavaScript. The Accordion
template uses native HTML details/summary elements. The AI chat widget needs
JavaScript.

= What happens to my data when I uninstall? =

Deleting the plugin removes all FAQ and quiz posts it created, the quiz
responses/analytics tables, and its options. Deactivating does not delete anything.

= Can visitors take quizzes without the AI chat? =

Quizzes run inside the chat widget, so the chat must be enabled with an API key.
Running a quiz itself does not call the AI provider.

= How do I send quiz leads to my CRM? =

Hook into `maqsy_quiz_completed`, which receives the session, the result, the
lead's `{ name, email, phone }`, and the quiz. You can also set a notification
email per quiz, or export leads as CSV from Quiz Analytics.

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
7. The quiz builder: multiple-choice, open-ended and lead-capture questions.
8. Quiz outcomes, picked by rules, points or a fallback.
9. Quiz settings: introduction, chat options, lead notifications and launch shortcode.
10. Previewing a quiz in the builder, with live outcome points.
11. Generating a quiz with AI from a goal, audience and business context.
12. The Quiz Analytics dashboard.

== Changelog ==

= 1.2.0 =
* New: conversational quizzes in the chat widget, with a visual quiz builder, multiple-choice/open-ended/conditional questions, branching, scored and rule-based personalized outcomes, recommendations, offers, CTAs and landing-page redirects.
* New: AI-assisted quiz generation.
* New: the AI can suggest a relevant quiz during a conversation; quizzes can also be started from the chat's recommendation menu, by per-quiz chat keywords (matched without calling the AI), the `[maqsy_quiz]` shortcode, or `#maqsy-quiz-ID` links.
* New: skip, pause/resume (persists across page loads), restart and end controls for visitors.
* New: Quiz Analytics dashboard (starts, completions, drop-offs, funnel, answers, leads, CTA clicks) with CSV export; lead notification emails; personal data exporter/eraser.
* Dev: `maqsy_quiz_completed` action; AI providers receive an optional per-request `timeout` setting.

= 1.1.0 =
* Use native details/summary elements for the Accordion template, with keyboard support and no JavaScript required.
* Add Maqsy_Public::get_schema( $id ) to retrieve FAQPage structured data separately.
* Remove the FAQ accordion script and its manual accessibility state handling.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Adds conversational quizzes and quiz analytics. Two database tables are created automatically on update.

= 1.1.0 =
Accordion FAQs now use native details/summary markup. Update custom button or .active CSS selectors to target summary and details[open].

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
