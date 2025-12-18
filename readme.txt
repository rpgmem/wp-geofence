=== WP Geofence ===
Contributors: rpgmem
Tags: geofence, geolocation, restrict content, shortcode, gutenberg
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.2.0
License: CC BY-NC-SA 4.0
License URI: https://creativecommons.org/licenses/by-nc-sa/4.0/

Show or hide content based on the visitor's geographic location and date/time restrictions.

== Description ==

WP Geofence is a powerful and lightweight tool for WordPress that allows you to control content visibility based on the user's real-time position. Using the browser's Geolocation API, the plugin validates if a user is within a specific radius of a coordinate before revealing protected content.

In addition to geolocation, you can now restrict content by specific dates and time intervals, making it perfect for local events, regional promotions, or time-sensitive location-based offers.

= Key Features =
* **Geofence Validation:** Define one or multiple circular areas (Latitude, Longitude, Radius).
* **Date & Time Restrictions:** Set start/end dates and daily time windows for content access.
* **Gutenberg Ready:** Includes a dedicated block for the block editor.
* **Shortcode Support:** Use `[geofence]` to protect any content in classic editors or widgets.
* **Intelligent Caching:** Optional local storage of coordinates to speed up validation for returning visitors.
* **Privacy Focused:** Location data is processed entirely in the user's browser; no coordinates are stored on your server.
* **Lazy Loading:** Prevents external resources (like iFrames) from loading until the location is verified.

== Installation ==

1. Upload the `wp-geofence` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Settings > WP Geofence** to configure your default areas and messages.

== Frequently Asked Questions ==

= Does it work on all browsers? =
It works on all modern browsers that support the Geolocation API. Note that most browsers require a secure connection (HTTPS) to access location data.

= Can I use multiple areas? =
Yes! You can define multiple areas in the settings or via shortcode attributes. The content will be revealed if the user is in ANY of the defined areas.

= Is the user's location private? =
Absolutely. The plugin never sends coordinates to your database. All distance calculations happen on the client-side (JavaScript).

== Screenshots ==

1. The settings page showing general configuration and the overview tab.
2. The Gutenberg block interface for easy content protection.

== Changelog ==

= 1.2.0 =
* Added date and time interval restrictions.
* Added "Restore Defaults" functionality in the admin panel.
* Improved Regex parser for better geofence area strings.

= 1.0.0 =
* Initial release with basic Geofence and Gutenberg support.

== Upgrade Notice ==

= 1.2.0 =
This version introduces time/date restrictions. Update now to use these new features.


### Notas finais sobre o ficheiro:
1.  **Stable Tag:** Mantive como `1.2.0` para coincidir com a versão que revimos.
2.  **Licença:** Refleti a licença `CC BY-NC-SA 4.0` que estava no rodapé da sua página de definições.
3.  **Tags:** Adicionei as tags mais comuns para ajudar na descoberta do plugin.

Com este ficheiro, a estrutura do seu plugin está completa e profissional. Se planeia publicá-lo no repositório oficial, lembre-se apenas de criar uma pasta `assets` no nível do SVN para colocar o ícone e o banner!