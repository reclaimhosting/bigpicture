<?php

class i_bigpicturecallingcard_0_6_install extends i_action_install
{
public function step2_init() {}
public function step2_process()
{
	
		$this->extract("main");
		$this->extract("data");
		$this->mv('wordpress/*');
		$this->rm('wordpress');
		$this->mkdir(array('wp-content/uploads','wp-content/languages', "wp-content/uploads/et_temp"));
		$this->chmod("wp-content", 0666, 0777, true);
		$this->chmod("wp-content/index.php", 0644);

		$urlinfo = parse_url($this->url);
		$this->write(".htaccess",'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase '.$urlinfo["path"].'/
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . '.$urlinfo["path"].'/index.php [L]
</IfModule>

# END WordPress');

		$this->cp('wp-config-sample.php','wp-config.php');
		$r = array(
			"#'localhost'#"			=> var_export($this->db_host,true),
			"#'username_here'#"		=> var_export($this->db_user,true),
			"#'database_name_here'#"	=> var_export($this->db_name,true),
			"#'password_here'#"	=> var_export($this->db_pass,true),
			"#'wp_'#"				=> var_export($this->db_prefix,true),
			"#define\('AUTH_KEY',.+'put your unique phrase here'\);#s" //matches whole block
				=>
"define('AUTH_KEY',         ".var_export(i_lib::randstr(64),true).");
define('SECURE_AUTH_KEY',  ".var_export(i_lib::randstr(64),true).");
define('LOGGED_IN_KEY',    ".var_export(i_lib::randstr(64),true).");
define('NONCE_KEY',        ".var_export(i_lib::randstr(64),true).");
define('AUTH_SALT',        ".var_export(i_lib::randstr(64),true).");
define('SECURE_AUTH_SALT', ".var_export(i_lib::randstr(64),true).");
define('LOGGED_IN_SALT',   ".var_export(i_lib::randstr(64),true).");
define('NONCE_SALT',       ".var_export(i_lib::randstr(64),true).");

/**
 * Other customizations.
 */
"
.( $this->env["has_php_suexec"] === false && $this->env["has_php_safe_mode"] !== false ? "" : "define('FS_METHOD','direct');".( $this->env["has_php_suexec"] !== false ? "define('FS_CHMOD_DIR',0755);define('FS_CHMOD_FILE',0644);" : "define('FS_CHMOD_DIR',0777);define('FS_CHMOD_FILE',0666);" )."\n" )
."define('WP_TEMP_DIR',dirname(__FILE__).'/wp-content/uploads');

/**
 * Turn off automatic updates since these are managed upstream.
 */
define('AUTOMATIC_UPDATER_DISABLED', true);
");
		$this->sr("wp-config.php", $r);
		
		$r = array(
				"#zzct_#" => $this->db_prefix,
				"#http://www.testing.installatron.com/wordpress\d*#" => $this->url
			);

		$this->db_import("install.sql", null, $r);
		$this->rm("install.sql");

		$sql = "";
		$sql .= "UPDATE `{$this->db_prefix}posts` SET post_date=NOW(), post_date_gmt=NOW(), post_modified=NOW(), post_modified_gmt=NOW();\n";
		$sql .= "UPDATE `{$this->db_prefix}users` SET user_pass='".md5($this->input["field_passwd"])."', user_login='".$this->db_escape($this->input["field_login"])."', user_email='".$this->db_escape($this->input["field_email"])."', user_registered=NOW() WHERE ID='1';\n";
		$sql .= "UPDATE `{$this->db_prefix}options` SET `option_value`='".$this->db_escape("$this->path/wp-content/uploads")."' WHERE `option_name`='upload_path';\n";
		$sql .= "UPDATE `{$this->db_prefix}options` SET `option_value`='".$this->db_escape($this->input["field_email"])."' WHERE `option_name`='admin_email';\n";
		$sql .= "UPDATE `{$this->db_prefix}usermeta` SET `meta_value`='0' WHERE `meta_key`='default_password_nag' AND `user_id`='1';\n";

		$sql .= "UPDATE `{$this->db_prefix}options` SET `option_value`='1' WHERE `option_name`='blog_public';\n";

		// use bigpicture theme
		$this->extract("bigpicture");
		$this->mv("wp-bigpicture-master","wp-content/themes/wp-bigpicture");
		$sql .= "UPDATE `{$this->db_prefix}options` SET `option_value`='wp-bigpicture' WHERE `option_name`='stylesheet' OR `option_name`='template';\n";

		// use permalinks instead of query string
		if ( !isset($this->env["has_apache"]) || $this->env["has_apache"] !== false || $this->env["has_nginx"] !== false )
		{
			$sql .= "UPDATE `{$this->db_prefix}options` SET `option_value`='".( $this->ds === "/" ? "/%category%/%postname%/" : "" )."' WHERE `option_name`='permalink_structure';\n";
		}

		$sql .= "INSERT INTO `{$this->db_prefix}options` (`option_name`, `option_value`) VALUES
('ftp_credentials', '".$this->db_escape(serialize(array( "hostname" => "localhost", "username" => $this->input["field_ftpuser"], "connection_type" => "ftp" )))."');\n";

		$this->write("install2.sql",$sql);
		$this->db_import("install2.sql");
		$this->rm("install2.sql");

		$this->chmod(array(".htaccess","wp-config.php"),0666);

                // to fix the upgrade screen after the first install:
		$r = $this->fetch('wp-admin/upgrade.php?step=1', null, null, false);

$this->write("wp-includes/.htaccess","<Files *.php>
deny from all
</Files>
<Files wp-tinymce.php>
allow from all
</Files>
<Files ms-files.php>
allow from all
</Files>");

		$this->rm("readme.html");
		$this->installPlugin("font-awesome-4-menus/n9m-font-awesome-4.php", "fontawesome");
		$this->installPlugin("fluid-video-embeds/fluid-video-embeds.php", "fluidvids");
		$this->installPlugin("wordpress-importer/wordpress-importer.php", "importer");


$this->write("wpinstall.sh", "#!/bin/bash
cd {$this->path}
/usr/local/bin/wp --allow-root --path='{$this->path}' post delete 1 &>> $logfile
/usr/local/bin/wp --allow-root --path='{$this->path}' import data.xml --authors=skip &>> $logfile
");

exec("/usr/bin/nohup /bin/sh {$this->path}/wpinstall.sh &> /dev/null &");
}
}

?>