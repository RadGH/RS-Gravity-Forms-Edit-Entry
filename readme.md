# RS Gravity Forms Edit Entry

This plugin allows users to edit their previous entry on a gravity form. Adds a new "Editable Form" block and supports the Gravity Forms shortcode by adding the <code>editable="true</code> property.

## Required Plugins

* Gravity Forms
* Advanced Custom Fields

## How to use

1. Install and activate the plugin
2. Modify a form on a page:
   * If using the block editor, add the block "Editable Form"
   * If using a shortcode, add the attribute: `editable="true"` and optionally a confirmation to show when editing an entry: `confirmation="Entry edited"`
3. Submit the form once so you have an entry.
4. Visit the page again. You should see the form pre-filled with your previous entry.
5. Make changes and submit the form again. The existing entry will be updated.
6. A note will be added to the entry explaining that it was updated by the user.

## Changelog

### 1.0.0

* Initial release
