<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wp_helmetsan_com' );

/** Database username */
define( 'DB_USER', 'wp_helmetsan_com' );

/** Database password */
define( 'DB_PASSWORD', 'CkFfDHxd4xCTAAmoC4o1MfGDLMHZIxl9' );

/** Database hostname */
define( 'DB_HOST', 'localhost:/run/mysqld/mysqld.sock' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'EYY]lS0OrQ8?9$wYLCpmadc4E059MT4:k=)|LW{.,aIIFbzV[b6dLRy~.eRn0;&g' );
define( 'SECURE_AUTH_KEY',   'Cl4W]taR]eL1*6N;7^d_Mof{n^myD6x1qOkQ-y10W]k3eO(3e?&HcFtphb1y*oQK' );
define( 'LOGGED_IN_KEY',     '-d<I,au_(by<;-B$}i|I{,@/j]&$6xy5xBJYeYBg)2A0?,?csWTwqZM7UmR<%%cB' );
define( 'NONCE_KEY',         '4!uu(to]J=@|fp,(V%([m(|(Yx`CrgH/_2LA^Myl2%{v^G.{>4Ig!UzMRMIW@8gJ' );
define( 'AUTH_SALT',         '*Kc*rsWsq1jin6xs3%tH@jMRON|hR~qK2*=RQyBf,*)AdY|IA#76!wP`*+LqFlr@' );
define( 'SECURE_AUTH_SALT',  '5V5lZ2;H-kc:2`->(,#lU~G=bRTo$]zp/E)*G5L|Nbk)t$>P.Pj{C~IP/$,BD;wg' );
define( 'LOGGED_IN_SALT',    '7eG@.J*$I1k[cg*=+-ElGL QnhzW% e/xrfoO6.o9^:Fm|*#zf1g!:9Cp=!udsd_' );
define( 'NONCE_SALT',        '#YTw~O4JE.|(&)/U||w&=`k{X0QS4h-]Y*/hL;fO*^b6`(;xX}G,QXQh=m`;*u|r' );
define( 'WP_CACHE_KEY_SALT', 'helmetsan.com:' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

define( 'WP_MEMORY_LIMIT', '1024M' );
define( 'WP_MAX_MEMORY_LIMIT', '1024M' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
define( 'DISABLE_WP_CRON', true );
define( 'WP_CACHE', true );
define( 'WP_AUTO_UPDATE_CORE', true );
define( 'DISALLOW_FILE_EDIT', true );
define( 'FS_METHOD', 'direct' );
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
define( 'WP_REDIS_PASSWORD', '636hTupeqNsyEIBRdHnqM6q39WmDqJ6K0c6or2KWYRfP2HCY' );
define( 'WP_REDIS_DATABASE', 4 );
define( 'WP_REDIS_PREFIX', 'helmetsan_com:' );
define( 'RT_WP_NGINX_HELPER_CACHE_PATH', '/var/cache/nginx/microcache' );
/* Cloudflare Master Token Configuration */
define( 'HELMETSAN_CLOUDFLARE_ZONE_ID', 'f098a57b228462adf58dcde8b65c77f6' );
define( 'HELMETSAN_CLOUDFLARE_ACCOUNT_ID', 'a06a4ae5652491dfb8376dc23488f6a0' );
define( 'HELMETSAN_CLOUDFLARE_API_TOKEN', 'cfat_REDACTED_CLOUDFLARE_API_TOKEN' );
define( 'HELMETSAN_AMZ_CREATOR_CLIENT_ID', 'amzn1.application-oa2-client.REDACTED' );
define( 'HELMETSAN_AMZ_CREATOR_CLIENT_SECRET', 'amzn1.oa2-cs.v1.REDACTED' );
define( 'HELMETSAN_AMZ_CREATOR_TAG', 'vtete-20' );
define( 'HELMETSAN_AMZ_CREATOR_VERSION', 'v3.1' );
define( 'HELMETSAN_R2_ACCESS_KEY_ID', 'REDACTED_R2_ACCESS_KEY' );
define( 'HELMETSAN_R2_SECRET_ACCESS_KEY', 'REDACTED_R2_SECRET_ACCESS_KEY' );
define( 'HELMETSAN_R2_ENDPOINT', 'https://a06a4ae5652491dfb8376dc23488f6a0.r2.cloudflarestorage.com' );
define( 'HELMETSAN_R2_BUCKET', 'helmetsan' );
define( 'HELMETSAN_R2_PUBLIC_URL', 'https://assets.helmetsan.com' );

define( 'HELMETSAN_GEO_IP_PRICING', true );
define( 'HELMETSAN_ACTIVE_CACHE_PUSH', true );
define( 'HELMETSAN_PREWARM_BYPASS_TOKEN', 'hs_warm_secret_99812' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
