<?php
/**
 * MyBB 1.2
 * Copyright � 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */
 
// Board Name: MyBB 1.4 (Merge)

class Convert_mybb extends Converter
{
	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "MyBB 1.4 (Merge)";
	
	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_usergroups" => array("name" => "Import MyBB Usergroups",
									  "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Import MyBB Users",
									  "dependencies" => "db_configuration,import_usergroups"),
						 "import_forums" => array("name" => "Import MyBB Forums",
									  "dependencies" => "db_configuration,import_users"),
						 "import_threads" => array("name" => "Import MyBB Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Import MyBB Polls",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Import MyBB Poll Votes",
									  "dependencies" => "db_configuration,import_polls"),
						 "import_icons" => array("name" => "Import MyBB Icons",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_posts" => array("name" => "Import MyBB Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_moderators" => array("name" => "Import MyBB Moderators",
									  "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_privatemessages" => array("name" => "Import MyBB Private Messages",
						 			  "dependencies" => "db_configuration,import_users"),
						 "import_smilies" => array("name" => "Import MyBB Smilies",
									  "dependencies" => "db_configuration"),
						 "import_settinggroups" => array("name" => "Import MyBB Setting groups",
									  "dependencies" => "db_configuration"),
						 "import_settings" => array("name" => "Import MyBB Settings",
									  "dependencies" => "db_configuration,import_settinggroups"),
						 "import_events" => array("name" => "Import MyBB Calendar Events",
									  "dependencies" => "db_configuration,import_users"),
						 "import_attachtypes" => array("name" => "Import MyBB Attachment Types",
									  "dependencies" => "db_configuration"),
						 "import_attachments" => array("name" => "Import MyBB Attachments",
									  "dependencies" => "db_configuration,import_posts"),
						);
						
	function mybb_db_connect()
	{
		global $import_session;

		// TEMPORARY
		if($import_session['old_db_engine'] != "mysql" && $import_session['old_db_engine'] != "mysqli")
		{
			require_once MYBB_ROOT."inc/db_{$import_session['old_db_engine']}.php";
		}
		$this->old_db = new databaseEngine;

		$this->old_db->connect($import_session['old_db_host'], $import_session['old_db_user'], $import_session['old_db_pass'], 0, true);
		$this->old_db->select_db($import_session['old_db_name']);
		$this->old_db->set_table_prefix($import_session['old_tbl_prefix']);
	}
	
	function db_configuration()
	{
		global $mybb, $output, $import_session, $db, $dboptions, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix;

		// Just posted back to this form?
		if($mybb->input['dbengine'])
		{
			if(!file_exists(MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php"))
			{
				$errors[] = 'You have selected an invalid database engine. Please make your selection from the list below.';
			}
			else
			{
				// Attempt to connect to the db
				// TEMPORARY
				if($mybb->input['dbengine'] != "mysql" && $mybb->input['dbengine'] != "mysqli")
				{
					require_once MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php";
				}
				$this->old_db = new databaseEngine;
				$this->old_db->error_reporting = 0;

				$connection = $this->old_db->connect($mybb->input['dbhost'], $mybb->input['dbuser'], $mybb->input['dbpass'], 0, true);
				if(!$connection)
				{
					$errors[]  = "Could not connect to the database server at '{$mybb->input['dbhost']} with the supplied username and password. Are you sure the hostname and user details are correct?";
				}

				// Select the database
				$dbselect = $this->old_db->select_db($mybb->input['dbname']);
				if(!$dbselect)
				{
					$errors[] = "Could not select the database '{$mybb->input['dbname']}'. Are you sure it exists and the specified username and password have access to it?";
				}

				// Need to check if MyBB is actually installed here
				$this->old_db->set_table_prefix($mybb->input['tableprefix']);
				if(!$this->old_db->table_exists("users"))
				{
					$errors[] = "The MyBB table '{$mybb->input['tableprefix']}users' could not be found in database '{$mybb->input['dbname']}'.  Please ensure MyBB exists at this database and with this table prefix.";
				}

				// No errors? Save import DB info and then return finished
				if(!is_array($errors))
				{
					$import_session['old_db_engine'] = $mybb->input['dbengine'];
					$import_session['old_db_host'] = $mybb->input['dbhost'];
					$import_session['old_db_user'] = $mybb->input['dbuser'];
					$import_session['old_db_pass'] = $mybb->input['dbpass'];
					$import_session['old_db_name'] = $mybb->input['dbname'];
					$import_session['old_tbl_prefix'] = $mybb->input['tableprefix'];
					
					// Create temporary import data fields
					create_import_fields();
					
					return "finished";
				}
			}
		}

		$output->print_header("MyBB Database Configuration");

		// Check for errors
		if(is_array($errors))
		{
			$error_list = error_list($errors);
			echo "<div class=\"error\">
			      <h3>Error</h3>
				  <p>There seems to be one or more errors with the database configuration information that you supplied:</p>
				  {$error_list}
				  <p>Once the above are corrected, continue with the conversion.</p>
				  </div>";
			$dbhost = $mybb->input['dbhost'];
			$dbuser = $mybb->input['dbuser'];
			$dbname = $mybb->input['dbname'];
			$tableprefix = $mybb->input['tableprefix'];
		}
		else
		{
			echo "<p>Please enter the database details for your current installation of MyBB.</p>";
			$dbhost = 'localhost';
			$tableprefix = '';
			$dbuser = '';
			$dbname = '';
		}

		if(function_exists('mysqli_connect'))
		{
			$dboptions['mysqli'] = 'MySQL Improved';
		}
		
		if(function_exists('mysql_connect'))
		{
			$dboptions['mysql'] = 'MySQL';
		}

		foreach($dboptions as $dbfile => $dbtype)
		{
			$dbengines .= "<option value=\"{$dbfile}\">{$dbtype}</option>";
		}

		$output->print_database_details_table("MyBB");
		
		$output->print_footer();
	}
	
	function import_usergroups()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("usergroups", "COUNT(*) as count");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_usergroups'])
		{
			// If there are more usergroups to do, continue, or else, move onto next module
			if($import_session['total_usergroups'] - $import_session['start_usergroups'] <= 0)
			{
				$import_session['disabled'][] = 'import_usergroups';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['usergroups_per_screen']))
		{
			$import_session['usergroups_per_screen'] = intval($mybb->input['usergroups_per_screen']);
		}
		
		if(empty($import_session['usergroups_per_screen']))
		{
			$import_session['start_usergroups'] = 0;
			echo "<p>Please select how many usergroups to import at a time:</p>
<p><input type=\"text\" name=\"usergroups_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_usergroups']-$import_session['start_usergroups'])." usergroups left to import and ".round((($import_session['total_usergroups']-$import_session['start_usergroups'])/$import_session['usergroups_per_screen']))." pages left at a rate of {$import_session['usergroups_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("usergroups");
			
			// Get only non-staff groups.
			$query = $this->old_db->simple_select("usergroups", "*", "gid > 7", array('limit_start' => $import_session['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
			while($group = $this->old_db->fetch_array($query))
			{
				echo "Inserting group #{$group['gid']}... ";
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_group[$field['Field']] = '';
						continue;
					}
					
					if(isset($group[$field['Field']]))
					{
						$insert_group[$field['Field']] = $group[$field['Field']];
					}					
				}
				
				// Make this into a usergroup
				$insert_group['import_gid'] = $group['gid'];
				
				$gid = $this->insert_usergroup($insert_group);
				
				// Restore connections
				$update_array = array('usergroup' => $gid);
				$db->update_query("users", $update_array, "import_usergroup = '{$group['gid']}'");
				$db->update_query("users", $update_array, "import_displaygroup = '{$group['gid']}'");
				
				$this->import_gids = null; // Force cache refresh
				
				echo "done.<br />\n";		
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no usergroups to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_usergroups'] += $import_session['usergroups_per_screen'];
		$output->print_footer();
	}
	
	function import_users()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of members
		if(!isset($import_session['total_members']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count");
			$import_session['total_members'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_users'])
		{
			// If there are more users to do, continue, or else, move onto next module
			if($import_session['total_members'] - $import_session['start_users'] <= 0)
			{
				$import_session['disabled'][] = 'import_users';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);
		
		// Get number of users per screen from form
		if(isset($mybb->input['users_per_screen']))
		{
			$import_session['users_per_screen'] = intval($mybb->input['users_per_screen']);
		}
		
		if(empty($import_session['users_per_screen']))
		{
			$import_session['start_users'] = 0;
			echo "<p>Please select how many users to import at a time:</p>
<p><input type=\"text\" name=\"users_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// Count the total number of users so we can generate a unique id if we have a duplicate user
			$query = $db->simple_select("users", "COUNT(*) as totalusers");
			$total_users = $db->fetch_field($query, "totalusers");
			
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_members']-$import_session['start_users'])." users left to import and ".round((($import_session['total_members']-$import_session['start_users'])/$import_session['users_per_screen']))." pages left at a rate of {$import_session['users_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("users");			
			
			// Get members
			$query = $this->old_db->simple_select("users", "*", "", array('limit_start' => $import_session['start_users'], 'limit' => $import_session['users_per_screen']));

			while($user = $this->old_db->fetch_array($query))
			{
				++$total_users;
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_user[$field['Field']] = '';
						continue;
					}
					
					if(isset($user[$field['Field']]))
					{
						$insert_user[$field['Field']] = $user[$field['Field']];
					}
				}
				
				// Check for duplicate users
				$query1 = $db->simple_select("users", "username,email,uid", " LOWER(username)='".$db->escape_string(my_strtolower($user['username']))."'");
				$duplicate_user = $db->fetch_array($query1);
				if($duplicate_user['username'] && my_strtolower($user['email']) == my_strtolower($duplicate_user['email']))
				{
					echo "Merging user #{$user['uid']} with user #{$duplicate_user['uid']}... ";
					$db->update_query("users", array('import_uid' => $user['uid']), "uid = '{$duplicate_user['uid']}'");
					echo "done.<br />";
					
					continue;
				}
				else if($duplicate_user['username'])
				{					
					$insert_user['username'] = $duplicate_user['username']."_mybb1.4_import".$total_users;
				}
				echo "Adding user #{$user['uid']}... ";
				
				$insert_user['import_uid'] = $user['uid'];
				$insert_user['usergroup'] = $this->get_group_id($user['usergroup'], true);
				$insert_user['additionalgroups'] = str_replace($insert_user['usergroup'], '', $this->get_group_id($user['usergroup']));
				$insert_user['displaygroup'] = $this->get_group_id($user['displaygroup'], true);
				$insert_user['import_usergroup'] = $user['usergroup'];
				$insert_user['import_additionalgroups'] = $user['additionalgroups'];
				
				$this->insert_user($insert_user);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no users to import. Please press next to continue.";
			}
		}
		$import_session['start_users'] += $import_session['users_per_screen'];
		$output->print_footer();
	}
	
	function import_forums()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_forums'])
		{
			// If there are more forums to do, continue, or else, move onto next module
			if($import_session['total_forums'] - $import_session['start_forums'] <= 0)
			{
				$import_session['disabled'][] = 'import_forums';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of forums per screen from form
		if(isset($mybb->input['forums_per_screen']))
		{
			$import_session['forums_per_screen'] = intval($mybb->input['forums_per_screen']);
		}
		
		if(empty($import_session['forums_per_screen']))
		{
			$import_session['start_forums'] = 0;
			echo "<p>Please select how many forums to import at a time:</p>
<p><input type=\"text\" name=\"forums_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{	
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_forums']-$import_session['start_forums'])." forums left to import and ".round((($import_session['total_forums']-$import_session['start_forums'])/$import_session['forums_per_screen']))." pages left at a rate of {$import_session['forums_per_screen']} per page.<br /><br />";
		
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("forums");

			$query = $this->old_db->simple_select("forums", "*", "", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen'], 'order_by' => 'type', 'order_dir' => 'asc'));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['fid']}... ";

				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_forum[$field['Field']] = '';
						continue;
					}

					if(isset($forum[$field['Field']]))
					{
						$insert_forum[$field['Field']] = $forum[$field['Field']];
					}
				}

				$insert_forum['import_fid'] = $forum['fid'];
				$insert_forum['lastposter'] = $this->get_import_username($forum['lastposteruid']);
				$insert_forum['lastposteruid'] = $this->get_import_uid($forum['lastposteruid']);
				$insert_forum['lastposttid'] = (-1 * $forum['lastposttid']);

				$fid = $this->insert_forum($insert_forum);

				// Update parent list.
				if($insert_forum['type'] == 'c')
				{
					$update_array = array('parentlist' => $fid);
				}
				else
				{
					$update_array = array('parentlist' => $insert_forum['pid'].','.$fid);
				}
				$db->update_query("forums", $update_array, "fid = '{$fid}'");
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no forums to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_forums'] += $import_session['forums_per_screen'];
		$output->print_footer();	
	}
	
	function import_threads()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("threads", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_threads'])
		{
			// If there are more threads to do, continue, or else, move onto next module
			if($import_session['total_threads'] - $import_session['start_threads'] <= 0)
			{
				$import_session['disabled'][] = 'import_threads';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of threads per screen from form
		if(isset($mybb->input['threads_per_screen']))
		{
			$import_session['threads_per_screen'] = intval($mybb->input['threads_per_screen']);
		}
		
		if(empty($import_session['threads_per_screen']))
		{
			$import_session['start_threads'] = 0;
			echo "<p>Please select how many threads to import at a time:</p>
<p><input type=\"text\" name=\"threads_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_threads']-$import_session['start_threads'])." threads left to import and ".round((($import_session['total_threads']-$import_session['start_threads'])/$import_session['threads_per_screen']))." pages left at a rate of {$import_session['threads_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("threads");

			$query = $this->old_db->simple_select("threads", "*", "", array('limit_start' => $import_session['start_threads'], 'limit' => $import_session['threads_per_screen']));
			while($thread = $this->old_db->fetch_array($query))
			{
				echo "Inserting thread #{$thread['tid']}... ";				
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_thread[$field['Field']] = '';
						continue;
					}
					
					if(isset($thread[$field['Field']]))
					{
						$insert_thread[$field['Field']] = $thread[$field['Field']];
					}
				}
				
				$insert_thread['import_tid'] = $thread['tid'];
				$insert_thread['fid'] = $this->get_import_fid($thread['fid']);
				$insert_thread['uid'] = $this->get_import_uid($thread['uid']);
				$insert_thread['username'] = $this->get_import_username($thread['uid']);
				$insert_thread['lastposteruid'] = $this->get_import_uid($thread['lastposteruid']);
				$insert_thread['lastposter'] = $this->get_import_username($thread['lastposteruid']);
				$insert_thread['firstpost'] = (-1 * $thread['firstpost']);
				$insert_thread['icon'] = (-1 * $thread['icon']);
				
				if($thread['poll'] != 0)
				{
					$insert_thread['poll'] = (-1 * $thread['pid']);
				}
				
				$tid = $this->insert_thread($insert_thread);
				
				// Restore connections
				$db->update_query("forums", array('lastposttid' => $tid), "lastposttid = '".(-1 * $thread['tid'])."'");
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no threads to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_threads'] += $import_session['threads_per_screen'];
		$output->print_footer();
	}
	
	function import_icons()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();
		
		if(!isset($import_session['bburl']))
		{
			$query = $this->old_db->simple_select("settings", "value", "name = 'bburl'");
			$import_session['bburl'] = $this->old_db->fetch_field($query, "value").'/';
		}

		// Get number of threads
		if(!isset($import_session['total_icons']))
		{
			$query = $this->old_db->simple_select("icons", "COUNT(*) as count", "iid > 16");
			$import_session['total_icons'] = $this->old_db->fetch_field($query, 'count');			
		}

		if($import_session['start_icons'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_icons'] - $import_session['start_icons'] <= 0)
			{
				$import_session['disabled'][] = 'import_icons';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['icons_per_screen']))
		{
			$import_session['icons_per_screen'] = intval($mybb->input['icons_per_screen']);
		}
		
		if(empty($import_session['icons_per_screen']))
		{
			$import_session['start_icons'] = 0;
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_icons']-$import_session['start_icons'])." icons left to import and ".round((($import_session['total_icons']-$import_session['start_icons'])/$import_session['icons_per_screen']))." pages left at a rate of {$import_session['icons_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("icons");
			
			$query = $this->old_db->simple_select("icons", "*", "iid > 16", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($icon = $this->old_db->fetch_array($query))
			{
				echo "Inserting icon #{$icon['iid']}... ";
				flush(); // Show status as soon as possible to avoid inconsistent status reporting
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_smilie[$field['Field']] = '';
						continue;
					}
					
					if(isset($smilie[$field['Field']]))
					{
						$insert_smilie[$field['Field']] = $smilie[$field['Field']];
					}
				}
				
				// MyBB values
				$insert_icon['import_iid'] = $icon['iid'];
				$insert_icon['path'] = "images/icons".substr(strrchr($icon['path'], "/"), 1);
				
				$iid = $this->insert_icon($insert_icon);
				
				// Restore connections
				$db->update_query("threads", array('icon' => $iid), "icon = '".((-1) * $icon['iid'])."'");
				
				// Transfer the icon
				if(file_exists($import_session['bburl'].$icon['path']))
				{
					$icondata = file_get_contents($import_session['bburl'].$icon['path']);
					$file = fopen(MYBB_ROOT.$insert_icon['path'], 'w');
					fwrite($file, $icondata);
					fclose($file);
					@chmod(MYBB_ROOT.$insert_icon['path'], 0777);
					$transfer_error = "";
				}
				else
				{
					$transfer_error = " (Note: Could not transfer attachment icon. - \"Not Found\")";
				}
				echo "done.{$transfer_error}<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no icons to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_icons'] += $import_session['icons_per_screen'];
		$output->print_footer();
	}
		
	function import_polls()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("polls", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_polls'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_polls'] - $import_session['start_polls'] <= 0)
			{
				$import_session['disabled'][] = 'import_polls';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['polls_per_screen']))
		{
			$import_session['polls_per_screen'] = intval($mybb->input['polls_per_screen']);
		}
		
		if(empty($import_session['polls_per_screen']))
		{
			$import_session['start_polls'] = 0;
			echo "<p>Please select how many polls to import at a time:</p>
<p><input type=\"text\" name=\"polls_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_polls']-$import_session['start_polls'])." polls left to import and ".round((($import_session['total_polls']-$import_session['start_polls'])/$import_session['polls_per_screen']))." pages left at a rate of {$import_session['polls_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("polls");

			$query = $this->old_db->simple_select("polls", "*", "", array('limit_start' => $import_session['start_polls'], 'limit' => $import_session['polls_per_screen']));
			while($poll = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll #{$poll['pid']}... ";				
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_poll[$field['Field']] = '';
						continue;
					}
					
					if(isset($poll[$field['Field']]))
					{
						$insert_poll[$field['Field']] = $poll[$field['Field']];
					}
				}

				$insert_poll['import_pid'] = $poll['pid'];
				$insert_poll['tid'] = $this->get_import_tid($poll['tid']);

				$pid = $this->insert_poll($insert_poll);

				// Restore connections
				$db->update_query("threads", array('poll' => $pid), "poll = '".(-1 * $poll['pid'])."'");

				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no polls to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_polls'] += $import_session['polls_per_screen'];
		$output->print_footer();
	}
	
	function import_pollvotes()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("pollvotes", "COUNT(*) as count");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_pollvotes'])
		{
			// If there are more poll votes to do, continue, or else, move onto next module
			if($import_session['total_pollvotes'] - $import_session['start_pollvotes'] <= 0)
			{
				$import_session['disabled'][] = 'import_pollvotes';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of poll votes per screen from form
		if(isset($mybb->input['pollvotes_per_screen']))
		{
			$import_session['pollvotes_per_screen'] = intval($mybb->input['pollvotes_per_screen']);
		}
		
		if(empty($import_session['pollvotes_per_screen']))
		{
			$import_session['start_pollvotes'] = 0;
			echo "<p>Please select how many poll votes to import at a time:</p>
<p><input type=\"text\" name=\"pollvotes_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_pollvotes']-$import_session['start_pollvotes'])." poll votes left to import and ".round((($import_session['total_pollvotes']-$import_session['start_pollvotes'])/$import_session['pollvotes_per_screen']))." pages left at a rate of {$import_session['pollvotes_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("pollvotes");

			$query = $this->old_db->simple_select("pollvotes", "*", "", array('limit_start' => $import_session['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
			while($pollvote = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll vote #{$pollvote['vid']}... ";				
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_pollvote[$field['Field']] = '';
						continue;
					}
					
					if(isset($pollvote[$field['Field']]))
					{
						$insert_pollvote[$field['Field']] = $pollvote[$field['Field']];
					}
				}
				
				$insert_pollvote['uid'] = $this->get_import_uid($pollvote['uid']);
				$insert_pollvote['pid'] = $this->get_import_pid($pollvote['pid']);
				
				$this->insert_pollvote($insert_pollvote);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no poll votes to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_pollvotes'] += $import_session['pollvotes_per_screen'];
		$output->print_footer();
	}
	
	function import_posts()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_posts'])
		{
			// If there are more posts to do, continue, or else, move onto next module
			if($import_session['total_posts'] - $import_session['start_posts'] <= 0)
			{
				$import_session['disabled'][] = 'import_posts';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['posts_per_screen']))
		{
			$import_session['posts_per_screen'] = intval($mybb->input['posts_per_screen']);
		}
		
		if(empty($import_session['posts_per_screen']))
		{
			$import_session['start_posts'] = 0;
			echo "<p>Please select how many posts to import at a time:</p>
<p><input type=\"text\" name=\"posts_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{	
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_posts']-$import_session['start_posts'])." posts left to import and ".round((($import_session['total_posts']-$import_session['start_posts'])/$import_session['posts_per_screen']))." pages left at a rate of {$import_session['posts_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("posts");
			
			$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $import_session['start_posts'], 'limit' => $import_session['posts_per_screen']));
			while($post = $this->old_db->fetch_array($query))
			{
				echo "Inserting post #{$post['pid']}... ";
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_post[$field['Field']] = '';
						continue;
					}
					
					if(isset($post[$field['Field']]))
					{
						$insert_post[$field['Field']] = $post[$field['Field']];
					}
				}
				
				$insert_post['import_pid'] = $post['pid'];
				$insert_post['tid'] = $this->get_import_tid($post['tid']);
				$insert_post['fid'] = $this->get_import_fid($post['fid']);
				$insert_post['uid'] = $this->get_import_uid($post['uid']);
				$insert_post['username'] = $this->get_import_username($post['uid']);
								
				$pid = $this->insert_post($insert_post);
				
				// Update thread count
				update_thread_count($post['tid']);
				
				// Restore firstpost connections
				$db->update_query("threads", array('firstpost' => $pid), "firstpost = '".(-1 * $post['pid'])."'");
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no posts to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_posts'] += $import_session['posts_per_screen'];
		$output->print_footer();
	}
	
	function import_attachments()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Set uploads path
		if(!isset($import_session['uploadspath']))
		{
			$query = $this->old_db->query("settings", "value", "name = 'uploadspath'", array('limit' => 1));
			$import_session['uploadspath'] = $this->old_db->fetch_field($query, 'value');
		}

		// Get number of threads
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_attachments'])
		{
			// If there are more attachments to do, continue, or else, move onto next module
			if($import_session['total_attachments'] - $import_session['start_attachments'] <= 0)
			{
				$import_session['disabled'][] = 'import_attachments';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['attachments_per_screen']))
		{
			$import_session['attachments_per_screen'] = intval($mybb->input['attachments_per_screen']);
		}
		
		if(empty($import_session['attachments_per_screen']))
		{
			$import_session['start_attachments'] = 0;
			echo "<p>Please select how many attachments to import at a time:</p>
<p><input type=\"text\" name=\"attachments_per_screen\" value=\"10\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_attachments']-$import_session['start_attachments'])." attachments left to import and ".round((($import_session['total_attachments']-$import_session['start_attachments'])/$import_session['attachments_per_screen']))." pages left at a rate of {$import_session['attachments_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("attachments");

			$query = $this->old_db->simple_select("attachments", "*", "", array('limit_start' => $import_session['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
			while($attachment = $this->old_db->fetch_array($query))
			{
				echo "Inserting attachment #{$attachment['aid']}... ";				
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_attachment[$field['Field']] = '';
						continue;
					}
					
					if(isset($attachment[$field['Field']]))
					{
						$insert_attachment[$field['Field']] = $attachment[$field['Field']];
					}
				}

				$insert_attachment['import_aid'] = $attachment['aid'];
				$insert_attachment['pid'] = $this->get_import_pid($attachment['pid']);
				$insert_attachment['uid'] = $this->get_import_uid($attachment['uid']);
				$attachname_array = explode('_', $attachment['attachname']);
				$insert_attachment['attachname'] = 'post_'.$this->get_import_uid($attachname_array[1]).'_'.$attachname_array[2].'.attach';
				
				$thumb_not_exists = "";
				if($attachment['thumbnail'])
				{
					$ext = get_extension($attachment['thumbnail']);
					$insert_attachment['thumbnail'] = str_replace(".attach", "_thumb.$ext", $insert_attachment['attachname']);
					
					// Transfer attachment thumbnail
					if(file_exists($import_session['uploadspath'].'/'.$attachment['attach_thumb_location']))
					{
						$thumbattachmentdata = file_get_contents($import_session['uploadspath'].'/'.$attachment['attach_thumb_location']);
						$file = fopen($mybb->settings['uploadspath'].'/'.$insert_attachment['thumbnail'], 'w');
						fwrite($file, $thumbattachmentdata);
						fclose($file);
						@chmod($mybb->settings['uploadspath'].'/'.$insert_attachment['thumbnail'], 0777);
					}
					else
					{
						$thumb_not_exists = "Could not find the attachment thumbnail.";
					}
				}

				$this->insert_attachment($insert_attachment);
				
				// Transfer attachment
				if(file_exists($import_session['uploadspath'].'/'.$attachment['attachname']))
				{
					$attachmentdata = file_get_contents($import_session['uploadspath'].'/'.$attachment['attachname']);
					$file = fopen($mybb->settings['uploadspath'].'/'.$insert_attachment['attachname'], 'w');
					fwrite($file, $attachmentdata);
					fclose($file);
					@chmod($mybb->settings['uploadspath'].'/'.$insert_attachment['attachname'], 0777);
					$attach_not_exists = "";
				}
				else
				{
					$attach_not_exists = "Could not find the attachment.";
				}
				
				// Restore connection
				$db->update_query("posts", array('posthash' => $insert_attachment['posthash']), "pid = '{$insert_attachment['pid']}'");
				
				$error_notice = "";
				if($attach_not_exists || $thumb_not_exists)
				{
					$error_notice = "(Note: $attach_not_exists $thumb_not_exists)";
				}
				echo "done.{$error_notice}<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no attachments to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_attachments'] += $import_session['attachments_per_screen'];
		$output->print_footer();
	}
	
	function import_moderators()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of moderators
		if(!isset($import_session['total_mods']))
		{
			$query = $this->old_db->simple_select("moderators", "COUNT(*) as count");
			$import_session['total_mods'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_mods'])
		{
			// If there are more moderators to do, continue, or else, move onto next module
			if($import_session['total_mods'] - $import_session['start_mods'] <= 0)
			{
				$import_session['disabled'][] = 'import_moderators';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['mods_per_screen']))
		{
			$import_session['mods_per_screen'] = intval($mybb->input['mods_per_screen']);
		}
		
		if(empty($import_session['mods_per_screen']))
		{
			$import_session['start_mods'] = 0;
			echo "<p>Please select how many moderators to import at a time:</p>
<p><input type=\"text\" name=\"mods_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_mods']-$import_session['start_mods'])." moderators left to import and ".round((($import_session['total_mods']-$import_session['start_mods'])/$import_session['mods_per_screen']))." pages left at a rate of {$import_session['mods_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("moderators");
			
			$query = $this->old_db->simple_select("moderators", "*", "", array('limit_start' => $import_session['start_mods'], 'limit' => $import_session['mods_per_screen']));
			while($mod = $this->old_db->fetch_array($query))
			{
				echo "Inserting user #{$mod['uid']} as moderator to forum #{$mod['fid']}... ";
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_mod[$field['Field']] = '';
						continue;
					}
					
					if(isset($mod[$field['Field']]))
					{
						$insert_mod[$field['Field']] = $mod[$field['Field']];
					}
				}
				
				$insert_mod['fid'] = $this->get_import_fid($mod['fid']);
				$insert_mod['uid'] = $this->get_import_uid($mod['uid']);

				$this->insert_moderator($insert_mod);
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no moderators to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_mods'] += $import_session['mods_per_screen'];
		$output->print_footer();
	}
	
	function import_privatemessages()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("privatemessages", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_privatemessages'])
		{
			// If there are more usergroups to do, continue, or else, move onto next module
			if($import_session['total_privatemessages'] - $import_session['start_privatemessages'] <= 0)
			{
				$import_session['disabled'][] = 'import_privatemessages';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['privatemessages_per_screen']))
		{
			$import_session['privatemessages_per_screen'] = intval($mybb->input['privatemessages_per_screen']);
		}
		
		if(empty($import_session['privatemessages_per_screen']))
		{
			$import_session['start_privatemessages'] = 0;
			echo "<p>Please select how many Private Messages to import at a time:</p>
<p><input type=\"text\" name=\"privatemessages_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_privatemessages']-$import_session['start_privatemessages'])." private messages left to import and ".round((($import_session['total_privatemessages']-$import_session['start_privatemessages'])/$import_session['privatemessages_per_screen']))." pages left at a rate of {$import_session['privatemessages_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("moderators");
			
			$query = $this->old_db->simple_select("privatemessages", "*", "", array('limit_start' => $import_session['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));
			
			while($pm = $this->old_db->fetch_array($query))
			{
				echo "Inserting Private Message #{$pm['pmid']}... ";
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_pm[$field['Field']] = '';
						continue;
					}
					
					if(isset($pm[$field['Field']]))
					{
						$insert_pm[$field['Field']] = $pm[$field['Field']];
					}
				}
				
				$insert_pm['import_pmid'] = $pm['pmid'];
				$insert_pm['uid'] = $this->get_import_uid($pm['uid']);
				$insert_pm['fromid'] = $this->get_import_uid($pm['fromid']);
				$insert_pm['toid'] = $this->get_import_uid($pm['toid']);
				
				$touserarray = unserialize($pm['recipients']);
				
				// Rebuild the recipients array
				$recipients = array();
				foreach($touserarray['to'] as $key => $uid)
				{
					$username = $this->get_import_uid($uid);		
					$recipients['to'][] = $this->get_import_username($username['uid']);
				}
				$insert_pm['recipients'] = serialize($recipients);

				$this->insert_privatemessage($insert_pm);
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no private messages to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_privatemessages'] += $import_session['privatemessages_per_screen'];
		$output->print_footer();
	}	
	
	function import_smilies()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();
		
		if(!isset($import_session['bburl']))
		{
			$query = $this->old_db->simple_select("settings", "value", "name = 'bburl'");
			$import_session['bburl'] = $this->old_db->fetch_field($query, "value").'/';
		}

		// Get number of threads
		if(!isset($import_session['total_smilies']))
		{
			$query = $this->old_db->simple_select("smilies", "COUNT(*) as count", "sid > 9");
			$import_session['total_smilies'] = $this->old_db->fetch_field($query, 'count');			
		}

		if($import_session['start_smilies'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_smilies'] - $import_session['start_smilies'] <= 0)
			{
				$import_session['disabled'][] = 'import_smilies';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['smilies_per_screen']))
		{
			$import_session['smilies_per_screen'] = intval($mybb->input['smilies_per_screen']);
		}
		
		if(empty($import_session['smilies_per_screen']))
		{
			$import_session['start_icons'] = 0;
			echo "<p>Please select how many smilies to import at a time:</p>
<p><input type=\"text\" name=\"smilies_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_smilies']-$import_session['start_smilies'])." smilies left to import and ".round((($import_session['total_smilies']-$import_session['start_smilies'])/$import_session['smilies_per_screen']))." pages left at a rate of {$import_session['smilies_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("smilies");
			
			$query = $this->old_db->simple_select("smilies", "*", "sid > 9", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($smilie = $this->old_db->fetch_array($query))
			{
				echo "Inserting smilie #{$smilie['sid']}... ";
				flush(); // Show status as soon as possible to avoid inconsistent status reporting
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_smilie[$field['Field']] = '';
						continue;
					}
					
					if(isset($smilie[$field['Field']]))
					{
						$insert_smilie[$field['Field']] = $smilie[$field['Field']];
					}
				}
				
				// MyBB values
				$insert_smilie['path'] = "images/smilies/".substr(strrchr($smilie['path'], "/"), 1);
			
				$this->insert_smilie($insert_smilie);
				
				// Transfer smilie
				if(file_exists($import_session['bburl'].$smilie['path']))
				{
					$smiliedata = file_get_contents($import_session['bburl'].$smilie['path']);
					$file = fopen(MYBB_ROOT.$insert_smilie['path'], 'w');
					fwrite($file, $smiliedata);
					fclose($file);
					@chmod(MYBB_ROOT.$insert_smilie['path'], 0777);
					$transfer_error = "";
				}
				else
				{
					$transfer_error = " (Note: Could not transfer attachment icon. - \"Not Found\")\n";
				}
				
				echo "done.{$transfer_error}<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no smilies to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_smilies'] += $import_session['smilies_per_screen'];
		$output->print_footer();
	}

	function import_settings()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of settings
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("settings", "COUNT(*) as count");
			$import_session['total_settings'] = $this->old_db->fetch_field($query, 'count');		
		}

		if($import_session['start_settings'])
		{
			// If there are more settings to do, continue, or else, move onto next module
			if($import_session['total_settings'] - $import_session['start_settings'] <= 0)
			{
				$import_session['disabled'][] = 'import_settings';
				rebuildsettings();
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of settings per screen from form
		if(isset($mybb->input['settings_per_screen']))
		{
			$import_session['settings_per_screen'] = intval($mybb->input['settings_per_screen']);
		}

		if(empty($import_session['settings_per_screen']))
		{
			$import_session['start_settings'] = 0;
			echo "<p>Please select how many settings to modify at a time:</p>
<p><input type=\"text\" name=\"settings_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_settings']-$import_session['start_settings'])." settings left to import and ".round((($import_session['total_settings']-$import_session['start_settings'])/$import_session['settings_per_screen']))." pages left at a rate of {$import_session['settings_per_screen']} per page.<br /><br />";

			$x = 0;

			$query = $this->old_db->simple_select("settings", "name, value", "sid < 149", array('limit_start' => $import_session['start_settings'], 'limit' => $import_session['settings_per_screen']));
			while($setting = $this->old_db->fetch_array($query))
			{
				echo "Updating setting {$setting['name']} from your other MyBB database... ";
			
				$this->update_setting($setting['name'], $setting['value']);
				
				echo "done.<br />\n";
				++$x;
			}
	
			if($this->old_db->num_rows($query) == 0)
			{
				$no_settings = true;
			}
			else
			{
				$no_settings = false;
			}

			if($x < $import_session['settings_per_screen'])
			{
				$query = $this->old_db->simple_select("settings", "name, value", "sid > 148", array('limit_start' => $import_session['start_settings']+$x, 'limit' => $import_session['settings_per_screen']-$x));
				while($setting = $this->old_db->fetch_array($query))
				{
					echo "Inserting setting {$setting['name']} from your other MyBB database... ";

					$insert_setting['name'] = $setting['name'];
					$insert_setting['title'] = $setting['title'];
					$insert_setting['description'] = $setting['description'];
					$insert_setting['optionscode'] = $setting['optionscode'];
					$insert_setting['value'] = $setting['value'];
					$insert_setting['disporder'] = $setting['disporder'];
					$insert_setting['gid'] = $this->get_import_settinggroup($setting['gid']);

					$this->insert_setting($insert_setting);

					echo "done.<br />\n";
				}

				if($this->old_db->num_rows($query) > 0)
				{
					$no_settings = false;
				}
			}

			if($no_settings)
			{
				echo "There are no settings to update. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_settings'] += $import_session['settings_per_screen'];
		$output->print_footer();
	}
	
	function import_settinggroups()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of settings
		if(!isset($import_session['total_settinggroups']))
		{
			$query = $this->old_db->simple_select("settinggroups", "COUNT(*) as count");
			$import_session['total_settinggroups'] = $this->old_db->fetch_field($query, 'count');		
		}

		if($import_session['start_settinggroups'])
		{
			// If there are more settings to do, continue, or else, move onto next module
			if($import_session['total_settinggroups'] - $import_session['start_settinggroups'] <= 0)
			{
				$import_session['disabled'][] = 'import_settinggroups';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of settings per screen from form
		if(isset($mybb->input['settinggroups_per_screen']))
		{
			$import_session['settinggroups_per_screen'] = intval($mybb->input['settinggroups_per_screen']);
		}

		if(empty($import_session['settinggroups_per_screen']))
		{
			$import_session['start_settinggroups'] = 0;
			echo "<p>Please select how many settinggroups to insert at a time:</p>
<p><input type=\"text\" name=\"settinggroups_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_settinggroups']-$import_session['start_settinggroups'])." settings left to import and ".round((($import_session['total_settinggroups']-$import_session['start_settinggroups'])/$import_session['settinggroups_per_screen']))." pages left at a rate of {$import_session['settinggroups_per_screen']} per page.<br /><br />";

			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("settinggroups");

			$query = $this->old_db->simple_select("settinggroups", "*", "isdefault != 'yes'", array('limit_start' => $import_session['start_settinggroups'], 'limit' => $import_session['settinggroups_per_screen']));
			while($settinggroup = $this->old_db->fetch_array($query))
			{
				echo "Inserting setting group {$settinggroup['name']} from your other MyBB database... ";
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_settinggroup[$field['Field']] = '';
						continue;
					}
					
					if(isset($settinggroup[$field['Field']]))
					{
						$insert_settinggroup[$field['Field']] = $settinggroup[$field['Field']];
					}
				}

				$insert_settinggroup['import_gid'] = $settinggroup['gid'];

				$this->insert_settinggroup($insert_settinggroup);

				echo "done.<br />\n";
			}

			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no custom setting groups to insert. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_settinggroups'] += $import_session['settinggroups_per_screen'];
		$output->print_footer();
	}
	
	function import_events()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_events']))
		{
			$query = $this->old_db->simple_select("events", "COUNT(*) as count");
			$import_session['total_events'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_events'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_events'] - $import_session['start_events'] <= 0)
			{
				$import_session['disabled'][] = 'import_events';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['events_per_screen']))
		{
			$import_session['events_per_screen'] = intval($mybb->input['events_per_screen']);
		}
		
		if(empty($import_session['events_per_screen']))
		{
			$import_session['start_events'] = 0;
			echo "<p>Please select how many events to import at a time:</p>
<p><input type=\"text\" name=\"events_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_events']-$import_session['start_events'])." events left to import and ".round((($import_session['total_events']-$import_session['start_events'])/$import_session['events_per_screen']))." pages left at a rate of {$import_session['events_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("events");

			$query = $this->old_db->simple_select("events", "*", "", array('limit_start' => $import_session['start_events'], 'limit' => $import_session['events_per_screen']));
			while($event = $this->old_db->fetch_array($query))
			{
				echo "Inserting event #{$event['eid']}... ";				
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_event[$field['Field']] = '';
						continue;
					}
					
					if(isset($event[$field['Field']]))
					{
						$insert_event[$field['Field']] = $event[$field['Field']];
					}
				}

				$insert_event['import_eid'] = $event['eid'];

				$this->insert_event($insert_event);

				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no events to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_events'] += $import_session['events_per_screen'];
		$output->print_footer();
	}
	
	function import_attachtypes()
	{
		global $mybb, $output, $import_session, $db;

		$this->mybb_db_connect();
		
		if(!isset($import_session['bburl']))
		{
			$query = $this->old_db->simple_select("settings", "value", "name = 'bburl'");
			$import_session['bburl'] = $this->old_db->fetch_field($query, "value").'/';
		}

		// Get number of attachment types
		if(!isset($import_session['total_attachtypes']))
		{
			$query = $this->old_db->simple_select("attachtypes", "COUNT(*) as count");
			$import_session['total_attachtypes'] = $this->old_db->fetch_field($query, 'count');
		}

		if($import_session['start_attachtypes'])
		{
			// If there are more attachment types to do, continue, or else, move onto next module
			if($import_session['total_attachtypes'] - $import_session['start_attachtypes'] <= 0)
			{
				$import_session['disabled'][] = 'import_attachtypes';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of attachment types per screen from form
		if(isset($mybb->input['attachtypes_per_screen']))
		{
			$import_session['attachtypes_per_screen'] = intval($mybb->input['attachtypes_per_screen']);
		}
		
		if(empty($import_session['attachtypes_per_screen']))
		{
			$import_session['start_attachtypes'] = 0;
			echo "<p>Please select how many attachment types to import at a time:</p>
<p><input type=\"text\" name=\"attachtypes_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_attachtypes']-$import_session['start_attachtypes'])." attachment types left to import and ".round((($import_session['total_attachtypes']-$import_session['start_attachtypes'])/$import_session['attachtypes_per_screen']))." pages left at a rate of {$import_session['attachtypes_per_screen']} per page.<br /><br />";
			
			// Get existing attachment types
			$query = $db->simple_select("attachtypes", "extension");
			while($row = $db->fetch_array($query))
			{
				$existing_types[$row['extension']] = true;
			}
			
			$query = $this->old_db->simple_select("attachtypes", "*", "", array('limit_start' => $import_session['start_attachtypes'], 'limit' => $import_session['attachtypes_per_screen']));
			while($type = $this->old_db->fetch_array($query))
			{
				echo "Inserting attachment type #{$type['atid']}... ";
				
				foreach($field_info as $key => $field)
				{
					if($field['Extra'] == 'auto_increment')
					{
						$insert_attachtype[$field['Field']] = '';
						continue;
					}
					
					if(isset($type[$field['Field']]))
					{
						$insert_attachtype[$field['Field']] = $type[$field['Field']];
					}					
				}		

				$insert_attachtype['import_atid'] = $type['atid'];
				$insert_attachtype['icon'] = 'images/attachtypes/'.substr(strrchr($attachtype['icon'], "/"), 1);
				
				$this->insert_attachtype($insert_attachtype);
				
				echo "done.";
					
				if(isset($existing_types[$type['extension']]))
				{
					echo " (Note: extension already exists)\n";
				}
				
				// Transfer attachment icon
				if(file_exists($import_session['bburl'].$attachtype['icon']))
				{
					$attachicondata = file_get_contents($import_session['bburl'].$attachtype['icon']);
					$file = fopen(MYBB_ROOT.$insert_attachtype['icon'], 'w');
					fwrite($file, $attachicondata);
					fclose($file);
					@chmod(MYBB_ROOT.$insert_attachtype['icon'], 0777);
				}
				else
				{
					echo " (Note: Could not transfer attachment icon. - \"Not Found\")\n";
				}
				
				echo "<br />\n";
				++$i;
			}
			
			if($import_session['total_attachtypes'] == 0)
			{
				echo "There are no attachment types to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_attachtypes'] += $import_session['attachtypes_per_screen'];
		$output->print_footer();
	}
	/**
	 * Get a user from the MyBB database
	 *
	 * @param int Username
	 * @return array If the username is empty, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_username($username)
	{
		if($username == '')
		{
			return array(
				'username' => 'Guest',
				'uid' => 0,
			);
		}
		
		$query = $this->old_db->simple_select("users", "*", "username='{$username}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Convert a MyBB group ID into a MyBB group ID (merge)
	 *
	 * @param int Group ID
	 * @param boolean single group or multiple?
	 * @param boolean original group values?
	 * @return mixed group id(s)
	 */
	function get_group_id($gid, $not_multiple=false, $orig=false)
	{
		$settings = array();
		if($not_multiple == false)
		{
			$query = $this->old_db->simple_select("usergroups", "COUNT(*) as rows", "gid='{$gid}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
		}
		
		$query = $this->old_db->simple_select("usergroups", "*", "gid='{$gid}'", $settings);
		
		$comma = $group = '';
		while($mybbgroup = $this->old_db->fetch_array($query))
		{
			if($orig == true)
			{
				$group .= $mybbgroup['gid'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($mybbgroup['gid'])
				{
					case 5: // Awaiting activation
						$group .= 5;
						break;
					case 1: // Guests
						$group .= 1;
					case 2: // Registered
						$group .= 2;
						break;
					case 7: // Banned
						$group .= 7;
						break;
					case 4: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import_gid($mybbgroup['gid']);
						if($gid > 0)
						{
							// If there is an associated custom group...
							$group .= $gid;
						}
						else
						{
							// The lot
							$group .= 2;
						}					
				}			
			}
			$comma = ',';
			
			if(!$query)
			{
				return 2; // Return regular registered user.
			}			
	
			return $group;
		}
	}
}

?>