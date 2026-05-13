<?php 
/**
 * 404 ( Not found page )
 */
get_header();?>
	<div class="content-wrapper">
		<section class="full-section gdl-page-404">
			<div class="container">
				<div class="message-box-wrapper">
					<h1>Page Not Found</h1>
					<div class="message-box-title">
						<div class="cover404">
							<span>4</span>
							<span>0</span>
							<span>4</span>						
						</div>
					</div>
					<div class="message-box-content">
						Looks like this page has gone off-piste. The page you're looking for doesn't exist or may have moved.
						<div style="margin-top: 30px;">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn">Back to Homepage</a>
						</div>
					</div>
				</div>
			</div>
			<div class="clear"></div>
		</section>
	</div>
<?php get_footer();?>