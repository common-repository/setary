<style>
	@font-face {
		font-family: 'Eudoxus Sans';
		src: url('<?php echo esc_attr( SETARY_URL ); ?>assets/fonts/EudoxusSansGX.ttf') format("truetype-variations");
		font-weight: 1 999;
	}

	body {
		background: #fff;
	}

	#wpbody-content {
		padding-bottom: 0 !important;
	}

	#wpfooter {
		display: none !important;
	}

	.setary-wrap {
		font-family: 'Eudoxus Sans', "Helvetica Neue", Helvetica, Arial, sans-serif;
		font-weight: 400;
		font-size: 15px;
		margin: 0 0 0 -20px;
		line-height: 1.2;
		background: #fff;
		min-height: 100%;
	}

	.setary-wrap:after {
		content: "";
		display: table;
		clear: both;
	}

	.setary-wrap p {
		font-size: 15px;
	}

	.setary-wrap a {
		color: #0367D9;
		text-decoration: underline;
	}

	.setary-wrap h1 {
		font-weight: 700;
		font-size: 37px;
		color: #183765;
		margin-top: 0;
	}

	.setary-wrap a:hover {
		color: #183765;
	}

	.setary-container {
		max-width: 1400px;
		padding: 0 40px;
		box-sizing: border-box;
		margin: 0 auto;
	}

	.setary-header {
		background: #183765;
		padding: 25px 0;
		color: #fff;
	}

	.setary-header__content {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 20px;
	}

	.setary-header__right {
		display: flex;
		gap: 20px;
		align-items: center;
	}

	.setary-header__text {
		font-weight: 600;
		font-size: 17px;
		text-align: right;
	}

	@media (max-width: 820px) {
		.setary-header__text {
			display: none;
		}
	}

	.setary-connect {
		margin: 40px 0;
		border-radius: 20px;
		background: #FFF4E3;
		padding: 40px;
		position: relative;
		overflow: hidden;
	}

	.setary-connect p {
		font-size: 17px;
	}

	.setary-connect__left {
		width: 45%;
	}

	.setary-connect__image {
		position: absolute;
		top: -40px;
		left: 45%;
		margin: 0;
	}

	@media (max-width: 1000px) {
		.setary-connect__left {
			width: auto;
		}

		.setary-connect__image {
			display: none;
		}
	}

	.setary-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		grid-auto-rows: minmax(min-content, max-content);
		gap: 40px 40px;
		max-width: none;
		margin: 0 0 60px;
	}

	@media (max-width: 1340px) {
		.setary-grid {
			grid-template-columns: repeat(2, 1fr);
		}
	}

	@media (max-width: 790px) {
		.setary-grid {
			grid-template-columns: repeat(1, 1fr);
		}
	}

	.setary-feature {
		background: #F5F9FF;
		padding: 25px 30px 25px 108px;
		position: relative;
		height: 100%;
		border-radius: 20px;
		box-sizing: border-box;
		position: relative;
	}

	.setary-feature__icon {
		position: absolute;
		top: 25px;
		left: 30px;
		width: 48px;
		height: auto;
	}

	.setary-feature__title {
		margin-top: 0;
		font-size: 19px;
		color: #183765;
		font-weight: 600;
	}

	.setary-feature__description {
		margin-bottom: 0;
	}

	.setary-feature__pro {
		height: 30px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 0 8px 0 28px;
		background: #183765 url('<?php echo esc_url( SETARY_URL ); ?>assets/img/bolt.svg') no-repeat 8px 50%;
		border-radius: 15px;
		color: #fff;
		font-size: 15px;
		font-weight: 600;
		position: absolute;
		top: -15px;
		right: 30px;
	}

	.setary-actions {
		display: flex;
		align-items: center;
		gap: 20px;
		margin: 30px 0 0;
	}

	.setary-button {
		height: 50px;
		display: inline-flex;
		justify-content: center;
		align-items: center;
		gap: 8px;
		padding: 0 30px;
		border-radius: 25px;
		background: #0367D9;
		color: #fff !important;
		text-decoration: none !important;
		font-weight: 600;
		transition: all 0.25s ease-in-out;
		white-space: nowrap;
	}

	.setary-button:hover,
	.setary-button:active,
	.setary-button:focus {
		background: #183765;
	}

	.setary-button img {
		transition: transform 0.25s ease-in-out;
	}

	.setary-button:hover img,
	.setary-button:active img,
	.setary-button:focus img {
		transform: translateX(2px);
	}

	.setary-button--secondary {
		background: #fff;
		color: #0367D9 !important;
	}

	.setary-button--secondary:hover,
	.setary-button--secondary:active,
	.setary-button--secondary:focus {
		background: #FFF4E3;
	}

	.setary-quicklinks {
		margin: 30px 0 0;
	}

	.setary-quicklinks p {
		margin: 0;
		font-size: 15px;
	}

	.setary-quicklinks__label {
		margin: 0 6px 0 0;
	}

	.setary-quicklink__divider {
		margin: 0 6px;
		opacity: 0.4;
	}
