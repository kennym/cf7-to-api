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
2. Install this plugin from WordPress.org, or copy this repository into `wp-content/plugins/cf7-to-api-basic-auth`.
3. Edit a form and open the **API Integration** tab.

Keep one copy active. WordPress.org uses the folder `cf7-to-api-basic-auth`. A checkout of this repository uses `cf7-to-api`. Version 1.5.0 and newer skip loading when another copy is already active. Delete the extra folder.

## Setup

Choose how the entry is sent:

* **Parameters.** Map each form field to an API key. GET adds the pairs to the query string. POST sends them as the body.
* **JSON or XML.** Write a template and put Contact Form 7 tags in it, including special tags such as `[_remote_ip]`, `[_url]`, and `[_date]`.

```json
{
  "name": "[your-name]",
  "email": "[your-email]",
  "accepted": "[acceptance]"
}
```

A URL that already has a query string keeps it. `https://example.com/hook?token=abc` becomes `https://example.com/hook?token=abc&name=Ada` when the mapped field is `name`.

Basic auth (`user:secret`) and Bearer auth are sent on GET and POST. When both are filled in, Bearer is used. Custom headers are one `Name: value` line each. The timeout defaults to 15 seconds and can be set from 1 to 60.

Uploaded files are sent as base64.

## Errors

A response outside HTTP 200–299, a connection error, or a rejected URL fails the submission and skips the Contact Form 7 email. The visitor sees the form’s usual error text.

**Show the API response body as the form message** uses a JSON `message` field when the body has one, and otherwise a shortened copy of the body.

The API Integration tab stores the last call for debugging. Authorization header values are left out.

`wp_http_validate_url()` allows public `http` and `https` URLs on ports 80, 443, and 8080. For a private host or another port, return the URL from `qs_cf7_api_validated_url`.

Credentials are stored in the form. Anyone who can edit that form can read them.

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

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
