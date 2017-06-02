<?php
/**
 * Plugin Name:         WPG Environment Info
 * Plugin URI:          https://github.com/wp-globalis-tools/wpg-environment-info
 * Description:         Add environment informations to your wp-admin bar 
 * Author:              Pierre Dargham, Globalis Media Systems
 * Author URI:          https://www.globalis-ms.com/
 * License:             GPL2
 *
 * Version:             0.2.0
 * Requires at least:   4.0.0
 * Tested up to:        4.7.8
 */

namespace Globalis\WP\EnvironmentInfo;

add_action('admin_bar_menu', __NAMESPACE__ . '\\add_environment_info', 10);
add_action('admin_bar_menu', __NAMESPACE__ . '\\remove_wp_logo', 99);
add_action('after_setup_theme', __NAMESPACE__ . '\\override_admin_bar_style', 10, 1);

function get_git_revision() {
	$filename = ROOT_DIR . '/.gitrevision';
	$prefix = '#';
	if('development' != WP_ENV) {
		if(file_exists($filename)) {
			$commit = file_get_contents($filename);
		} else {
			$commit = 'unknown';
		}
	} else {
		$commit = exec('git rev-parse --short HEAD');
	}
	return $prefix . $commit;
}

function get_git_branch() {
	$filename = ROOT_DIR . '/.gitbranch';
	if('development' != WP_ENV) {
		if(file_exists($filename)) {
			return file_get_contents($filename);
		} else {
			return 'unknown branch';
		}
	} else {
		return exec('git rev-parse --abbrev-ref HEAD');
	}
}

function get_version($revision, $branch) {
	if(false !== strpos($branch, 'release_')) {
		$version = str_replace('release_', '', $branch);
	} else {
		$version = $revision;
	}
	return $version;
}

function env_shortname($env) {
	$env = 'pre-production';
	$envs = [
		'development' => 'dev.',
		'staging' => 'staging',
		'production' => 'prod.',
	];
	if(isset($envs[$env])) {
		return $envs[$env];
	} else {
		return substr($env, 0, 8);;
	}
}

function box_title($title) {
	return '<span class="wpg-box">' . $title . ' : </span>';
}

function box_switch($title) {
	return '<span class="wpg-box">' . $title . ' &rarr; </span>';
}

function box_switch_to($env, $url) {
	return '<span class="wpg-box"><a href="' . $url . '">ENV &rarr; ' . $env . '</a></span>';
}

function add_environment_info($wp_admin_bar) {

	$revision = get_git_revision();
	$branch   = get_git_branch();

	$wp_admin_bar->add_menu( array(
		'parent' => false,
		'id'     => 'website-env',
		'title'  => '[' . get_version($revision, $branch) . '] ' . env_shortname(WP_ENV),
		'meta'   => ['class'  => 'wpg-environment wpg-environment-' . WP_ENV],
		'href'   => admin_url('/'),
		));

	if(current_user_can('manage_options')) {
		$wp_admin_bar->add_node(array(
			'parent' => 'website-env',
			'id'     => 'website-env-box-server',
			'title'  => box_title('Server') . code(gethostbyaddr($_SERVER['SERVER_ADDR'])) . ' (' . code($_SERVER['SERVER_ADDR']) . ')',
			));

		$wp_admin_bar->add_node(array(
			'parent' => 'website-env',
			'id'     => 'website-env-box-db',
			'title'  => box_title('Database') . code(DB_NAME) . ' on ' . code(DB_HOST),
			));

		$wp_admin_bar->add_node(array(
			'parent' => 'website-env',
			'id'     => 'website-env-box-git',
			'title'  => box_title('Git') . 'commit ' . code($revision) . ' on branch ' . code($branch),
			));

		$wp_admin_bar->add_node(array(
			'parent' => 'website-env',
			'id'     => 'website-env-box-seo',
			'title'  => box_title('SEO') . code(get_seo_info()),
			));

		$public_urls = get_public_urls();

		if(!empty($public_urls)) {
			$wp_admin_bar->add_node(array(
				'parent' => 'website-env',
				'id'     => 'website-env-box-hr',
				'title'  => '&nbsp;',
				));

			$wp_admin_bar->add_node(array(
				'parent' => 'website-env',
				'id'     => 'website-env-box-switch',
				'title'  => '<span class="wpg-box">Switch to environment</span>',
				));

			foreach($public_urls as $env => $url) {
				$wp_admin_bar->add_node(array(
					'parent' => 'website-env-box-switch',
					'id'     => 'website-env-box-switch-env-' . strtolower($env),
					'title'  => '<a href="' . $url . '"><span class="wpg-box">' . ucwords($env) . '</span></a>',
					));
			}
		}
	}
}

