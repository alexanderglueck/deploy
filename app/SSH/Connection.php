<?php

namespace App\SSH;

use Exception;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SCP;
use phpseclib3\Net\SSH2;

class Connection
{
    /** @var SSH2 */
    protected $connection = null;

    protected $ip = null;

    protected $port = null;

    protected $user = null;

    protected $password = null;

    protected $pathToPrivateKey = null;

    protected $output = null;

    protected $error = null;

    /**
     * Connection constructor.
     *
     * @param  string  $ip
     * @param  int  $port
     * @param  string  $user
     */
    public function __construct($ip, $port, $user)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->user = $user;

        return $this;
    }

    /**
     * @param  string  $pathToPrivateKey
     * @return $this
     */
    public function usingPrivateKey($pathToPrivateKey)
    {
        $this->pathToPrivateKey = $pathToPrivateKey;

        return $this;
    }

    /**
     * @param  string  $password
     * @return $this
     */
    public function usingPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return $this
     *
     * @throws Exception
     */
    public function connect()
    {
        $this->connection = new SSH2($this->ip, $this->port, 300);

        if (! $this->connection) {
            throw new Exception('Could not connect to server.');
        }

        if (! $this->password && ! $this->pathToPrivateKey) {
            throw new Exception('No authentication method set. Call usingPassword or usingPrivateKey prior to calling connect.');
        }

        if ($this->password) {
            if (! $this->connection->login($this->user, $this->password)) {
                throw new Exception('Could not login. Wrong password.');
            }
        }

        if ($this->pathToPrivateKey) {
            $key = PublicKeyLoader::load(file_get_contents($this->pathToPrivateKey));
            if (! $this->connection->login($this->user, $key)) {
                throw new Exception('Could not login. No password provided. Is the server set up?');
            }
        }

        return $this;
    }

    public function disconnect()
    {
        $this->connection->disconnect();

        $this->connection = null;
    }

    /**
     * @return bool
     */
    public function upload($localPath, $remotePath)
    {
        return (new SCP($this->connection))->put($remotePath, $localPath, SCP::SOURCE_LOCAL_FILE);
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return $this->connection != null;
    }

    /**
     * @param  callable|null  $callback
     */
    public function run($command, $callback = null)
    {
        if ($callback == null) {
            $this->output = $this->connection->exec($command, function ($str) {
                $this->output .= $str;
            });
        } else {
            $this->output = $this->connection->exec($command, $callback);
        }

        $this->error = $this->connection->getStdError();
    }

    /**
     * @return string|null
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @return string|null
     */
    public function getError()
    {
        return $this->error;
    }
}
