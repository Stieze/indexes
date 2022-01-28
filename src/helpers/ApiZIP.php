<?php

namespace App\Helper;

use ZipArchive;

class ApiZIP
{
    /**
     * @param string $url to download zip
     * @return void
     */
    public function download(string $url): void {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec ($ch);
        curl_close ($ch);
        $destination = getenv("TEMP_FOLDER") . getenv("FILE_NAME") . ".zip";
        $file = fopen($destination, "w+");
        fputs($file, $data);
        fclose($file);
    }

    /**
     * Unzip downloaded file to temp folder
     * @return void
     */
    public function unZip(): void {
        $zip = new ZipArchive;
        $res = $zip->open(getenv("TEMP_FOLDER") . getenv("FILE_NAME").".zip");
        if ($res === TRUE) {
            $zip->extractTo(getenv("TEMP_FOLDER")); // directory to extract contents to
            $zip->close();
            unlink(getenv("TEMP_FOLDER") . getenv("FILE_NAME") . ".zip");
        }
    }
}