function code($string) {
	return '<code>' . $string . '</code>';
}

function get_seo_info() {
	if(defined('WPG_NOINDEX') && true === WPG_NOINDEX) {
		return 'noindex';
	} else {
		return get_option('blog_public') ? 'index' : 'noindex';
	}
}

function remove_wp_logo($wp_admin_bar) {
	$wp_admin_bar->remove_menu('wp-logo');
}

function get_public_urls() {
	if (!defined('WP_PUBLIC_URLS')) {
		return [];
	}

	$urls           = [];
	$envs           = unserialize(WP_PUBLIC_URLS);
	$current_url    = WP_SCHEME . '://' . WP_DOMAIN . $_SERVER['REQUEST_URI'];

	if(isset($envs[WP_ENV])) {
		unset($envs[WP_ENV]);
	}

	foreach($envs as $env_name => $env_url) {
	$urls[$env_name] = str_replace(trailingslashit(WP_HOME), trailingslashit($env_url), $current_url);
	}

	return $urls;
}

function override_admin_bar_style() {
  add_theme_support('admin-bar', ['callback' => __NAMESPACE__ . '\\admin_bar_inline_css']);
  add_action('admin_head', __NAMESPACE__ . '\\admin_bar_inline_css', 10, 1);
}

function admin_bar_inline_css() {
  ?>
	<style type="text/css" media="screen">
		#wpadminbar #wp-admin-bar-website-env {
			width: 160px;
		}
		#wpadminbar #wp-admin-bar-website-env code {
			color: #00b9eb;
			background-color: #22262a;
			padding: 0 3px;
			font-weight: bold;
			font-family: sans-serif;
		}
		#wpadminbar #wp-admin-bar-website-env > a {
			text-transform: uppercase;
			font-weight: bold;
		}
		#wpadminbar #wp-admin-bar-website-env #wp-admin-bar-website-env-box-switch.menupop.hover > div > span,
		#wpadminbar #wp-admin-bar-website-env #wp-admin-bar-website-env-box-switch li a > .wpg-box:hover,
		#wpadminbar #wp-admin-bar-website-env #wp-admin-bar-website-env-box-switch li.hover a > .wpg-box {
			color: #00b9eb;
		}
		#wpadminbar #wp-admin-bar-website-env .wpg-box {
			width: 85px;
			font-weight: bold;
			color: #FFFFFF;
			text-transform: uppercase;
			display: inline-block;
		}
		#wpadminbar #wp-admin-bar-website-env #wp-admin-bar-website-env-box-hr {
			border-bottom: 2px dotted grey;
			height: 0;
			padding-bottom: 10px;
			margin-bottom: 5px;
			margin-left: 10px;
			margin: 0 10px 5px 10px;
		}
		#wpadminbar .wpg-environment {
			background-color : #e49503;
		}
		#wpadminbar .wpg-environment.wpg-environment-development {
			background-color : #037a03;
		}
		#wpadminbar .wpg-environment.wpg-environment-staging {
			background-color : #e49503;
		}
		#wpadminbar .wpg-environment.wpg-environment-production {
			background-color : #d43a19;
		}
		#wpadminbar #wp-admin-bar-website-env .wpg-switch-to-link a {
			text-transform: uppercase;
			font-weight: bold;
			text-decoration: underline;
			padding: 0;
		}
	</style>
  <?php
}