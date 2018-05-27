=== Contact form 7 TO API ===
Tags: contact form 7 to api,contact form 7,cf7 api,cf7 get,contact form 7 post,contact form7 get,contact form 7 remote, Contact form 7 crm, contact form 7 integration,contact form 7 integrations, contact form 7 rest api,
Requires at least: 4.7.0
Tested up to: 4.9.4
Stable tag: 1.2.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

An addon to transmit contact form 7 entries to remote API using POST or GET.

== Description ==

Adds an option to send leads to remote API's such as CRM's ERP's and other remote systems using POST/GET.
NOTE: This plugin requires Contact Form 7 version 4.2 or later.

Supports XML and JSON

== Usage ==

Simply go to your form settings, choose the "Redirect Settings" tab and set your required parameters,
1. Chose wether the specific form will use the API integrations
2. Type the API url
3. Select the method (POST/GET)
4. map the form fields (Each field that you use on the form will be availble on this tab after saving the form)
5. choose wether you wish to debug and save the last transmited value.

== Installation ==

Installing Contact form 7 TO API  can be done either by searching for "Contact form 7 TO API" via the "Plugins > Add New" screen in your WordPress dashboard, or by using the following steps:

1. Download the plugin via WordPress.org.
1. Upload the ZIP file through the "Plugins" > Add New > Upload" screen in your WordPress dashboard.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Visit the settings screen and configure, as desired.

== Frequently Asked Questions ==

= How can i redirect the user after success ? =
You can use another plugin for that - Contact Form 7 Redirection

https://wordpress.org/plugins/wpcf7-redirect/

= How can i set Extra parameters ? =

You could set hidden fields for that

https://contactform7.com/hidden-field/

OR

simply append the constant parameters to the url
For example:

http://my-api-url?const1=some_value&const2=some_value


== Screenshots ==

1. A view of the interface - send parameters
2. JSON
3. XML
3. Debug log view

== Changelog ==

= 1.2.0 =
* Added better support for checkbox arrays and radio buttons
* Added record filter to override record structure "cf7api_create_record" 

= 1.1.1 =
* Fix version number

= 1.1.0 =
* Added send XML option.
* Added send JSON option.
* Added error log.
* Debug log for each form
* Debug log view changed
* Debug log is now saved anyway

= 1.0.1 =
* Fix code errors and notices.

= 1.0.0 =
* Initial release.
