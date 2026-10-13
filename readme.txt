=== Contact Form 7 to API ===
Contributors: kennymeyer
Tags: contact form 7, api, webhook, crm, rest api
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Contact Form 7 submissions to a remote API with GET or POST.

== Description ==

Contact Form 7 to API adds an **API Integration** tab to each form. When the form is submitted, the entry is sent to the URL you set.

= What you can send =

* **Parameters.** Map each form field to an API key. GET adds the pairs to the query string and sends no body. POST sends them as the body.
* **JSON.** The filled template is the request body for both GET and POST.
* **XML.** The filled template is the request body. XML is always sent as POST, and the method menu is hidden.
* **Files.** Uploads are sent as base64. A file larger than 8 MB is left out.
* **Acceptance.** In JSON, an acceptance field is a boolean: `true` when checked and `false` when it is not. In XML and in parameter mode it is the text `true` or `false`.
* **Auth.** Basic auth (`user:secret`) or a Bearer token. Bearer is used when both are filled in, and both are sent on GET and POST.
* **Headers.** One `Name: value` line each.
* **Timeout.** Defaults to 15 seconds. You can set 1 to 60 seconds on the form.

A JSON template looks like this:

`{ "name": "[your-name]", "email": "[your-email]" }`

If the URL already has a query string, mapped fields are added with `&`. `https://example.com/hook?token=abc` keeps the token, and the fields follow it.

= When the call fails =

A response outside HTTP 200–299, a connection error, or a rejected URL fails the submission and skips the Contact Form 7 email. The visitor sees the form's usual error message.

Turn on **Show the API response body as the form message** to show a JSON `message` field, or a shortened copy of any other body.

The last call is saved on the form. Open **DEBUG LOG** on the API Integration tab. Authorization header values are not stored.

= Good to know =

* Credentials are stored in the form. Anyone who can edit the form can read them.
* Keep one copy of the plugin active. WordPress.org installs it in `cf7-to-api-basic-auth`. A GitHub checkout uses `cf7-to-api`. Version 1.5.0 and newer skip loading when another copy is already active. Delete the extra folder.
* WordPress allows public http and https URLs on ports 80, 443, and 8080. For a private host or another port, return that URL from the `qs_cf7_api_validated_url` filter, and only for an address you trust.

== External service ==

This plugin does not call a service of its own. Nothing is sent until **Send to API** is turned on for that form. The request then goes to the URL entered on the API Integration tab. You choose that address. Its terms of use and privacy policy are the ones published by whoever runs it.

The request can include:

* Mapped field values, or the filled JSON or XML template.
* File contents, as base64, for files of 8 MB or less.
* Any special mail-tag you put in a template. `[_remote_ip]` is the visitor's IP address. `[_url]` is the page URL.
* The Basic or Bearer credential, in the Authorization header, when one is saved on the form.

On your own site, the API URL, credentials, templates, and field map are stored in the Contact Form 7 form. The debug log stores the last called URL, request body, header names, response code, response body, and error on that same form. Authorization header values are not stored.

Deleting the plugin removes the option that remembers dismissed notices. It leaves Contact Form 7 posts in place, including the API settings and the debug log.

== Installation ==

1. Install and activate Contact Form 7.
2. Install and activate Contact Form 7 to API. It can be activated first, and it shows a notice until Contact Form 7 is active.
3. Edit a form, open the **API Integration** tab, and save.

== Frequently Asked Questions ==

= Where are the settings? =

Edit the form and open the **API Integration** tab.

= The URL already has a query string. Will my fields replace it? =

No. Fields are appended with `&`. `https://example.com/hook?token=abc` keeps `token=abc`, and the mapped fields follow it.

= Does Basic auth work with GET? =

Yes. The Authorization header is sent for GET and POST. If both Basic and Bearer are filled in, Bearer is used.

= Can I add my own headers? =

Yes. Use the Custom headers box, one `Name: value` line each. The `qs_cf7_api_get_args` filter can still change the request for GET and POST.

= What happens when the API returns an error? =

The form fails and the email is not sent. The visitor sees the form's usual failure text, unless you turn on **Show the API response body as the form message**.

= Who can see the API credentials? =

