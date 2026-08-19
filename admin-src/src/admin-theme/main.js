// This bundle has no Vue app — it exists only to compile theme.css into
// its own file (admin-theme.css), enqueued as a second stylesheet by
// WS_Shortcode_Admin alongside the shared admin.css. See that class and
// WS_Shortcode_Base::render() for why this can't just live inside
// admin.css: that file is also loaded directly by two PHP-only pages
// (Settings, Onboarding Wizard) that have nothing to do with this Vue
// app's frontend dark theme.
import './theme.css';
