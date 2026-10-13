# Contact Form 7 to API

Send a Contact Form 7 submission to a remote API with GET or POST.

The WordPress.org plugin is [Contact Form 7 to API](https://wordpress.org/plugins/cf7-to-api-basic-auth/). Support requests go in the [plugin forum](https://wordpress.org/support/plugin/cf7-to-api-basic-auth/).

## Requirements

| | |
| --- | --- |
| WordPress | 6.7 or newer. Tested up to 7.1. |
| PHP | 7.4 or newer. |
| Contact Form 7 | 5.9 through 6.2. The plugin still activates without it and shows a notice. |

## Install

1. Install and activate Contact Form 7.
2. Install **Contact Form 7 to API** from WordPress.org. That copy lives in `wp-content/plugins/cf7-to-api-basic-auth`.
3. Edit a form and open the **API Integration** tab.

To run this repository instead, clone it so the folder is `wp-content/plugins/cf7-to-api`. Do not activate that folder and `cf7-to-api-basic-auth` together. Version 1.5.0 and newer skip loading when another copy is already active. Delete the extra folder. An old 1.4 copy can still stop the site if it loads after this one.

## Setup

Choose how the entry is sent:

* **Parameters.** Map each form field to an API key. GET adds the pairs to the query string and sends no body. POST sends them as the body.
* **JSON.** The filled template is the body for both GET and POST. Tags such as `[your-name]`, `[_remote_ip]`, `[_url]`, and `[_date]` are replaced.
* **XML.** The filled template is the body. XML is always sent as POST.

```json
{
  "name": "[your-name]",
  "email": "[your-email]",
  "accepted": "[acceptance]"
}
```

A URL that already has a query string keeps it. `https://example.com/hook?token=abc` becomes `https://example.com/hook?token=abc&name=Ada` when the mapped field is `name`.

Basic auth (`user:secret`) and Bearer auth are sent on GET and POST. When both are filled in, Bearer is used. Custom headers are one `Name: value` line each. The timeout defaults to 15 seconds and can be set from 1 to 60.

Uploaded files are sent as base64. A file larger than 8 MB is left out. In JSON, an acceptance field is a boolean: `true` when checked and `false` when it is not. In XML and in parameter mode it is the text `true` or `false`.

## Errors

A response outside HTTP 200–299, a connection error, or a rejected URL fails the submission and skips the Contact Form 7 email. The visitor sees the form’s usual error text.

**Show the API response body as the form message** uses a JSON `message` field when the body has one, and otherwise a shortened copy of the body.

The API Integration tab stores the last call for debugging. Authorization header values are left out.

`wp_http_validate_url()` allows public `http` and `https` URLs on ports 80, 443, and 8080. For a private host or another port, return the URL from `qs_cf7_api_validated_url`.

Credentials are stored in the form. Anyone who can edit that form can read them.

## Data sent off the site

This plugin has no service of its own. With **Send to API** turned on, the submission goes to the URL saved on that form. You choose the address. The terms and privacy policy that apply are the ones published by whoever runs it.

The request can include mapped fields or the filled template, file contents up to 8 MB as base64, and any special tag in the template. `[_remote_ip]` is the visitor's IP address. A saved Basic or Bearer credential is sent in the Authorization header.

The API URL, credentials, templates, field map, and last debug log are stored on the Contact Form 7 post. The debug log keeps the called URL, request body, header names, response code, response body, and error. It does not keep Authorization header values.

Uninstall deletes the dismissed-notices option only. Form settings and the debug log remain.

## Hooks

| Hook | Role |
| --- | --- |
| `cf7api_create_record` | Replace the record before it is sent. |
| `qs_cf7_api_before_sent_to_api` | Runs with the record, before the request. |
| `qs_cf7_api_validated_url` | Accept a URL that WordPress would reject. |
| `qs_cf7_api_get_args` | Change the `wp_remote_*` arguments for GET and POST. |
| `qs_cf7_api_get_url` | Change the final GET URL. Receives the URL and the record. |
| `qs_cf7_api_post_url` | Change the final POST URL. |
| `qs_cf7_api_timeout` | Override the timeout in seconds. |
| `qs_cf7_api_after_sent_to_api` | Runs with the record and the HTTP response. Skipped on a transport error. |
| `after_qs_cf7_api_send_lead` | Runs with the raw result, including a `WP_Error`. |
| `set_record_value` | Change one mapped value before it is placed in the payload. |

## Development

Dev tools install on PHP 8.3. The plugin itself still runs on PHP 7.4.

```bash
composer install
vendor/bin/phpcs
vendor/bin/phpunit
```

The version history for the WordPress.org package is in [readme.txt](readme.txt).

## Releasing

A change that should ship includes a file in [`.changeset`](.changeset). `npx changeset` writes it. `patch` is a fix, `minor` is a feature, and `major` is a breaking change. The sentence in the file is the changelog line. `Upgrade notice:` on its own line replaces the notice WordPress shows. Otherwise the previous notice is copied forward.

Pushing that file to `master` opens a release pull request. Merging it tags the version, without a `v` prefix, and publishes the same zip to [WordPress.org](https://wordpress.org/plugins/cf7-to-api-basic-auth/) and GitHub. The attached zip is the installable package. It unpacks as `cf7-to-api-basic-auth`.

A docs-only change that should not release uses `npx changeset --empty`. Node 22 or newer runs the Changesets CLI. The markdown file can also be committed by hand.

WordPress.org publish uses the `SVN_USERNAME` and `SVN_PASSWORD` Actions secrets. The SVN password comes from the WordPress.org profile. In the repository settings, GitHub Actions must be allowed to open pull requests.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
