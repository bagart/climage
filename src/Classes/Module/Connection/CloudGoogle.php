<?php
namespace Climage\Module\Connection;

use Climage\Exception as E;

class CloudGoogle extends Cloud
{
    private $google_api_scopes = [
        \Google_Service_Drive::DRIVE
    ];

    public function getDrive()
    {
        return new \Google_Service_Drive($this->getClient());
    }

    /**
     * Returns an authorized API client.
     * @return \Google_Client the authorized client object
     */
    protected function getClient()
    {
        if (!$this->client) {
            define('TESTFILE', __DIR__ . '/composer.json');
            define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
            define('CREDENTIALS_PATH', '~/.credentials/drive-php-quickstart.json');
            
            $client = new \Google_Client();
            $client->setApplicationName($this->project_name);
            $client->setScopes(implode(' ', $this->google_api_scopes));
            $client->setAuthConfig(getenv('CLIMAGE_GOOGLE_DRIVE_TOKEN'));
            $client->setAccessType('offline');
            
            $credentialsPath = getenv('CLIMAGE_CLOUD_GOOGLE_TOKEN');
            if (!$credentialsPath) {
                throw new E\WrongParamException('CLIMAGE_CLOUD_GOOGLE_TOKEN undefined');
            }

            if (file_exists($credentialsPath)) {
                $accessToken = file_get_contents($credentialsPath);
            } else {
                echo "Open the following link in your browser:\n{$client->createAuthUrl()}\nEnter verification code: ";
                $authCode = trim(fgets(STDIN));
                $accessToken = $client->authenticate($authCode);
                if (!file_exists(dirname($credentialsPath))) {
                    mkdir(
                        dirname($credentialsPath),
                        0700, 
                        true
                    );
                }
                if (!file_exists(dirname($credentialsPath))) {
                    throw new E\WrongParamException('credentials path not created');
                }
                file_put_contents($credentialsPath, $accessToken);
                echo "Credentials saved to {$credentialsPath}\n";
            }

            $client->setAccessToken($accessToken);

            // Refresh the token if it's expired.
            if ($client->isAccessTokenExpired()) {
                $client->refreshToken($client->getRefreshToken());
                file_put_contents($credentialsPath, $client->getAccessToken());
            }
            $this->client = $client;
        }

        return $this->client;
    }

    /**
     * @param array $opts
     * @return \Google_Service_Drive_FileList
     */
    public function getFileList($opts = [])
    {
        return $this->getDrive()->files->listFiles($opts);
    }

    /**
     * @param $filename
     * @throws E\WrongParamException
     */
    public function upload($filename)
    {
        if (!file_exists($filename)) {
            throw new E\WrongParamException("input file not exists: $filename");
        }

        $file = new \Google_Service_Drive_DriveFile();
        $file->title = basename($filename);
        
        $defer =$this->getClient()->shouldDefer();
        $this->getClient()->setDefer(true);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $filename);
        finfo_close($finfo);

        $media = new \Google_Http_MediaFileUpload(
            $this->getClient(),
            $this->getDrive()->files->insert($file),
            $mime_type,
            null,
            true,
            $this->chunk
        );

        $media->setFileSize(filesize($filename));

        $result = false;
        $handle = fopen($filename, "rb");
        while (!$result && !feof($handle)) {
            $chunk = $this->readHandleChunk($handle);
            $result = $media->nextChunk($chunk);
        }
        fclose($handle);
        
        $this->getClient()->setDefer($defer);

        return $result;
    }

    /**
     * @param $filename
     * @throws E\WrongParamException
     */
    public function upload_v2($filename)
    {
        if (!file_exists($filename)) {
            throw new E\WrongParamException("input file not exists: $filename");
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $filename);
        finfo_close($finfo);
                
        $file = new \Google_Service_Drive_DriveFile();
        $file->setName(basename($filename));
        $file->setMimeType($mime_type);

        $this->getDrive()->files
            ->create(
                $file,
                array(
                    'data' => file_get_contents($filename),
                    'postBody' => file_get_contents($filename),
                    'mimeType' => $mime_type
                )
            );
    }
}