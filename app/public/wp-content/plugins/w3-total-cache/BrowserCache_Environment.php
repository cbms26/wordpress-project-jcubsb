<?php
/**
 * File: BrowserCache_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class BrowserCache_Environment
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Squiz.Strings.DoubleQuoteUsage.NotRequired
 */
class BrowserCache_Environment {
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'w3tc_cdn_rules_section', array( $this, 'w3tc_cdn_rules_section' ), 10, 2 );
	}

	/**
	 * Fixes environment in each wp-admin request
	 *
	 * @param Config $config           Config.
	 * @param bool   $force_all_checks Force all checks flag.
	 *
	 * @throws Util_Environment_Exceptions Environment exceptions.
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();

		if ( $config->get_boolean( 'config.check' ) || $force_all_checks ) {
			if ( $config->get_boolean( 'browsercache.enabled' ) ) {
				$this->rules_cache_add( $config, $exs );
			} else {
				$this->rules_cache_remove( $exs );
			}
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes environment once event occurs
	 *
	 * @param Config $config     Config.
	 * @param string $event      Event.
	 * @param Config $old_config Old config.
	 *
	 * @throws Util_Environment_Exceptions Environment Exceptions.
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
	}

	/**
	 * Fixes environment after plugin deactivation
	 *
	 * @throws Util_Environment_Exceptions Environment Exceptions.
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		$this->rules_cache_remove( $exs );

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Returns required rules for module
	 *
	 * @param Config $config Config.
	 *
	 * @return array
	 */
	public function get_required_rules( $config ) {
		if ( ! $config->get_boolean( 'browsercache.enabled' ) ) {
			return array();
		}

		$mime_types = $this->get_mime_types();

		switch ( true ) {
			case Util_Environment::is_apache():
				$generator_apache = new BrowserCache_Environment_Apache( $config );
				$rewrite_rules    = array(
					array(
						'filename' => Util_Rule::get_apache_rules_path(),
						'content'  => W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE . "\n" .
							$this->rules_cache_generate_apache( $config ) .
							$generator_apache->rules_no404wp( $mime_types ) .
							W3TC_MARKER_END_BROWSERCACHE_CACHE . "\n",
					),
				);
				break;

			case Util_Environment::is_litespeed():
				$generator_litespeed = new BrowserCache_Environment_LiteSpeed( $config );
				$rewrite_rules       = $generator_litespeed->get_required_rules( $mime_types );
				break;

			case Util_Environment::is_nginx():
				$generator_nginx = new BrowserCache_Environment_Nginx( $config );
				$rewrite_rules   = $generator_nginx->get_required_rules( $mime_types );
				break;

			default:
				$rewrite_rules = array();
		}

		return $rewrite_rules;
	}

	/**
	 * Returns mime types
	 *
	 * @return array
	 */
	public function get_mime_types() {
		$a = Util_Mime::sections_to_mime_types_map();

		$other_compression = $a['other'];
		unset( $other_compression['asf|asx|wax|wmv|wmx'] );
		unset( $other_compression['avi'] );
		unset( $other_compression['avif'] );
		unset( $other_compression['avifs'] );
		unset( $other_compression['divx'] );
		unset( $other_compression['gif'] );
		unset( $other_compression['br'] );
		unset( $other_compression['gz|gzip'] );
		unset( $other_compression['jpg|jpeg|jpe'] );
		unset( $other_compression['mid|midi'] );
		unset( $other_compression['mov|qt'] );
		unset( $other_compression['mp3|m4a'] );
		unset( $other_compression['mp4|m4v'] );
		unset( $other_compression['ogv'] );
		unset( $other_compression['mpeg|mpg|mpe'] );
		unset( $other_compression['png'] );
		unset( $other_compression['ra|ram'] );
		unset( $other_compression['tar'] );
		unset( $other_compression['webp'] );
		unset( $other_compression['wma'] );
		unset( $other_compression['zip'] );

		$a['other_compression'] = $other_compression;

		return $a;
	}

	/**
	 * Generate rules for FTP upload
	 *
	 * @param Config $config Config.
	 *
	 * @return string
	 */
	public function rules_cache_generate_for_ftp( $config ) {
		return $this->rules_cache_generate_apache( $config );
	}

	/**
	 * Writes cache rules
	 *
	 * @param Config $config Config.
	 * @param array  $exs    Extras.
	 *
	 * @throws Util_WpFile_FilesystemOperationException FilesystemOperation Exceptions.
	 * With S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_cache_add( $config, $exs ) {
		$rules = $this->get_required_rules( $config );

		foreach ( $rules as $i ) {
			Util_Rule::add_rules(
				$exs,
				$i['filename'],
				$i['content'],
				W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE,
				W3TC_MARKER_END_BROWSERCACHE_CACHE,
				array(
					W3TC_MARKER_BEGIN_MINIFY_CORE  => 0,
					W3TC_MARKER_BEGIN_PGCACHE_CORE => 0,
					W3TC_MARKER_BEGIN_WORDPRESS    => 0,
					W3TC_MARKER_END_PGCACHE_CACHE  => strlen( W3TC_MARKER_END_PGCACHE_CACHE ) + 1,
					W3TC_MARKER_END_MINIFY_CACHE   => strlen( W3TC_MARKER_END_MINIFY_CACHE ) + 1,
				)
			);
		}
	}

	/**
	 * Removes cache directives
	 *
	 * @param array $exs Extras.
	 *
	 * @throws Util_WpFile_FilesystemOperationException FilesystemOperation Exceptions.
	 * With S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_cache_remove( $exs ) {
		$filenames = array();

		switch ( true ) {
			case Util_Environment::is_apache():
				$filenames[] = Util_Rule::get_apache_rules_path();
				break;

			case Util_Environment::is_litespeed():
				$filenames[] = Util_Rule::get_apache_rules_path();
				$filenames[] = Util_Rule::get_litespeed_rules_path();
				break;

			case Util_Environment::is_nginx():
				$filenames[] = Util_Rule::get_nginx_rules_path();
				break;
		}

		foreach ( $filenames as $i ) {
			Util_Rule::remove_rules(
				$exs,
				$i,
				W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE,
				W3TC_MARKER_END_BROWSERCACHE_CACHE
			);
		}
	}

	/**
	 * Returns cache rules.
	 *
	 * @param Config $config Configuration.
	 *
	 * @return string
	 */
	private function rules_cache_generate_apache( Config $config ): string {
		$mime_types2             = $this->get_mime_types();
		$cssjs_types             = $mime_types2['cssjs'];
		$cssjs_types             = array_unique( $cssjs_types );
		$html_types              = $mime_types2['html'];
		$other_types             = $mime_types2['other'];
		$other_compression_types = $mime_types2['other_compression'];
		$cssjs_expires           = $config->get_boolean( 'browsercache.cssjs.expires' );
		$html_expires            = $config->get_boolean( 'browsercache.html.expires' );
		$other_expires           = $config->get_boolean( 'browsercache.other.expires' );
		$cssjs_lifetime          = $config->get_integer( 'browsercache.cssjs.lifetime' );
		$html_lifetime           = $config->get_integer( 'browsercache.html.lifetime' );
		$other_lifetime          = $config->get_integer( 'browsercache.other.lifetime' );
		$compatibility           = $config->get_boolean( 'pgcache.compatibility' );
		$mime_types              = array();
		$rules                   = '';

		// For mod_mime and mod_expires.
		if ( $cssjs_expires && $cssjs_lifetime ) {
			$mime_types = array_merge( $mime_types, $cssjs_types );
		}

		if ( $html_expires && $html_lifetime ) {
			$mime_types = array_merge( $mime_types, $html_types );
		}

		if ( $other_expires && $other_lifetime ) {
			$mime_types = array_merge( $mime_types, $other_types );
		}

		// Rules for mod_mime.
		if ( count( $mime_types ) ) {
			$rules_mime = "<IfModule mod_mime.c>\n";

			foreach ( $mime_types as $ext => $mime_type ) {
				$extensions = explode( '|', $ext );

				if ( ! is_array( $mime_type ) ) {
					$mime_type = (array) $mime_type;
				}

				foreach ( $mime_type as $mime_type2 ) {
					$rules_mime .= '    AddType ' . $mime_type2;

					foreach ( $extensions as $extension ) {
						$rules_mime .= ' .' . $extension;
					}

					$rules_mime .= "\n";
				}
			}

			$rules_mime .= "</IfModule>\n";

			// Rules for mod_expires.
			$rules_mime .= "<IfModule mod_expires.c>\n";
			$rules_mime .= "    ExpiresActive On\n";

			if ( $cssjs_expires && $cssjs_lifetime ) {
				foreach ( $cssjs_types as $mime_type ) {
					$rules_mime .= '    ExpiresByType ' . $mime_type . ' A' . $cssjs_lifetime . "\n";
				}
			}

			if ( $html_expires && $html_lifetime ) {
				foreach ( $html_types as $mime_type ) {
					$rules_mime .= '    ExpiresByType ' . $mime_type . ' A' . $html_lifetime . "\n";
				}
			}

			if ( $other_expires && $other_lifetime ) {
				foreach ( $other_types as $mime_type ) {
					if ( is_array( $mime_type ) ) {
						foreach ( $mime_type as $mime_type2 ) {
							$rules_mime .= '    ExpiresByType ' . $mime_type2 . ' A' . $other_lifetime . "\n";
						}
					} else {
						$rules_mime .= '    ExpiresByType ' . $mime_type . ' A' . $other_lifetime . "\n";
					}
				}
			}

			$rules_mime .= "</IfModule>\n";

			/**
			 * Filter: w3tc_browsercache_rules_apache_mime
			 *
			 * @since 2.8.0
			 *
			 * @param string $rules_mime Apache rules for MIME types.
			 * @return string
			 */
			$rules .= apply_filters( 'w3tc_browsercache_rules_apache_mime', $rules_mime );

			unset( $rules_mime );
		}

		// For mod_brotli.
		$cssjs_brotli = $config->get_boolean( 'browsercache.cssjs.brotli' );
		$html_brotli  = $config->get_boolean( 'browsercache.html.brotli' );
		$other_brotli = $config->get_boolean( 'browsercache.other.brotli' );

		if ( $cssjs_brotli || $html_brotli || $other_brotli ) {
			$brotli_types = array();

			if ( $cssjs_brotli ) {
				$brotli_types = array_merge( $brotli_types, $cssjs_types );
			}

			if ( $html_brotli ) {
				$brotli_types = array_merge( $brotli_types, $html_types );
			}

			if ( $other_brotli ) {
				$brotli_types = array_merge( $brotli_types, $other_compression_types );
			}

			// Rules for mod_brotli.
			$rules_brotli = "<IfModule mod_brotli.c>\n";

			if ( version_compare( Util_Environment::get_server_version(), '2.3.7', '>=' ) ) {
				$rules_brotli .= "    <IfModule mod_filter.c>\n";
			}

			$rules_brotli .= "        AddOutputFilterByType BROTLI_COMPRESS " . implode( ' ', $brotli_types ) . "\n";
			$rules_brotli .= "    <IfModule mod_mime.c>\n";
			$rules_brotli .= "        # BROTLI_COMPRESS by extension\n";
			$rules_brotli .= "        AddOutputFilter BROTLI_COMPRESS js css htm html xml\n";
			$rules_brotli .= "    </IfModule>\n";

			if ( version_compare( Util_Environment::get_server_version(), '2.3.7', '>=' ) ) {
				$rules_brotli .= "    </IfModule>\n";
			}

			$rules_brotli .= "</IfModule>\n";

			/**
			 * Filter: w3tc_browsercache_rules_apache_brotli
			 *
			 * @since 2.8.0
			 *
			 * @param string $rules_brotli Apache rules for mod_brotli.
			 * @return string
			 */
			$rules .= apply_filters( 'w3tc_browsercache_rules_apache_brotli', $rules_brotli );

			unset( $rules_brotli );
		}

		// For mod_deflate.
		$cssjs_compression = $config->get_boolean( 'browsercache.cssjs.compression' );
		$html_compression  = $config->get_boolean( 'browsercache.html.compression' );
		$other_compression = $config->get_boolean( 'browsercache.other.compression' );

		if ( $cssjs_compression || $html_compression || $other_compression ) {
			$compression_types = array();

			if ( $cssjs_compression ) {
				$compression_types = array_merge( $compression_types, $cssjs_types );
			}

			if ( $html_compression ) {
				$compression_types = array_merge( $compression_types, $html_types );
			}

			if ( $other_compression ) {
				$compression_types = array_merge( $compression_types, $other_compression_types );
			}

			// Rules for mod_deflate.
			$rules_deflate = "<IfModule mod_deflate.c>\n";

			if ( $compatibility ) {
				$rules_deflate .= "    <IfModule mod_setenvif.c>\n";
				$rules_deflate .= "        BrowserMatch ^Mozilla/4 gzip-only-text/html\n";
				$rules_deflate .= "        BrowserMatch ^Mozilla/4\\.0[678] no-gzip\n";
				$rules_deflate .= "        BrowserMatch \\bMSIE !no-gzip !gzip-only-text/html\n";
				$rules_deflate .= "        BrowserMatch \\bMSI[E] !no-gzip !gzip-only-text/html\n";
				$rules_deflate .= "    </IfModule>\n";
			}

			if ( version_compare( Util_Environment::get_server_version(), '2.3.7', '>=' ) ) {
				$rules_deflate .= "    <IfModule mod_filter.c>\n";
			}

			$rules_deflate .= "        AddOutputFilterByType DEFLATE " . implode( ' ', $compression_types ) . "\n";
			$rules_deflate .= "    <IfModule mod_mime.c>\n";
			$rules_deflate .= "        # DEFLATE by extension\n";
			$rules_deflate .= "        AddOutputFilter DEFLATE js css htm html xml\n";
			$rules_deflate .= "    </IfModule>\n";

			if ( version_compare( Util_Environment::get_server_version(), '2.3.7', '>=' ) ) {
				$rules_deflate .= "    </IfModule>\n";
			}

			$rules_deflate .= "</IfModule>\n";

			/**
			 * Filter: w3tc_browsercache_rules_apache_deflate
			 *
			 * @since 2.8.0
			 *
			 * @param string $rules_deflate Apache rules for mod_deflate.
			 * @return string
			 */
			$rules .= apply_filters( 'w3tc_browsercache_rules_apache_deflate', $rules_deflate );

			unset( $rules_deflate );
		}

		/* Rules for MIME types CSS/JS, HTML, and other. */

		/**
		 * Filter: w3tc_browsercache_rules_apache_cssjs
		 *
		 * @since 2.8.0
		 *
		 * @param string $this->_rules_cache_generate_apache_for_type( $config, $mime_types2['cssjs'], 'cssjs' ) Apache rules for CSS/JS MIME types.
		 * @return string
		 */
		$rules .= apply_filters( 'w3tc_browsercache_rules_apache_cssjs', $this->_rules_cache_generate_apache_for_type( $config, $mime_types2['cssjs'], 'cssjs' ) );

		/**
		 * Filter: w3tc_browsercache_rules_apache_html
		 *
		 * @since 2.8.0
		 *
		 * @param string $this->_rules_cache_generate_apache_for_type( $config, $mime_types2['html'], 'html' ) Apache rules for HTML MIME types.
		 * @return string
		 */
		$rules .= apply_filters( 'w3tc_browsercache_rules_apache_html', $this->_rules_cache_generate_apache_for_type( $config, $mime_types2['html'], 'html' ) );

		/**
		 * Filter: w3tc_browsercache_rules_apache_other
		 *
		 * @since 2.8.0
		 *
		 * @param string $this->_rules_cache_generate_apache_for_type( $config, $mime_types2['other'], 'other' ) Apache rules for other MIME types.
		 * @return string
		 */
		$rules .= apply_filters( 'w3tc_browsercache_rules_apache_other', $this->_rules_cache_generate_apache_for_type( $config, $mime_types2['other'], 'other' ) );

		// For mod_headers.
		if ( $config->get_boolean( 'browsercache.hsts' ) ||
			$config->get_boolean( 'browsercache.security.xfo' ) ||
			$config->get_boolean( 'browsercache.security.xss' ) ||
			$config->get_boolean( 'browsercache.security.xcto' ) ||
			$config->get_boolean( 'browsercache.security.pkp' ) ||
			$config->get_boolean( 'browsercache.security.referrer.policy' ) ||
			$config->get_boolean( 'browsercache.security.csp' ) ||
			$config->get_boolean( 'browsercache.security.cspro' ) ||
			$config->get_boolean( 'browsercache.security.fp' )
		) {
			$lifetime = $config->get_integer( 'browsercache.other.lifetime' );

			// Rules for mod_headers.
			$rules_headers = "<IfModule mod_headers.c>\n";

			if ( $config->get_boolean( 'browsercache.hsts' ) ) {
				$dir            = $config->get_string( 'browsercache.security.hsts.directive' );
				$rules_headers .= '    Header always set Strict-Transport-Security "max-age=' . $lifetime .
					( strpos( $dir, 'inc' ) ? '; includeSubDomains' : '' ) . ( strpos( $dir, 'pre' ) ? '; preload' : '' ) . "\"\n";
			}

			if ( $config->get_boolean( 'browsercache.security.xfo' ) ) {
				$dir = $config->get_string( 'browsercache.security.xfo.directive' );
				$url = trim( $config->get_string( 'browsercache.security.xfo.allow' ) );

				if ( empty( $url ) ) {
					$url = Util_Environment::home_url_maybe_https();
				}

				$rules_headers .= '    Header always append X-Frame-Options "' .
					( 'same' === $dir ? 'SAMEORIGIN' : ( 'deny' === $dir ? 'DENY' : 'ALLOW-FROM' . $url ) ) . "\"\n";
			}

			if ( $config->get_boolean( 'browsercache.security.xss' ) ) {
				$dir            = $config->get_string( 'browsercache.security.xss.directive' );
				$rules_headers .= '    Header set X-XSS-Protection "' . ( 'block' === $dir ? '1; mode=block' : $dir ) . "\"\n";

			}

			if ( $config->get_boolean( 'browsercache.security.xcto' ) ) {
				$rules_headers .= "    Header set X-Content-Type-Options \"nosniff\"\n";
			}

			if ( $config->get_boolean( 'browsercache.security.pkp' ) ) {
				$pin            = trim( $config->get_string( 'browsercache.security.pkp.pin' ) );
				$pinbak         = trim( $config->get_string( 'browsercache.security.pkp.pin.backup' ) );
				$extra          = $config->get_string( 'browsercache.security.pkp.extra' );
				$url            = trim( $config->get_string( 'browsercache.security.pkp.report.url' ) );
				$rep_only       = '1' === $config->get_string( 'browsercache.security.pkp.report.only' ) ? true : false;
				$rules_headers .= '    Header set ' . ( $rep_only ? 'Public-Key-Pins-Report-Only' : 'Public-Key-Pins' ) .
					' "pin-sha256="$pin"; pin-sha256="$pinbak"; max-age=' . $lifetime . ( strpos( $extra, 'inc' ) ? '; includeSubDomains' : '' ) .
					( ! empty( $url ) ? '; report-uri="$url"' : '' ) . "\"\n";
			}

			if ( $config->get_boolean( 'browsercache.security.referrer.policy' ) ) {
				$dir            = $config->get_string( 'browsercache.security.referrer.policy.directive' );
				$rules_headers .= '    Header set Referrer-Policy "' . ( empty( $dir ) ? '' : $dir ) . "\"\n";
			}

			if ( $config->get_boolean( 'browsercache.security.csp' ) ) {
				$base            = trim( $config->get_string( 'browsercache.security.csp.base' ) );
				$reporturi       = trim( $config->get_string( 'browsercache.security.csp.reporturi' ) );
				$reportto        = trim( $config->get_string( 'browsercache.security.csp.reportto' ) );
				$frame           = trim( $config->get_string( 'browsercache.security.csp.frame' ) );
				$connect         = trim( $config->get_string( 'browsercache.security.csp.connect' ) );
				$font            = trim( $config->get_string( 'browsercache.security.csp.font' ) );
				$script          = trim( $config->get_string( 'browsercache.security.csp.script' ) );
				$style           = trim( $config->get_string( 'browsercache.security.csp.style' ) );
				$img             = trim( $config->get_string( 'browsercache.security.csp.img' ) );
				$media           = trim( $config->get_string( 'browsercache.security.csp.media' ) );
				$object          = trim( $config->get_string( 'browsercache.security.csp.object' ) );
				$plugin          = trim( $config->get_string( 'browsercache.security.csp.plugin' ) );
				$form            = trim( $config->get_string( 'browsercache.security.csp.form' ) );
				$frame_ancestors = trim( $config->get_string( 'browsercache.security.csp.frame.ancestors' ) );
				$sandbox         = trim( $config->get_string( 'browsercache.security.csp.sandbox' ) );
				$child           = trim( $config->get_string( 'browsercache.security.csp.child' ) );
				$manifest        = trim( $config->get_string( 'browsercache.security.csp.manifest' ) );
				$scriptelem      = trim( $config->get_string( 'browsercache.security.csp.scriptelem' ) );
				$scriptattr      = trim( $config->get_string( 'browsercache.security.csp.scriptattr' ) );
				$styleelem       = trim( $config->get_string( 'browsercache.security.csp.styleelem' ) );
				$styleattr       = trim( $config->get_string( 'browsercache.security.csp.styleattr' ) );
				$worker          = trim( $config->get_string( 'browsercache.security.csp.worker' ) );
				$default         = trim( $config->get_string( 'browsercache.security.csp.default' ) );

				$dir = rtrim(
					( ! empty( $base ) ? "base-uri $base; " : '' ) .
						( ! empty( $reporturi ) ? "report-uri $reporturi; " : '' ) .
						( ! empty( $reportto ) ? "report-to $reportto; " : '' ) .
						( ! empty( $frame ) ? "frame-src $frame; " : '' ) .
						( ! empty( $connect ) ? "connect-src $connect; " : '' ) .
						( ! empty( $font ) ? "font-src $font; " : '' ) .
						( ! empty( $script ) ? "script-src $script; " : '' ) .
						( ! empty( $style ) ? "style-src $style; " : '' ) .
						( ! empty( $img ) ? "img-src $img; " : '' ) .
						( ! empty( $media ) ? "media-src $media; " : '' ) .
						( ! empty( $object ) ? "object-src $object; " : '' ) .
						( ! empty( $plugin ) ? "plugin-types $plugin; " : '' ) .
						( ! empty( $form ) ? "form-action $form; " : '' ) .
						( ! empty( $frame_ancestors ) ? "frame-ancestors $frame_ancestors; " : '' ) .
						( ! empty( $sandbox ) ? "sandbox $sandbox; " : '' ) .
						( ! empty( $child ) ? "child-src $child; " : '' ) .
						( ! empty( $manifest ) ? "manifest-src $manifest; " : '' ) .
						( ! empty( $scriptelem ) ? "script-src-elem $scriptelem; " : '' ) .
						( ! empty( $scriptattr ) ? "script-src-attr $scriptattr; " : '' ) .
						( ! empty( $styleelem ) ? "style-src-elem $styleelem; " : '' ) .
						( ! empty( $styleattr ) ? "style-src-attr $styleattr; " : '' ) .
						( ! empty( $worker ) ? "worker-src $worker; " : '' ) .
						( ! empty( $default ) ? "default-src $default;" : '' ),
					'; '
				);

				if ( ! empty( $dir ) ) {
					$rules_headers .= '    Header set Content-Security-Policy "' . $dir . "\"\n";
				}
			}

			if ( $config->get_boolean( 'browsercache.security.cspro' ) && ( ! empty( $config->get_string( 'browsercache.security.cspro.reporturi' ) ) || ! empty( $config->get_string( 'browsercache.security.cspro.reportto' ) ) ) ) {
				$base            = trim( $config->get_string( 'browsercache.security.cspro.base' ) );
				$reporturi       = trim( $config->get_string( 'browsercache.security.cspro.reporturi' ) );
				$reportto        = trim( $config->get_string( 'browsercache.security.cspro.reportto' ) );
				$frame           = trim( $config->get_string( 'browsercache.security.cspro.frame' ) );
				$connect         = trim( $config->get_string( 'browsercache.security.cspro.connect' ) );
				$font            = trim( $config->get_string( 'browsercache.security.cspro.font' ) );
				$script          = trim( $config->get_string( 'browsercache.security.cspro.script' ) );
				$style           = trim( $config->get_string( 'browsercache.security.cspro.style' ) );
				$img             = trim( $config->get_string( 'browsercache.security.cspro.img' ) );
				$media           = trim( $config->get_string( 'browsercache.security.cspro.media' ) );
				$object          = trim( $config->get_string( 'browsercache.security.cspro.object' ) );
				$plugin          = trim( $config->get_string( 'browsercache.security.cspro.plugin' ) );
				$form            = trim( $config->get_string( 'browsercache.security.cspro.form' ) );
				$frame_ancestors = trim( $config->get_string( 'browsercache.security.cspro.frame.ancestors' ) );
				$sandbox         = trim( $config->get_string( 'browsercache.security.cspro.sandbox' ) );
				$child           = trim( $config->get_string( 'browsercache.security.cspro.child' ) );
				$manifest        = trim( $config->get_string( 'browsercache.security.cspro.manifest' ) );
				$scriptelem      = trim( $config->get_string( 'browsercache.security.cspro.scriptelem' ) );
				$scriptattr      = trim( $config->get_string( 'browsercache.security.cspro.scriptattr' ) );
				$styleelem       = trim( $config->get_string( 'browsercache.security.cspro.styleelem' ) );
				$scriptelem      = trim( $config->get_string( 'browsercache.security.cspro.styleattr' ) );
				$worker          = trim( $config->get_string( 'browsercache.security.cspro.worker' ) );
				$default         = trim( $config->get_string( 'browsercache.security.cspro.default' ) );
				$dir             = rtrim(
					( ! empty( $base ) ? "base-uri $base; " : '' ) .
						( ! empty( $reporturi ) ? "report-uri $reporturi; " : '' ) .
						( ! empty( $reportto ) ? "report-to $reportto; " : '' ) .
						( ! empty( $frame ) ? "frame-src $frame; " : '' ) .
						( ! empty( $connect ) ? "connect-src $connect; " : '' ) .
						( ! empty( $font ) ? "font-src $font; " : '' ) .
						( ! empty( $script ) ? "script-src $script; " : '' ) .
						( ! empty( $style ) ? "style-src $style; " : '' ) .
						( ! empty( $img ) ? "img-src $img; " : '' ) .
						( ! empty( $media ) ? "media-src $media; " : '' ) .
						( ! empty( $object ) ? "object-src $object; " : '' ) .
						( ! empty( $plugin ) ? "plugin-types $plugin; " : '' ) .
						( ! empty( $form ) ? "form-action $form; " : '' ) .
						( ! empty( $frame_ancestors ) ? "frame-ancestors $frame_ancestors; " : '' ) .
						( ! empty( $sandbox ) ? "sandbox $sandbox; " : '' ) .
						( ! empty( $child ) ? "child-src $child; " : '' ) .
						( ! empty( $manifest ) ? "manifest-src $manifest; " : '' ) .
						( ! empty( $scriptelem ) ? "script-src-elem $scriptelem; " : '' ) .
						( ! empty( $scriptattr ) ? "script-src-attr $scriptattr; " : '' ) .
						( ! empty( $styleelem ) ? "style-src-elem $styleelem; " : '' ) .
						( ! empty( $styleattr ) ? "style-src-attr $styleattr; " : '' ) .
						( ! empty( $worker ) ? "worker-src $worker; " : '' ) .
						( ! empty( $default ) ? "default-src $default;" : '' ),
					'; '
				);

				if ( ! empty( $dir ) ) {
					$rules_headers .= '    Header set Content-Security-Policy-Report-Only "' . $dir . "\"\n";
				}
			}

			if ( $config->get_boolean( 'browsercache.security.fp' ) ) {
				$fp_values = $config->get_array( 'browsercache.security.fp.values' );

				$feature_v    = array();
				$permission_v = array();

				foreach ( $fp_values as $key => $value ) {
					if ( ! empty( $value ) ) {
						$value = str_replace( array( '"', "'" ), '', $value );

						$feature_v[]    = "$key '$value'";
						$permission_v[] = "$key=($value)";
					}
				}

				if ( ! empty( $feature_v ) ) {
					$rules_headers .= '    Header set Feature-Policy "' . implode( ';', $feature_v ) . "\"\n";
				}

				if ( ! empty( $permission_v ) ) {
					$rules_headers .= '    Header set Permissions-Policy "' . implode( ',', $permission_v ) . "\"\n";
				}
			}

			$rules_headers .= "</IfModule>\n";

			/**
			 * Filter: w3tc_browsercache_rules_apache_headers
			 *
			 * @since 2.8.0
			 *
			 * @param string $rules_mime Apache rules for mod_headers.
			 * @return string
			 */
			$rules .= apply_filters( 'w3tc_browsercache_rules_apache_headers', $rules_headers );

			unset( $rules_headers );
		}

		$g = new BrowserCache_Environment_Apache( $config );

		/**
		 * Filter: w3tc_browsercache_rules_apache_rewrite
		 *
		 * @since 2.8.0
		 *
		 * @param string $rules_mime Apache rules for mod_rewrite.
		 * @return string
		 */
		$rules .= apply_filters( 'w3tc_browsercache_rules_apache_rewrite', $g->rules_rewrite() );

		return apply_filters( 'w3tc_browsercache_rules_apache', $rules );
	}

	/**
	 * Writes cache rules
	 *
	 * @param Config $config     Config.
	 * @param array  $mime_types Mime types.
	 * @param string $section    Section.
	 *
	 * @return string
	 */
	private function _rules_cache_generate_apache_for_type( $config, $mime_types, $section ) {
		$is_disc_enhanced  = $config->get_boolean( 'pgcache.enabled' ) && 'file_generic' === $config->get_string( 'pgcache.engine' );
		$cache_control     = $config->get_boolean( 'browsercache.' . $section . '.cache.control' );
		$etag              = $config->get_boolean( 'browsercache.' . $section . '.etag' );
		$w3tc              = $config->get_boolean( 'browsercache.' . $section . '.w3tc' );
		$unset_setcookie   = $config->get_boolean( 'browsercache.' . $section . '.nocookies' );
		$set_last_modified = $config->get_boolean( 'browsercache.' . $section . '.last_modified' );
		$compatibility     = $config->get_boolean( 'pgcache.compatibility' );

		$mime_types2 = apply_filters( 'w3tc_browsercache_rules_section_extensions', $mime_types, $config, $section );
		$extensions  = array_keys( $mime_types2 );

		// Remove ext from filesmatch if its the same as permalink extension.
		$pext = strtolower( pathinfo( get_option( 'permalink_structure' ), PATHINFO_EXTENSION ) );
		if ( $pext ) {
			$extensions = Util_Rule::remove_extension_from_list( $extensions, $pext );
		}

		$extensions_lowercase = array_map( 'strtolower', $extensions );
		$extensions_uppercase = array_map( 'strtoupper', $extensions );

		$rules         = '';
		$headers_rules = '';

		if ( $cache_control ) {
			$cache_policy = $config->get_string( 'browsercache.' . $section . '.cache.policy' );

			switch ( $cache_policy ) {
				case 'cache':
					$headers_rules .= "        Header set Pragma \"public\"\n";
					$headers_rules .= "        Header set Cache-Control \"public\"\n";
					break;

				case 'cache_public_maxage':
					$expires  = $config->get_boolean( 'browsercache.' . $section . '.expires' );
					$lifetime = $config->get_integer( 'browsercache.' . $section . '.lifetime' );

					$headers_rules .= "        Header set Pragma \"public\"\n";

					if ( $expires ) {
						$headers_rules .= "        Header append Cache-Control \"public\"\n";
					} else {
						$headers_rules .= "        Header set Cache-Control \"max-age=" . $lifetime . ", public\"\n";
					}

					break;

				case 'cache_validation':
					$headers_rules .= "        Header set Pragma \"public\"\n";
					$headers_rules .= "        Header set Cache-Control \"public, must-revalidate, proxy-revalidate\"\n";
					break;

				case 'cache_noproxy':
					$headers_rules .= "        Header set Pragma \"public\"\n";
					$headers_rules .= "        Header set Cache-Control \"private, must-revalidate\"\n";
					break;

				case 'cache_maxage':
					$expires  = $config->get_boolean( 'browsercache.' . $section . '.expires' );
					$lifetime = $config->get_integer( 'browsercache.' . $section . '.lifetime' );

					$headers_rules .= "        Header set Pragma \"public\"\n";

					if ( $expires ) {
						$headers_rules .= "        Header append Cache-Control \"public, must-revalidate, proxy-revalidate\"\n";
					} else {
						$headers_rules .= "        Header set Cache-Control \"max-age=" . $lifetime . ", public, must-revalidate, proxy-revalidate\"\n";
					}

					break;

				case 'no_cache':
					$headers_rules .= "        Header set Pragma \"no-cache\"\n";
					$headers_rules .= "        Header set Cache-Control \"private, no-cache\"\n";
					break;

				case 'no_store':
					$headers_rules .= "        Header set Pragma \"no-store\"\n";
					$headers_rules .= "        Header set Cache-Control \"no-store\"\n";
					break;

				case 'cache_immutable':
					$lifetime       = $config->get_integer( 'browsercache.' . $section . '.lifetime' );
					$headers_rules .= "        Header set Pragma \"public\"\n";
					$headers_rules .= "        Header set Cache-Control \"public, max-age=" . $lifetime . ", immutable\"\n";
					break;

				case 'cache_immutable_nomaxage':
					$headers_rules .= "        Header set Pragma \"public\"\n";
					$headers_rules .= "        Header set Cache-Control \"public, immutable\"\n";
					break;
			}
		}

		if ( $etag ) {
			$rules .= "    FileETag MTime Size\n";
		} else {
			if ( $compatibility ) {
				$rules         .= "    FileETag None\n";
				$headers_rules .= "        Header unset ETag\n";
			}
		}

		if ( $unset_setcookie ) {
			$headers_rules .= "        Header unset Set-Cookie\n";
		}

		if ( ! $set_last_modified ) {
			$headers_rules .= "        Header unset Last-Modified\n";
		}

		if ( $w3tc ) {
			$headers_rules .= "        Header set X-Powered-By \"" . Util_Environment::w3tc_header() . "\"\n";
		}

		if ( strlen( $headers_rules ) > 0 ) {
			$rules .= "    <IfModule mod_headers.c>\n";
			$rules .= $headers_rules;
			$rules .= "    </IfModule>\n";
		}

		if ( strlen( $rules ) > 0 ) {
			$rules  = "<FilesMatch \"\\.(" . implode( '|', array_merge( $extensions_lowercase, $extensions_uppercase ) ) . ")$\">\n" . $rules;
			$rules .= "</FilesMatch>\n";
		}

		return $rules;
	}

	/**
	 * Return CDN rules section
	 *
	 * @param array  $section_rules Section rules.
	 * @param Config $config        Config.
	 *
	 * @return array
	 */
	public function w3tc_cdn_rules_section( $section_rules, $config ) {
		if ( Util_Environment::is_litespeed() ) {
			$o             = new BrowserCache_Environment_LiteSpeed( $config );
			$section_rules = $o->w3tc_cdn_rules_section( $section_rules );
		}

		return $section_rules;
	}
}
