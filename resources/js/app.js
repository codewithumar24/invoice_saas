/*
 | Front-end entry. Plain modular JavaScript, no framework.
 | Each module looks for its own data-* hooks, so pages only pay for what they use.
 */
import './core/theme';
import './core/forms';
import './core/ui';
import './core/toast';
import './components/bulk';
import './components/command-palette';
import './components/table-columns';

// Feature modules are loaded only on pages that need them
if (document.querySelector('[data-line-items]')) import('./invoice/items');
if (document.querySelector('[data-chart]')) import('./dashboard/charts');
if (document.querySelector('[data-qr]')) import('./settings/qr');
if (document.querySelector('[data-timer], [data-project-tasks]')) import('./dashboard/timer');
if (document.querySelector('[data-builder]')) import('./settings/builder');
if (document.querySelector('[data-email-preview]')) import('./invoice/email-preview');
if (document.querySelector('[data-quick-customer]')) import('./customer/quick-create');
if (document.querySelector('[data-sortable]')) import('./dashboard/widgets');
