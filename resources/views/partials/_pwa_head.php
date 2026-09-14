<?php

/**
 * Home-screen-install tags — manifest link, icons, and the iOS-specific
 * meta tags Safari still requires separately (it doesn't read
 * manifest.json for "Add to Home Screen" the way Android Chrome does).
 * Included from every page's <head> (see resources/views/*.php) rather
 * than duplicated 55 times over, since every page is its own
 * self-contained <html> document in this app (no shared layout — see
 * App\Support\View::render()) and a user can trigger "Add to Home
 * Screen" from whichever page happens to be open.
 *
 * No service worker: this is a plain installable web app (a home-screen
 * icon that launches full-screen, no browser chrome), not an offline-
 * capable one. Offline support for a manual-entry finance app raises
 * its own real question — what happens to a transaction typed in with
 * no signal, and how it reconciles once back online — that hasn't been
 * asked for and isn't solved by adding a service worker on its own.
 *
 * apple-mobile-web-app-status-bar-style is "black" rather than
 * "black-translucent": the latter draws page content underneath the
 * status bar/notch, which needs safe-area-inset padding on every page
 * to avoid the hero band's top edge being obscured — not worth taking
 * on for this pass. "black" keeps a solid status bar and needs nothing
 * else.
 */
use App\Support\View;

?>
<link rel="manifest" href="<?= View::asset('/manifest.json') ?>">
<meta name="theme-color" content="#fafaf9" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0c0a09" media="(prefers-color-scheme: dark)">

<link rel="icon" type="image/png" sizes="512x512" href="<?= View::asset('/assets/icons/icon-512.png') ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= View::asset('/assets/icons/icon-192.png') ?>">
<link rel="icon" type="image/x-icon" href="<?= View::asset('/favicon.ico') ?>">

<link rel="apple-touch-icon" href="<?= View::asset('/assets/icons/apple-touch-icon.png') ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?= View::asset('/assets/icons/apple-touch-icon-152.png') ?>">
<link rel="apple-touch-icon" sizes="167x167" href="<?= View::asset('/assets/icons/apple-touch-icon-167.png') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="MyCFO+">
<meta name="mobile-web-app-capable" content="yes">
