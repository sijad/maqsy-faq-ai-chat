# Maqsy

Manage FAQs as a WordPress custom post type, insert them anywhere via shortcode or block, and let an AI chat widget answer visitor questions grounded in that content.

## Development

```
composer install   # PHP dependencies (PHPCS + WordPress Coding Standards)
npm install         # JS dependencies (@wordpress/scripts, Tailwind, esbuild)
npm --prefix chat-runtime install   # isolated React 19 toolchain for the chat bundle

npm run start        # watch/build JS, block, and Tailwind sources during development
npm run build        # production build to build/ (compiles Tailwind first)
npm run lint:js       # ESLint (WordPress config)
npm run lint:css      # Stylelint (WordPress config)
npm run makepot       # regenerate languages/maqsy-faq-ai-chat.pot
composer lint         # PHPCS (WordPress Coding Standards)
composer lint:fix     # PHPCBF autofix
```

## Releasing to wordpress.org

1. Bump the version in **four** places: `maqsy-faq-ai-chat.php` header, the
   `MAQSY_VERSION` constant, `readme.txt` `Stable tag`, and `package.json`.
   Add a `readme.txt` changelog entry.
2. `npm install && npm --prefix chat-runtime install && npm run build` to
   regenerate `build/`.
3. `composer lint && npm run lint:js && npm run lint:css` — all clean.
4. Run the [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin
   against the build and resolve findings.
5. Package: `npm run plugin-zip` for a quick local zip, or push to the SVN repo
   via [`10up/action-wordpress-plugin-deploy`](https://github.com/10up/action-wordpress-plugin-deploy) (from `https://github.com/sijad/maqsy-faq-ai-chat`),
   which honours `.distignore`. Either way the distributed plugin must contain
   the compiled `build/` directory (it is git-ignored here but required in the
   release) plus the human-readable `src/` and `chat-runtime/src/` sources.
6. The plugin directory icon, banner and screenshots live in `.wordpress-org/`
   (repo root). The deploy action copies them to SVN `/assets` (a sibling of
   `trunk/`); they are excluded from the plugin itself via `.distignore` and the
   `package.json` `files` list. Set the action's `assets-dir` input to
   `.wordpress-org`.

## Usage

### Shortcode

```
[maqsy id="123"]
[maqsy id="123" template="accordion" style="dark"]
```

- `id` (required) — the ID of a `maqsy` FAQ post.
- `template` (optional) — `simple` or `accordion`. Overrides the template saved on the FAQ post.
- `style` (optional) — `light` (default), `dark`, or `minimal`. Overrides the visual style.

### Block

Insert the "FAQ" block (`maqsy/faq`) from the block inserter, pick an FAQ post, and optionally override its template/style — it renders through the same code path as the shortcode.

### AI chat

Configure a provider and API key under **FAQs → AI Chat Settings**, then turn on "Enable AI chat". A small floating button appears in the corner of every front-end page; clicking it lazy-loads the chat UI (React + Tailwind, rendered inside a Shadow DOM so its styles can never leak into — or be broken by — the active theme). Answers are grounded in your published FAQs plus any custom site/branding instructions you provide in settings.

The built-in provider talks to any OpenAI-compatible `/chat/completions` API (OpenAI or OpenRouter — just change the base URL), and other plugins can register additional providers without touching Maqsy's code:

```php
add_filter( 'maqsy_ai_providers', function ( $providers ) {
	$providers[] = new My_Custom_Provider(); // implements Maqsy_AI_Provider
	return $providers;
} );
```

`maqsy_ai_system_prompt` (filter) and `maqsy_ai_before_request` / `maqsy_ai_after_response` (actions) are also available for customizing the prompt or observing requests.
