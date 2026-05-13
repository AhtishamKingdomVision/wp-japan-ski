/**
 * Main script for Duplicator Plugin
 */

// Import and initialize global namespace (must be first)
import './dupli-namespace.js';

// Import jQuery and make it globally available
import jquery from 'jquery';
window.$ = window.jQuery = jquery;

// Import Tippy and make it globally available
import tippy from 'tippy.js';
window.tippy = tippy;

// Import Handlebars dist (browser-compatible) and make it globally available
import Handlebars from 'handlebars/dist/handlebars';
window.Handlebars = Handlebars;

/**
 * Scripts
 */
import "parsleyjs";
import "@popperjs/core";
import "select2";
import "js-cookie";
import "jstree";
import "formstone";
import "formstone/dist/js/upload.js";
import "./duplicator-tooltip.js";
import "./dynamic-help.js";

/**
 * Styles
 */
import "select2/dist/css/select2.css";
import "jstree/dist/themes/default/style.css"

console.log('Duplicator Plugin vendor bundle loaded successfully');
