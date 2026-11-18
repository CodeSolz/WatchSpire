<?php
/**
 * About Us – the CodeSolz plugin family.
 *
 * Same page as Better Find & Replace's About Us screen: a short intro to
 * CodeSolz, a card for this plugin, and cards for the other CodeSolz plugins.
 * Every icon is bundled with the plugin, so the page makes no external request.
 *
 * @package WatchSpire
 */

defined( 'ABSPATH' ) || exit;

$watchspire_about_icons = WATCHSPIRE_URL . 'assets/images/plugins/';

/*
 * The other CodeSolz plugins. `icon_file` is the plugin's WordPress.org icon,
 * bundled next to this plugin's other images.
 */
$watchspire_about_plugins = array(
	array(
		'title'       => 'Better Find & Replace',
		'subtitle'    => 'Change any text across your whole site at once',
		'description' => 'Replace text on the fly as pages load, or permanently in the database. Works with plain text and regular expressions, and swaps media files in your Media Library.',
		'icon_file'   => 'better-find-and-replace.png',
		'features'    => array(
			'Real-time replacement without touching the database',
			'Permanent find & replace in database tables',
			'Plain text and regex rules',
			'Replace media files in the Media Library',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire',
		'wporg_url'   => 'https://wordpress.org/plugins/real-time-auto-find-and-replace/',
	),
	array(
		'title'       => 'Aisleza – 3D Virtual Store for WooCommerce',
		'subtitle'    => 'Let shoppers walk through your store',
		'description' => 'Turn your WooCommerce catalog into a walk-in 3D store. Categories become aisles, products sit on real shelves with price tags, and shoppers fill a trolley that is your real WooCommerce cart.',
		'icon_file'   => 'aisleza.png',
		'features'    => array(
			'Build aisles from your product categories',
			'Products on shelves with live prices & sale tags',
			'Uses your real WooCommerce cart & checkout',
			'Works with keyboard, mouse, and touch joystick',
		),
		'pro_url'     => 'https://aisleza.com/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire',
		'wporg_url'   => 'https://wordpress.org/plugins/aisleza-3d-virtual-store-for-woocommerce/',
	),
	array(
		'title'       => 'PressHelm AI Site Developer',
		'subtitle'    => 'Your AI developer for WordPress content',
		'description' => 'Give your WordPress site its own AI developer. Ask in plain words from Telegram, Claude, or ChatGPT, and it builds pages, posts, products, menus, forms, and SEO on your real site, with approvals and one-tap undo.',
		'icon_file'   => 'presshelm-ai.png',
		'features'    => array(
			'Edit your site from Telegram, Claude, or ChatGPT',
			'Pages, products, images, menus, forms & SEO',
			'Approve important changes before they run',
			'Undo any change with one tap',
		),
		'pro_url'     => 'https://presshelmai.com/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire',
		'wporg_url'   => 'https://wordpress.org/plugins/presshelm-ai/',
	),
	array(
		'title'       => 'AI Store Signals for WooCommerce',
		'subtitle'    => 'Get your products ready for AI shopping',
		'description' => 'See how well AI systems can understand your WooCommerce store. Find visibility gaps, improve product data, generate AI-friendly feeds and llms.txt, and track AI bot activity.',
		'icon_file'   => 'ai-store-signals.png',
		'features'    => array(
			'Get an AI readiness score for your store',
			'Find product & schema visibility gaps',
			'Generate AI-friendly feeds & llms.txt',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/ai-store-signals-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire',
		'wporg_url'   => 'https://wordpress.org/plugins/ai-store-signals-for-woocommerce/',
	),
	array(
		'title'       => 'WearGraph – AI Virtual Try-On for WooCommerce',
		'subtitle'    => 'Let shoppers see clothes on themselves',
		'description' => 'Shoppers add a photo and see the garment on themselves, with Add to Cart right beside the preview. Runs on your own Gemini, OpenAI, OpenRouter, FLUX or xAI key, with no WearGraph account or subscription.',
		'icon_file'   => 'weargraph.png',
		'features'    => array(
			'AI fitting room on product pages',
			'On-model product images from your own photos',
			'See which products are ready for try-on',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/weargraph/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire',
		'wporg_url'   => 'https://wordpress.org/plugins/weargraph/',
	),
	array(
		'title'       => 'AttributeHub for WooCommerce',
		'subtitle'    => 'Turn messy product attributes into clean filters',
		'description' => 'Clean up confusing supplier and imported attribute values without changing your original product data. Create consistent, customer-friendly filters across your store.',
		'icon_file'   => 'attributehub.png',
		'features'    => array(
			'Turn codes like BK into Black and WH into White',
			'Clean imported attributes automatically',
			'Keep original product data untouched',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/attributehub-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire',
		'wporg_url'   => 'https://wordpress.org/plugins/attributehub-for-woocommerce/',
	),
	array(
		'title'       => 'Merchant Feed Booster for WooCommerce',
		'subtitle'    => 'Fix Google Shopping feed problems before they cost sales',
		'description' => 'Find exactly why your WooCommerce products may be rejected or underperforming on Google Shopping. Audit your catalog, get a health score, and see exactly what needs fixing.',
		'icon_file'   => 'merchant-feed-booster.png',
		'features'    => array(
			'Audit products against 27 feed rules',
			'Get a 0–100 feed health score',
			'See exactly what needs fixing',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/merchant-feed-booster-lite-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire',
		'wporg_url'   => 'https://wordpress.org/plugins/merchant-feed-booster-lite-for-woocommerce/',
	),
);

$watchspire_about_docs_url     = '';
$watchspire_about_product_url  = 'https://codesolz.net/our-products/wordpress-plugin/watchspire/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire';
$watchspire_about_wporg_url    = 'https://wordpress.org/plugins/watchspire/';
$watchspire_about_support_url  = 'https://wordpress.org/support/plugin/watchspire/';
$watchspire_about_upgrade_url  = 'https://codesolz.net/our-products/wordpress-plugin/watchspire/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire';
$watchspire_about_products_url = 'https://codesolz.net/our-products/wordpress-plugins/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire';
$watchspire_about_website_url  = 'https://codesolz.net/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire';
$watchspire_about_forum_url    = 'https://codesolz.net/forum/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=watchspire';
$watchspire_about_has_pro      = \WatchSpire\Support\Pro::is_active();
?>
<div class="wrap">
<div class="watchspire-about-wrap">

	<!-- ================================================
		HERO SECTION
		================================================ -->
	<div class="watchspire-about-hero">
		<div class="watchspire-about-hero-grid">
			<div class="watchspire-about-hero-copy">
				<div class="watchspire-about-hero-badge"><?php esc_html_e( 'CodeSolz · WordPress plugins since 2016', 'watchspire' ); ?></div>
				<h1 class="watchspire-about-hero-title"><?php esc_html_e( 'WordPress Tools That Each Fix One Real Problem', 'watchspire' ); ?></h1>
				<p class="watchspire-about-hero-subtitle">
					<?php esc_html_e( 'A contact form that stops sending without any error. A Google Shopping product rejected because its title is too long. Supplier codes like BLK showing up in your shop filters. CodeSolz plugins find problems like these on your own site, show you exactly what is wrong, and help you fix it.', 'watchspire' ); ?>
				</p>
				<div class="watchspire-about-hero-points">
					<span class="watchspire-about-hero-point"><?php esc_html_e( 'Every plugin starts free on WordPress.org', 'watchspire' ); ?></span>
					<span class="watchspire-about-hero-point"><?php esc_html_e( 'This page loads nothing from outside your site', 'watchspire' ); ?></span>
					<span class="watchspire-about-hero-point"><?php esc_html_e( 'Plugins for WordPress and WooCommerce', 'watchspire' ); ?></span>
				</div>
				<div class="watchspire-about-hero-actions">
					<a href="<?php echo esc_url( $watchspire_about_products_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-hero-cta">
						<?php esc_html_e( 'Browse Our Plugins', 'watchspire' ); ?> <span aria-hidden="true">&#8599;</span>
					</a>
					<a href="<?php echo esc_url( $watchspire_about_website_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-hero-link">
						<?php esc_html_e( 'Visit CodeSolz', 'watchspire' ); ?>
					</a>
				</div>
			</div>
			<div class="watchspire-about-hero-showcase">
				<div class="watchspire-about-hero-panel">
					<div class="watchspire-about-hero-panel-label"><?php esc_html_e( 'Find the right tool', 'watchspire' ); ?></div>
					<h2 class="watchspire-about-hero-panel-title"><?php esc_html_e( 'Start with the problem you have', 'watchspire' ); ?></h2>
					<div class="watchspire-about-hero-feature-list">
						<div class="watchspire-about-hero-feature">
							<strong><?php esc_html_e( 'Sales you may be losing', 'watchspire' ); ?></strong>
							<span><?php esc_html_e( 'WatchSpire catches forms and checkouts that fail without an error. Merchant Feed Booster checks every product against 27 Google Shopping rules.', 'watchspire' ); ?></span>
						</div>
						<div class="watchspire-about-hero-feature">
							<strong><?php esc_html_e( 'A messy catalog or site', 'watchspire' ); ?></strong>
							<span><?php esc_html_e( 'AttributeHub turns codes like BK into Black in your filters. Better Find & Replace changes a word or link on every page at once.', 'watchspire' ); ?></span>
						</div>
						<div class="watchspire-about-hero-feature">
							<strong><?php esc_html_e( 'New ways for shoppers to buy', 'watchspire' ); ?></strong>
							<span><?php esc_html_e( 'Aisleza turns your catalog into a walk-in 3D store, WearGraph adds virtual try-on, and AI Store Signals gets products ready for AI shopping assistants.', 'watchspire' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ================================================
		FEATURED PLUGIN – THIS PLUGIN
		================================================ -->
	<div class="watchspire-about-section-header">
		<div class="watchspire-about-section-label"><?php esc_html_e( 'Currently Installed', 'watchspire' ); ?></div>
		<h2 class="watchspire-about-section-title"><?php esc_html_e( 'Your Active Plugin', 'watchspire' ); ?></h2>
		<p class="watchspire-about-section-desc"><?php esc_html_e( 'What the plugin you are running does, and where to get help with it.', 'watchspire' ); ?></p>
	</div>

	<div class="watchspire-about-featured-card">
		<div class="watchspire-about-featured-inner">

			<div class="watchspire-about-featured-icon-wrap" aria-hidden="true">
				<img src="<?php echo esc_url( $watchspire_about_icons . 'watchspire.png' ); ?>" alt="" width="72" height="72" />
			</div>

			<div class="watchspire-about-featured-body">
				<div class="watchspire-about-featured-meta">
					<span class="watchspire-about-badge featured-label"><?php esc_html_e( 'Installed', 'watchspire' ); ?></span>
					<span class="watchspire-about-badge active"><?php esc_html_e( 'Active', 'watchspire' ); ?></span>
					<?php if ( $watchspire_about_has_pro ) : ?>
						<span class="watchspire-about-badge premium"><?php esc_html_e( 'Pro', 'watchspire' ); ?></span>
					<?php endif; ?>
				</div>

				<h2 class="watchspire-about-featured-title"><?php esc_html_e( 'WatchSpire', 'watchspire' ); ?></h2>

				<p class="watchspire-about-featured-desc">
					<?php esc_html_e( 'Watches the parts of your site that fail without telling anyone: form submissions, outgoing email, uptime, SSL expiry, 404 and 5xx errors, and broken links. When something breaks, the change log shows which plugin, theme or core update happened just before it.', 'watchspire' ); ?>
				</p>

				<ul class="watchspire-about-feature-list">
							<li><?php esc_html_e( 'Form monitoring for CF7, WPForms, Gravity Forms & more', 'watchspire' ); ?></li>
							<li><?php esc_html_e( 'Alerts when submissions go unusually quiet', 'watchspire' ); ?></li>
							<li><?php esc_html_e( 'Mail failure tracking with a mail health score', 'watchspire' ); ?></li>
							<li><?php esc_html_e( 'SSL expiry, uptime and server error checks', 'watchspire' ); ?></li>
							<li><?php esc_html_e( 'Broken links & images scanner', 'watchspire' ); ?></li>
							<li><?php esc_html_e( 'Change log of plugin, theme and core updates', 'watchspire' ); ?></li>
				</ul>

				<div class="watchspire-about-btn-group">
					<?php if ( '' !== $watchspire_about_docs_url ) : ?>
						<a href="<?php echo esc_url( $watchspire_about_docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn primary">
							<?php esc_html_e( 'Documentation', 'watchspire' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $watchspire_about_product_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn <?php echo '' !== $watchspire_about_docs_url ? 'secondary' : 'primary'; ?>">
						<?php esc_html_e( 'Product Page', 'watchspire' ); ?>
					</a>
					<a href="<?php echo esc_url( $watchspire_about_wporg_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn secondary">
						<?php esc_html_e( 'WordPress.org Page', 'watchspire' ); ?>
					</a>
					<a href="<?php echo esc_url( $watchspire_about_support_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn secondary">
						<?php esc_html_e( 'Get Support', 'watchspire' ); ?>
					</a>
					<?php if ( ! $watchspire_about_has_pro ) : ?>
						<a href="<?php echo esc_url( $watchspire_about_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn outline-purple">
							&#9733; <?php esc_html_e( 'Upgrade to Pro', 'watchspire' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- ================================================
		PLUGIN GRID
		================================================ -->
	<div class="watchspire-about-section-header">
		<div class="watchspire-about-section-label"><?php esc_html_e( 'Plugin Family', 'watchspire' ); ?></div>
		<h2 class="watchspire-about-section-title"><?php esc_html_e( 'More from CodeSolz', 'watchspire' ); ?></h2>
		<p class="watchspire-about-section-desc"><?php esc_html_e( 'Each one solves a different problem, and each one has a free version on WordPress.org you can try first.', 'watchspire' ); ?></p>
	</div>

	<div class="watchspire-about-plugins-grid">
		<?php foreach ( $watchspire_about_plugins as $watchspire_about_plugin ) : ?>
		<div class="watchspire-about-plugin-card">
			<div class="watchspire-about-card-top">
				<div class="watchspire-about-card-icon" aria-hidden="true">
					<img src="<?php echo esc_url( $watchspire_about_icons . $watchspire_about_plugin['icon_file'] ); ?>" alt="" width="48" height="48" loading="lazy" />
				</div>
				<div class="watchspire-about-card-heading">
					<h3 class="watchspire-about-card-title"><?php echo esc_html( $watchspire_about_plugin['title'] ); ?></h3>
					<span class="watchspire-about-card-subtitle"><?php echo esc_html( $watchspire_about_plugin['subtitle'] ); ?></span>
				</div>
			</div>
			<p class="watchspire-about-card-desc"><?php echo esc_html( $watchspire_about_plugin['description'] ); ?></p>
			<?php if ( ! empty( $watchspire_about_plugin['features'] ) ) : ?>
			<ul class="watchspire-about-card-features">
				<?php foreach ( $watchspire_about_plugin['features'] as $watchspire_about_feature ) : ?>
				<li><?php echo esc_html( $watchspire_about_feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<div class="watchspire-about-card-footer">
				<a href="<?php echo esc_url( $watchspire_about_plugin['pro_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-card-link pro">
					<?php esc_html_e( 'Learn More', 'watchspire' ); ?>
				</a>
				<a href="<?php echo esc_url( $watchspire_about_plugin['wporg_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-card-link wporg">
					<?php esc_html_e( 'Get It Free on WordPress.org', 'watchspire' ); ?> &#8599;
				</a>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- ================================================
		FOOTER SECTION
		================================================ -->
	<div class="watchspire-about-trust-section">
		<div class="watchspire-about-trust-logo">Code<span>Solz</span></div>
		<h2 class="watchspire-about-trust-tagline"><?php esc_html_e( 'Making WordPress plugins since 2016', 'watchspire' ); ?></h2>
		<p class="watchspire-about-trust-desc">
			<?php esc_html_e( 'Every plugin on this page starts free on WordPress.org, so you can try it on your own site before you pay for anything.', 'watchspire' ); ?>
		</p>
		<div class="watchspire-about-trust-links">
			<a href="<?php echo esc_url( $watchspire_about_website_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn primary">
				<?php esc_html_e( 'Visit Website', 'watchspire' ); ?>
			</a>
			<?php if ( '' !== $watchspire_about_docs_url ) : ?>
				<a href="<?php echo esc_url( $watchspire_about_docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn secondary">
					<?php esc_html_e( 'Documentation', 'watchspire' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $watchspire_about_support_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn secondary">
				<?php esc_html_e( 'Get Support', 'watchspire' ); ?>
			</a>
			<a href="<?php echo esc_url( $watchspire_about_forum_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn secondary">
				<?php esc_html_e( 'Our Community', 'watchspire' ); ?>
			</a>
			<a href="<?php echo esc_url( $watchspire_about_products_url ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-about-btn outline-purple">
				<?php esc_html_e( 'All Our Plugins', 'watchspire' ); ?> &#8599;
			</a>
		</div>
	</div>

</div><!-- .watchspire-about-wrap -->
</div><!-- .wrap -->
