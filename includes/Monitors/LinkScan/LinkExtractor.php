<?php
/**
 * Extracts links and images from post content, allowlisted custom
 * fields, and nav menus.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\LinkScan;

defined( 'ABSPATH' ) || exit;

final class LinkExtractor {

	/**
	 * @return array<int,array{url:string,type:string,anchor_text:string}>
	 */
	public function extract_from_post( \WP_Post $post ): array {
		$found = array();

		$this->extract_from_html( $post->post_content, $found );

		foreach ( $this->allowlisted_meta_keys() as $meta_key ) {
			$value = get_post_meta( $post->ID, $meta_key, true );
			if ( is_string( $value ) && $value ) {
				$this->extract_from_html( $value, $found );
			}
		}

		return $found;
	}

	/**
	 * @return array<int,array{url:string,type:string,anchor_text:string,source_post_id:?int,source_title:?string}>
	 */
	public function extract_from_menus(): array {
		$found     = array();
		$locations = get_nav_menu_locations();
		foreach ( $locations as $menu_id ) {
			$items = wp_get_nav_menu_items( $menu_id );
			if ( ! $items ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( empty( $item->url ) ) {
					continue;
				}
				$found[] = array(
					'url'            => $item->url,
					'type'           => 'link',
					'anchor_text'    => $item->title,
					'source_post_id' => null,
					'source_title'   => __( 'Navigation menu', 'watchspire' ),
				);
			}
		}

		return $found;
	}

	/**
	 * @param array<int,array{url:string,type:string,anchor_text:string}> $found
	 */
	private function extract_from_html( string $html, array &$found ): void {
		if ( '' === trim( $html ) ) {
			return;
		}

		if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$url = trim( $match[1] );
				if ( $this->is_checkable( $url ) ) {
					$found[] = array(
						'url'         => $url,
						'type'        => 'link',
						'anchor_text' => wp_strip_all_tags( $match[2] ),
					);
				}
			}
		}

		if ( preg_match_all( '/<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$url = trim( $match[1] );
				if ( $this->is_checkable( $url ) ) {
					$found[] = array(
						'url'         => $url,
						'type'        => 'image',
						'anchor_text' => '',
					);
				}
			}
		}
	}

	private function is_checkable( string $url ): bool {
		if ( '' === $url || 0 === strpos( $url, '#' ) || 0 === stripos( $url, 'mailto:' ) || 0 === stripos( $url, 'tel:' ) || 0 === stripos( $url, 'javascript:' ) ) {
			return false;
		}

		if ( 0 === strpos( $url, 'data:' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @return string[]
	 */
	private function allowlisted_meta_keys(): array {
		return apply_filters( 'watchspire_link_scan_meta_keys', array() );
	}
}