</style>

<div class="setary-wrap setary-wrap--welcome">
	<header class="setary-header">
		<div class="setary-container">
			<div class="setary-header__content">
				<img src="<?php echo esc_url( SETARY_URL ); ?>assets/img/setary-logo.svg" width="180" height="50" alt="Setary">
				<div class="setary-header__right">
					<span class="setary-header__text"><?php _e( 'Lightning-fast bulk editing for WooCommerce products', 'setary' ); ?></span>
					<a href="<?php echo esc_url( add_query_arg( array( 'utm_source' => 'Setary', 'utm_medium' => 'Plugin', 'utm_campaign' => 'Welcome Header' ), SETARY_SITE_URL ) ); ?>" class="setary-button setary-button--secondary" target="_blank"><?php _e( 'Learn More', 'setary' ); ?> <img src="<?php echo esc_url( SETARY_URL ); ?>assets/img/arrow-right-blue.svg" width="14" height="14" alt="Go"></a>
				</div>
			</div>
		</div>
	</header>

	<div class="setary-container">
		<section class="setary-connect">
			<div class="setary-connect__left">
				<h1 class="setary-connect__title"><?php _e( 'Add your store to Setary to get started', 'setary' ); ?></h1>
				<p class="setary-connect__description"><?php _e( 'Setary is an external tool for managing your WooCommerce products. Head over to the Setary website, login or create an account, and connect your store to get started.', 'setary' ); ?></p>
				<div class="setary-actions">
					<a href="<?php echo esc_url( add_query_arg( array( 'utm_source' => 'Setary', 'utm_medium' => 'Plugin', 'utm_campaign' => 'Welcome Connect' ), SETARY_APP_URL ) ); ?>" class="setary-button" target="_blank"><?php _e( 'Add your Store', 'setary' ); ?> <img src="<?php echo esc_url( SETARY_URL ); ?>assets/img/arrow-right.svg" width="14" height="14" alt="Go"></a>
				</div>
				<div class="setary-quicklinks">
					<p>
						<span class="setary-quicklinks__label"><?php _e( 'Quicklinks', 'setary' ); ?>:</span>
						<a href="<?php echo esc_url( add_query_arg( array( 'utm_source' => 'Setary', 'utm_medium' => 'Plugin', 'utm_campaign' => 'Welcome Connect' ), 'https://setary.com/docs/connect-a-woocommerce-store/' ) ); ?>" class="setary-quicklink__link"><?php _e( 'Get started', 'setary' ); ?></a>
						<span class="setary-quicklink__divider">|</span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=setary' ) ); ?>" class="setary-quicklink__link"><?php _e( 'Settings', 'setary' ); ?></a>
						<span class="setary-quicklink__divider">|</span>
						<a href="<?php echo esc_url( add_query_arg( array( 'utm_source' => 'Setary', 'utm_medium' => 'Plugin', 'utm_campaign' => 'Welcome Connect' ), 'https://setary.com/contact-us/' ) ); ?>" class="setary-quicklink__link"><?php _e( 'Support', 'setary' ); ?></a>
						<span class="setary-quicklink__divider">|</span>
						<a href="<?php echo esc_url( add_query_arg( array( 'utm_source' => 'Setary', 'utm_medium' => 'Plugin', 'utm_campaign' => 'Welcome Connect' ), 'https://setary.com/' ) ); ?>" class="setary-quicklink__link"><?php _e( 'Learn more', 'setary' ); ?></a>
					</p>
				</div>
			</div>
			<img class="setary-connect__image" src="<?php echo esc_url( SETARY_URL ); ?>assets/img/setary-app-preview.png" width="1246" height="880" alt="Setary app preview">
		</section>

		<?php $features = [
			[
				'icon'        => 'row-table.svg',
				'title'       => __( 'Edit products in a spreadsheet environment', 'setary' ),
				'description' => __( 'Setary makes it quick and easy to edit all of the products in your store in a lightning-fast spreadsheet environment.', 'setary' ),
			],
			[
				'icon'        => 'copy.svg',
				'title'       => __( 'Edit variations', 'setary' ),
				'description' => __( 'Edit products and product variations in Setary, saving you hours when managing your product collection.', 'setary' ),
			],
			[
				'icon'        => 'storage-unit.svg',
				'title'       => __( 'Manage your product inventory', 'setary' ),
				'description' => __( 'Easily manage your product inventory without having to click between slow-loading admin pages.', 'setary' ),
			],
			[
				'icon'        => 'store.svg',
				'title'       => __( 'Connect multiple stores', 'setary' ),
				'description' => __( 'Connect to multiple stores with Setary. Manage your product inventory across multiple stores from one location.', 'setary' ),
			],
			[
				'icon'        => 'copy.svg',
				'title'       => __( 'Bulk actions', 'setary' ),
				'description' => __( 'Select multiple products and apply changes, like increasing the price by 10%, in one go.', 'setary' ),
			],
			[
				'icon'        => 'tag-cut.svg',
				'title'       => __( 'Update product data', 'setary' ),
				'description' => __( 'Setary doesn\'t stop at inventory. Easily edit product categories and other product data from within the app.', 'setary' ),
			],
			[
				'icon'        => 'copy.svg',
				'title'       => __( 'Push to multiple stores', 'setary' ),
				'description' => __( 'Push product updates to multiple stores at once. Match products by any field like ID, Name, or SKU.', 'setary' ),
			],
			[
				'icon'        => 'copy.svg',
				'title'       => __( 'Edit custom fields', 'setary' ),
				'description' => __( 'Use Setary to modify data in custom fields. This could be data added by third-party plugins, or your own custom fields.', 'setary' ),
			],
			[
				'icon'        => 'copy.svg',
				'title'       => __( 'Show/hide columns', 'setary' ),
				'description' => __( 'Choose which columns are visible for each store in Setary and change them at any time.', 'setary' ),
			],
		]; ?>

		<section class="setary-grid">
			<?php foreach ( $features as $feature ) { ?>
				<div class="setary-grid__item">
					<div class="setary-feature">
						<img class="setary-feature__icon" src="<?php echo esc_url( SETARY_URL ); ?>assets/img/<?php echo esc_attr( $feature['icon'] ); ?>" alt="<?php echo esc_attr( $feature['title'] ); ?>">
						<h2 class="setary-feature__title"><?php echo wp_kses_post( $feature['title'] ); ?></h2>
						<p class="setary-feature__description"><?php echo wp_kses_post( $feature['description'] ); ?></p>
					</div>
				</div>
			<?php } ?>
		</section>
	</div>
</div>