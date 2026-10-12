=== Contact Form 7 to API ===
Contributors: kennymeyer
Tags: contact form 7, api, webhook, crm, rest api
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Contact Form 7 submissions to a remote API with GET or POST.

== Description ==

Contact Form 7 to API adds an **API Integration** tab to each Contact Form 7 form. When the form is submitted, the plugin sends the entry to the URL you configure.

* GET or POST.
* Parameter fields, a JSON template, or an XML template.
* Basic auth and Bearer auth, on both GET and POST.
* Custom request headers.
* Per-form timeout (default 15 seconds, maximum 60).
* Contact Form 7 special mail-tags such as `[_remote_ip]`, `[_url]`, and `[_date]` inside JSON and XML templates.
* File fields are sent as base64.
* The last call is stored on the form for debugging. Secrets from the Authorization header are not stored.

An HTTP response outside 200–299, or a transport failure, marks the submission failed and skips the Contact Form 7 email. Use “Show the API response body as the form message” when the visitor should see the API’s `message` field, or the raw body.

Basic or Bearer credentials are stored in the Contact Form 7 form. Anyone who can edit that form can read them.

Do not activate a second copy of this plugin. The WordPress.org folder is `cf7-to-api-basic-auth`. A GitHub checkout uses the folder `cf7-to-api`. Activating both used to crash the site. Starting in 1.5.0 the second copy stops loading, but you should delete the extra folder.

The API URL is checked with WordPress’ `wp_http_validate_url()`. That allows public http and https URLs on ports 80, 443, and 8080. For a private host or another port, return the original URL from the `qs_cf7_api_validated_url` filter. Only do that for addresses you trust.

== Installation ==

1. Install and activate Contact Form 7.
2. Install and activate Contact Form 7 to API.
3. Edit a form, open the API Integration tab, and save.

== Frequently Asked Questions ==

= Where are the settings? =

Edit the form and open the API Integration tab. Older readme text called this “Redirect Settings”. That name was wrong.

= The URL already has a query string. Will my fields replace it? =

No. Fields are appended with `&`. `https://example.test/build?token=abc` stays intact and the mapped fields follow it.

= Does Basic auth work with GET? =

Yes. The Authorization header is sent for GET and POST. If both Basic and Bearer are filled in, Bearer is used.

= Can I add my own headers? =

Yes. Use the Custom headers box, one `Name: value` line each. The `qs_cf7_api_get_args` filter still runs for both GET and POST and can change the request arguments.

= What happens when the API returns an error? =

Responses outside HTTP 200–299 fail the form and the email is not sent. The visitor sees the form’s “There was an error trying to send your message” text unless you enable the response-body message.

= Who can see the API credentials? =

Anyone who can edit the form. They are stored in the form, not in a separate encrypted option.

== Upgrade Notice ==

= 1.5.1 =
API responses outside HTTP 200-299 fail the form and skip the email. The default timeout is 15 seconds. Re-save a form if a JSON or XML template shows extra backslashes.

= 1.5.0 =
API responses outside HTTP 200-299 now fail the form and skip the email. The default timeout is 15 seconds. Re-save a form if a JSON or XML template shows extra backslashes.

== Changelog ==

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
