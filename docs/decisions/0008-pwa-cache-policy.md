# ADR-0008: The PWA caches public static assets only

- Status: Accepted (2026-10-14)

The service worker (`public/sw.js`):

- Precaches only `/offline.html`, the manifest, and the icons.
- Serves fingerprinted build assets under `/build/assets/` cache-first. These are public and immutable.
- Is **network-only** for every navigation, Inertia, API, document, and receipt request. If the network fails during a navigation, it shows `/offline.html`.
- Never stores responses from authenticated pages, documents, receipts, or form submissions.
- Deletes old caches when it activates, and clears its caches when it receives the `CLEAR_CACHES` message sent at logout.
- Announces updates to the user ("a new version is available, reload"). It never takes over a page in the middle of a form.

No application data is written to localStorage, sessionStorage, or IndexedDB. Only UI preferences (language, theme, sidebar) are stored client-side.
