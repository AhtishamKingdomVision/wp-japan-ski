<form method="get" id="searchform" action="<?php  echo home_url(); ?>/">
	<div class="search-text">
		<input type="text" placeholder="Search" value="<?php the_search_query(); ?>" name="s" id="s" autocomplete="off" />
	</div>
    <div class="search-submit">
	 <input type="submit" id="searchsubmit" value="" />
    </div>
</form>
