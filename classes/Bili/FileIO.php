<?php

namespace Bili;

/**
 * File IO operations.
 *
 * @package Bili
 * @author felix
 * @version 1.0
 */
class FileIO
{
	public static function extension($filename)
	{
		$path_info = pathinfo($filename);
    	return $path_info['extension'];
	}

	public static function add2Base($filename, $addition)
	{
		$strBase = basename($filename, self::extension($filename));
		return substr($strBase, 0, -1) . $addition . "." . self::extension($filename);
	}

	public static function unlinkDir($dir)
	{
	    $files = glob($dir . '*', GLOB_MARK);
	    foreach ($files as $file) {
	        if (substr($file, -1) == '/') {
	            self::unlinkDir($file);
	        } else {
	            unlink($file);
	        }
	    }

	    if (is_dir($dir)) {
	        rmdir($dir);
	    }
	}

	/**
	 * Convert HTML markup to a binary PDF
	 * @param  string      $strHtml The HTML input
	 * @return binary|null The binary PDF output or null if something went wrong.
	 */
	public static function html2pdf($strHtml, $strFilePrefix = "document")
	{
	    $varReturn = null;

	    srand((double) microtime()*1000000);
	    $random_number = rand();
	    $sid = md5($random_number);

	    $strHash 		= $strFilePrefix . "-" . $sid;
	    $strPdfFile 	= $GLOBALS["_PATHS"]["cache"] . $strHash . ".pdf"; // TODO: Check if global exists.
	    $strHtmlFile 	= $GLOBALS["_PATHS"]["cache"] . $strHash . ".html";

	    file_put_contents($strHtmlFile, $strHtml);
	    $strInput = $strHtmlFile;
	    $strOutput = $strPdfFile;

	    $arrExec = array();
	    $arrExec[] = $GLOBALS["_CONF"]["app"]["wkhtmltopdf"]; // TODO: Check if global exists.
	    $arrExec[] = $strInput;
	    $arrExec[] = $strOutput;
	    $strExec = implode(" ", $arrExec);

	    $blnCreated = exec($strExec);

	    if (file_exists($strPdfFile)) {
	        $varReturn = file_get_contents($strPdfFile);

	        // Clean up
	        @unlink($strHtmlFile);
	        @unlink($strPdfFile);
	    }

	    return $varReturn;
	}

	public static function handleUpload($targetDir)
	{
		// HTTP headers for no cache etc
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		// 5 minutes execution time
		@set_time_limit(5 * 60);

		// Get parameters
		$chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
		$chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;
		$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';
		$fileId = isset($_REQUEST["id"]) ? $_REQUEST["id"] : '';

		// Clean the fileName for security reasons
		$fileName = preg_replace('/[^\w\._]+/', '-', $fileName);
		$originalName = $fileName;

		// Make sure the fileName is unique but only if chunking is disabled
		if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
			$ext = strrpos($fileName, '.');
			$fileName_a = substr($fileName, 0, $ext);
			$fileName_b = substr($fileName, $ext);

			$count = 1;
			while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName_a . '_' . $count . $fileName_b)) {
				$count++;
			}

			$fileName = $fileName_a . '_' . $count . $fileName_b;
		}

		// Create target dir
		if (!file_exists($targetDir)) {
			@mkdir($targetDir);
		}

		// Look for the content type header
		if (isset($_SERVER["HTTP_CONTENT_TYPE"])) {
			$contentType = $_SERVER["HTTP_CONTENT_TYPE"];
		}

		if (isset($_SERVER["CONTENT_TYPE"])) {
			$contentType = $_SERVER["CONTENT_TYPE"];
		}

		// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
		if (strpos($contentType, "multipart") !== false) {
			if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
				// Open temp file
				try {
					$out = @fopen($targetDir . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
					if ($out) {
						// Read binary input stream and append it to temp file
						$in = @fopen($_FILES['file']['tmp_name'], "rb");

						if ($in) {
							while ($buff = fread($in, 4096)) {
								fwrite($out, $buff);
							}
						} else {
							die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
						}
						fclose($in);
						fclose($out);
						@unlink($_FILES['file']['tmp_name']);
					} else {
						die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
					}
				} catch (\Exception $ex) {
					die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
				}
			} else {
				die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
			}
		} else {
			// Open temp file
			try {
				$out = @fopen($targetDir . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
				if ($out) {
					// Read binary input stream and append it to temp file
					$in = @fopen("php://input", "rb");

					if ($in) {
						while ($buff = fread($in, 4096)) {
							fwrite($out, $buff);
						}
					} else {
						die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
					}
					fclose($in);
					fclose($out);
				} else {
					die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
				}
			} catch (\Exception $ex) {
				die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
			}
		}

		// Save the upload info.
		$_SESSION["app-uploads"][$fileId] = array("file" => $fileName, "original" => $originalName);

		// Return JSON-RPC response
		die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
	}

	/**
	 * Check if a remote file on a webserver exists.
	 *
	 * @param string $strUrl
	 * @return boolean
	 */
	public static function webFileExists($strUrl)
	{
	    $blnReturn = false;

	    $objCurl = curl_init();
	    curl_setopt($objCurl, CURLOPT_URL, $strUrl);
	    curl_setopt($objCurl, CURLOPT_HEADER, true);
	    curl_setopt($objCurl, CURLOPT_NOBODY, true);
	    curl_setopt($objCurl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	    curl_setopt($objCurl, CURLOPT_SSLVERSION, 3);
	    curl_setopt($objCurl, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($objCurl, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($objCurl, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($objCurl, CURLOPT_FOLLOWLOCATION, true);
	    curl_setopt($objCurl, CURLOPT_MAXREDIRS, 10); //follow up to 10 redirections - avoids loops

	    $strData = curl_exec($objCurl);

        $intStatusCode = curl_getinfo($objCurl, CURLINFO_HTTP_CODE);
	    if ($intStatusCode == 200) {
	        $blnReturn = true;
	    }

	    curl_close($objCurl);

	    return $blnReturn;
	}

	/**
	 * Download a file from a webserver.
	 *
	 * @param string $strUrl
	 * @return mixed
	 */
	public static function getWebFile($strUrl)
	{
	    $strReturn = null;

	    $objCurl = curl_init();
	    curl_setopt($objCurl, CURLOPT_URL, $strUrl);
        curl_setopt($objCurl, CURLOPT_HEADER, false);
	    curl_setopt($objCurl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	    curl_setopt($objCurl, CURLOPT_SSLVERSION, 3);
	    curl_setopt($objCurl, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($objCurl, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($objCurl, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($objCurl, CURLOPT_FOLLOWLOCATION, true);
	    curl_setopt($objCurl, CURLOPT_MAXREDIRS, 10); //follow up to 10 redirections - avoids loops

	    $strReturn = curl_exec($objCurl);

	    curl_close($objCurl);

	    return $strReturn;
	}
}