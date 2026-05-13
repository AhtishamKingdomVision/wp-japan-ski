<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'japan_ski' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'T1EG _c7tFlAOE)yqs*_fXLgF>,<=+3}-~HAwv6W3#%IzKR/>10<CWb!$z}W1|QB' );
define( 'SECURE_AUTH_KEY',  '&utzk;>K_+)2[q?Mx/i#2d+G@MQ^@p{#yK&VX/!KF<)i%+<)ESTyoe*qd{!%gU5>' );
define( 'LOGGED_IN_KEY',    'EOKs/RDxnzc<esQ:DFg()h `A&I6F]7<2E_I>fF5+5MN/(:WRPb[H6&P4Zo:otV2' );
define( 'NONCE_KEY',        ']R6zf,F).pGSG?5<$c&e6q,<xVB2Y?a&JE^PZIOP$C4Jk>1N?tKs:6l9B&R+A#)e' );
define( 'AUTH_SALT',        'n.;Sv=|=R|8![kr}=i1xJnI;RnKFPoaklI)WI![tl4]</]#)wHHRVHasjJqORCeM' );
define( 'SECURE_AUTH_SALT', '|!WZv7HYC@@%(w4sO/@paOF28c/fIi`GSynF@Ph6G8e-6}`yE*zgCvhrojCd5W-e' );
define( 'LOGGED_IN_SALT',   'U$]^,xE/fGTsgOaj)*wC_f7!2)^/v.9-Z%Txm,b#<HS2Z!h!U$,:16:w_!K><!$^' );
define( 'NONCE_SALT',       'W[X8d-mF/U+1T?T^#}T,?1aRp@`G>>F6YA(r2` B%+U6*]=N0P4sx&{g%iRhOejg' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