Anyone who can edit the form. They are stored in the form, not in a separate encrypted option.

= Does JSON GET send a body? =

Yes. Parameter mode is the one that puts fields on the query string and sends no body. A JSON template is sent as the body for GET and POST. XML is always POST.

= What is removed on uninstall? =

Only the option that remembers dismissed notices. Form settings and the debug log stay on the Contact Form 7 posts.

= Can I change the request in code? =

Yes. `cf7api_create_record` replaces the payload. `qs_cf7_api_get_args`, `qs_cf7_api_get_url`, `qs_cf7_api_post_url`, and `qs_cf7_api_timeout` adjust the HTTP call. `qs_cf7_api_validated_url` accepts a URL that WordPress would otherwise reject.

== Upgrade Notice ==

= 1.5.4 =
API responses outside HTTP 200-299 fail the form and skip the email. The default timeout is 15 seconds. Re-save a form if a JSON or XML template shows extra backslashes.

= 1.5.3 =
API responses outside HTTP 200-299 fail the form and skip the email. The default timeout is 15 seconds. Re-save a form if a JSON or XML template shows extra backslashes.

= 1.5.2 =
API responses outside HTTP 200-299 fail the form and skip the email. The default timeout is 15 seconds. Re-save a form if a JSON or XML template shows extra backslashes.

= 1.5.1 =
API responses outside HTTP 200-299 fail the form and skip the email. The default timeout is 15 seconds. Re-save a form if a JSON or XML template shows extra backslashes.

= 1.5.0 =
API responses outside HTTP 200-299 now fail the form and skip the email. The default timeout is 15 seconds. Re-save a form if a JSON or XML template shows extra backslashes.

== Changelog ==

= 1.5.4 =

* Trim surrounding whitespace from the saved API endpoint URL. An already encoded %20 is left as-is.

= 1.5.3 =

* Documents what is sent to the API URL, what the debug log stores, and what uninstall leaves in place. Form and API behavior match 1.5.2.

= 1.5.2 =

* Rewrites the plugin directory readme.
* The API Integration tab script no longer depends on jQuery. Form and API behavior match 1.5.1.

= 1.5.1 =

* Maintenance release. The directory index file contains a short PHP comment so coding-standards checks can read it. Form and API behavior match 1.5.0.

= 1.5.0 =

* Requires WordPress 6.7 and PHP 7.4. Tested with WordPress 7.1 and Contact Form 7 5.9 through 6.2.
* Fixes a fatal error when two copies of the plugin are active.
* Fixes saving JSON and XML templates so quotes are no longer stored as backslashes.
* Fixes a PHP 8 error when a JSON or XML template is saved without a field map.
* Appends fields to an existing query string with `&`, and sends Basic and Bearer auth on GET.
* Encodes JSON values, so quotes, newlines, and dollar signs stay valid JSON. XML values are escaped.
* Resolves Contact Form 7 special mail-tags in templates.
* Adds a timeout field and a custom headers field. Requests use HTTP/1.1.
* A non-2xx response, a transport error, or an invalid API URL fails the submission and skips the email.
* Escapes the API tab, checks a nonce and capability before dismissing notices, and limits assets to Contact Form 7 screens.
* Removes the unused template-helper class.

= 1.4.11 =

* Support the Contact Form 7 property loader used by current CF7 releases.

= 1.4.10 =

* Option to show the API response body as the form message.

= 1.4.9 =

* Acceptance fields can be sent as JSON booleans.
* Multiple files can be sent.
* Multi-value fields in templates are joined when a single text value is required.

= 1.4.8 =

* Revert single-quote handling.

= 1.4.7 =

* Compatibility fixes.

= 1.4.5 =

* Handle line breaks.

= 1.4.4 =

* Read custom placeholders out of the template.

= 1.4.3 =

* Bearer token fix.

= 1.4.2 =

* Bug fix.

= 1.4.1 =

* WordPress 5 support.

= 1.3.0 =

* Basic auth.

= 1.2.0 =

* Checkbox arrays and the `cf7api_create_record` filter.

= 1.1.0 =

* XML, JSON, and the per-form debug log.

= 1.0.0 =

* Initial release.
