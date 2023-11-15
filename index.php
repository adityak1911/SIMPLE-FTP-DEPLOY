<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Increase execution time limit (in seconds, 0 means unlimited)
set_time_limit(300); // Example: Set the time limit to 5 minutes

// Increase memory limit (in megabytes)
ini_set('memory_limit', '256M');


// Function to read local files and folders recursively
function readLocalFiles($localDirectory)
{
    $result = [];
    if ($handle = opendir($localDirectory)) {
        foreach (scandir($localDirectory) as $entry) {
            if ($entry != "." && $entry != "..") {
                $localPath = $localDirectory . '/' . $entry;

                if (is_dir($localPath)) {
                    $result[$entry] = readLocalFiles($localPath);
                } else {
                    $result[] = $entry;
                }
            }
        }

        closedir($handle);
    }
    

    return $result;
}

// Function to read remote files and folders recursively
function readRemoteFiles($ftpConnection, $remoteDirectory)
{
    $result = [];

    $contents = ftp_nlist($ftpConnection, $remoteDirectory);

    // Check if the directory exists and is accessible
    if ($contents === false) {
        //echo "Error getting remote directory contents: \n";
    }else{
        
        
        foreach ($contents as $item) {
                $itemPath = $remoteDirectory . '/' . $item;
    
                if (ftp_size($ftpConnection, $itemPath) == -1) {
                    // If it's a directory, recursively read its contents
                    // Exclude folders starting with . or ..
                    if (!(str_starts_with($item, "/.") || str_starts_with($item, "/.." || str_starts_with($item, "..") || str_starts_with($item, ".")))) {
                        $folders = readRemoteFiles($ftpConnection, $itemPath);
                        
                        $result[$item] = $folders;
                        
                    }
                } else {
                    
                    if(in_array(str_replace('/', "", str_replace('.', '', $item)), ['htaccess'])){
                        $result[] = $item;
                    }
                }
            }
        
    }
    return $result;
}



// Function to calculate file hash
function calculateFileHash($filePath)
{
    return md5_file($filePath);
}

// Function to upload files and folders recursively
function uploadFiles($localDirectory, $remoteDirectory, $ftpConnection, $deleteFlag)
{
    // Open the local directory
    if ($handle = opendir($localDirectory)) {
        // Loop through each file/folder in the directory
        while (false !== ($entry = readdir($handle))) {
            // Skip "." and ".." entries
            if ($entry != "." && $entry != "..") {
                $localPath = $localDirectory . '/' . $entry;
                $remotePath = $remoteDirectory . '/' . $entry;

                // Check if the entry is a directory
                if (is_dir($localPath)) {
                    // Check if the remote directory already exists
                    if (!@ftp_chdir($ftpConnection, $remotePath)) {
                        // If it doesn't exist, create it
                        ftp_mkdir($ftpConnection, $remotePath);
                    }

                    // Recursively upload the directory
                    uploadFiles($localPath, $remotePath, $ftpConnection, $deleteFlag);
                } else {
                    // Calculate hash of the local file
                    $localFileHash = calculateFileHash($localPath);

                    // Check if the remote file exists
                    $remoteFileHash = '';
                    if (ftp_size($ftpConnection, $remotePath) != -1) {
                        $tempLocalFile = tempnam(sys_get_temp_dir(), 'temp_local_file');
                        ftp_get($ftpConnection, $tempLocalFile, $remotePath, FTP_BINARY);
                        $remoteFileHash = calculateFileHash($tempLocalFile);
                        unlink($tempLocalFile);
                    }

                    // Upload the file only if it's not present in remote or has different content
                    if ($localFileHash !== $remoteFileHash) {
                        ftp_put($ftpConnection, $remotePath, $localPath, FTP_BINARY);
                        echo "Uploaded: $localPath\n";
                    } else {
                        echo "Skipped (unchanged): $localPath\n";
                    }


                    // delete code has some issues
                    // Delete the remote file if it doesn't exist locally and delete_flag is true
                    if ($deleteFlag && $localFileHash === '' && ftp_size($ftpConnection, $remotePath) != -1) {
                        ftp_delete($ftpConnection, $remotePath);
                        echo "Deleted from remote: $remotePath\n";
                    }
                }
            }
        }

        // Close the directory handle
        closedir($handle);
    }
}

function array_diff_multi($arraya, $arrayb) {

    foreach ($arraya as $keya => $valuea) {
        if (in_array($valuea, $arrayb)) {
            unset($arraya[$keya]);
        }
    }
    return $arraya;
}


// FTP connection settings
$ftpServer = 'ftp.nearo.in';
$ftpUsername = 'sp@capi.nearo.in';
$ftpPassword = '~{fIWi}QnTF4';

// Local and remote directories
$localDirectory = __DIR__.'/';
$remoteDirectory = '/';

// Connect to the FTP server
$ftpConnection = ftp_connect($ftpServer);
if (!$ftpConnection) {
    die('Could not connect to FTP server');
}

// Login to the FTP server
if (!ftp_login($ftpConnection, $ftpUsername, $ftpPassword)) {
    die('FTP login failed');
}

// Set passive mode (optional, depending on your server configuration)
ftp_pasv($ftpConnection, true);



// Read local files and folders
$localFiles = readLocalFiles($localDirectory);

// Read remote files and folders
$remoteFiles = readRemoteFiles($ftpConnection, $remoteDirectory);

//print_r($remoteFiles);

//print_r(array_diff_multi($remoteFiles, $localFiles));


// Check if the remote directory already exists
if (!ftp_chdir($ftpConnection, $remoteDirectory)) {
    // If it doesn't exist, create it
    ftp_mkdir($ftpConnection, $remoteDirectory);
    ftp_chdir($ftpConnection, $remoteDirectory);
}

$deleteFlag = true;

// Upload files and folders
uploadFiles($localDirectory, $remoteDirectory, $ftpConnection, $deleteFlag);

// Close the FTP connection
ftp_close($ftpConnection);

echo 'Upload completed successfully';

?>
