<?php
/*
Plugin Name:  Bulk Subscriber
Plugin URI:   https://github.com/altgilbers/wp_bulk_subscriber
Description:  Bulk Subscribe lists of existing users into a role in an existing site in a WordPress network install
Version:      1.0
Author:       Ian Altgilbers
Author URI:   http://altgilbers.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

// to make sure jquery is loaded..
wp_enqueue_script("jquery");

add_action('network_admin_menu', 'bs_plugin_setup_menu');

function bs_plugin_setup_menu(){
	add_menu_page(
		$page_title='Bulk Subscriber Plugin Page',
		$menu_title='Bulk Subscriber Plugin',
		$capability='manage_network',
		$menu_slug='bs-options',
		$function='bs_init'
	);
}


add_action('network_admin_edit_bs_process', 'bs_process');

// this function does the business of actually adding users to the site
function bs_process()
{
	if(! is_network_admin())
		exit;
	$output="";
	// would be wise to sanitize input here out of abundance of caution, but form is only accessible to users with super-admin privs..
	$site=$_POST['site_id'];
	$target_role=$_POST['target_role'];
	// split user list on whitespace, comma, or semicolon..
	$users = preg_split( "/[,;\r\n\s]/", $_POST['users'],-1,PREG_SPLIT_NO_EMPTY );

	$site_obj=get_site($site);
	date_default_timezone_set('America/New_York');
	$output.=date(DATE_RFC2822)."\n\n";
	$output.="Site selected ID: ".$site."  path: ".$site_obj->path."\n";
	error_log("number of users provided: ".count($users));
	$output.="Number of users provided: ".count($users)."\n";
	$output.="Role selected: ".$target_role."\n";
	$output.="\n\n";

	foreach($users as $user)
	{
		if(strpos($user,'@')===FALSE)
		{
			$u=get_user_by('login',$user);
		}
		else
		{
			$u=get_user_by('email',$user);
		}

		if($u===FALSE)
		{
			error_log("user: [".$user."] not found");
			$output.="user: [".$user."] not found\n";
		}
		else
		{
			if(is_user_member_of_blog( $u->ID, $site ))
			{
				error_log($u->user_login." already a member of blog");
				$output.=$u->user_login." already a member of blog\n";
			}
			else
			{
				if (add_user_to_blog($site,$u->ID,$target_role))
				{
					error_log($u->user_login." added to blog with role: ".$target_role);
					$output.=$u->user_login." added to blog with role: ".$target_role."\n";
				}
				else
				{
					error_log($u->user_login.": error adding to blog");
					$output.=$u->user_login." error adding to blog\n";
				}
			}
		}
	}

	// can't pass any significant amount of info back to the calling page, so i'll store the output in a site option and read it out after.
	update_site_option('bs_last_log',$output);
	$redirect_url=add_query_arg(array('page' => 'bs-options'),network_admin_url( 'admin.php' ));
	wp_redirect($redirect_url);

	exit;
}


// plugins admin page display
function bs_init(){  ?>
   <h1>Bulk Subscriber</h1>

   <form method="post" action="<?php echo network_admin_url( 'edit.php?action=bs_process' ) ?>">
      <p>Choose the site you want to add existing users to from dropdown...  filter the list to help you find the site you want:</p>
      <input id="sites_filter" type="text" placeholder="filter blogs list"/>
      <br/>
      <select name="site_id" id="sites_list">
         <?php
            foreach(get_sites(array(number=>100000,orderby=>'registered',order=>'DESC')) as $x)
            {
               echo "<option value=".$x->blog_id.">".$x->path."</option>";
            }
         ?>
      </select>
      <br/>
      <select name="target_role">
         <?php wp_dropdown_roles( 'editor' );?>
      </select>
      <br/>
      <textarea rows="14" cols="50" name="users" placeholder="usernames or emails"></textarea>
      <?php submit_button();?>
   </form>
   <h3>Log of last run...</h3>
   <pre style="border:1px solid black;"> <?php echo get_site_option('bs_last_log') ?> </pre>

   <script>
      jQuery.fn.filterByText = function(textbox, selectSingleMatch) {
         return this.each(function() {
            var select = this;
            var options = [];
            jQuery(select).find('option').each(function() {
               options.push({value: jQuery(this).val(), text: jQuery(this).text()});
            });
            jQuery(select).data('options', options);
            jQuery(textbox).bind('change keyup', function() {
            var options = jQuery(select).empty().scrollTop(0).data('options');
            var search = jQuery.trim(jQuery(this).val());
            var regex = new RegExp(search,'gi');

            jQuery.each(options, function(i) {
               var option = options[i];
               if(option.text.match(regex) !== null) {
                  jQuery(select).append(
                     jQuery('<option>').text(option.text).val(option.value)
                  );
               }
            });
            if (selectSingleMatch === true && jQuery(select).children().length === 1) {
               jQuery(select).children().get(0).selected = true;
            }
         });
      });
   };
   jQuery(function() {
      jQuery('#sites_list').filterByText(jQuery('#sites_filter'), true);
   });  

   </script>
<?php
} 
?>
