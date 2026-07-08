<?php

namespace App\SSH;

use Exception;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class Connection
{
    /** @var SSH2 */
    protected $connection = null;

    protected $ip = null;

    protected $port = null;

    protected $user = null;

    protected $timeout = null;

    protected $password = null;

    protected $privateKey = null;

    protected $output = null;

    protected $error = null;

    /**
     * Connection constructor.
     *
     * @param  string  $ip
     * @param  int  $port
     * @param  string  $user
     * @param  int  $timeout
     */
    public function __construct($ip, $port, $user, $timeout = 300)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->user = $user;
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param  string  $privateKey  the key material itself (OpenSSH format)
     * @return $this
     */
    public function usingPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;

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
        $this->connection = new SSH2($this->ip, $this->port, $this->timeout);

        if (! $this->connection) {
            throw new Exception('Could not connect to server.');
        }

        if (! $this->password && ! $this->privateKey) {
            throw new Exception('No authentication method set. Call usingPassword or usingPrivateKey prior to calling connect.');
        }

        if ($this->password) {
            if (! $this->connection->login($this->user, $this->password)) {
                throw new Exception('Could not login. Wrong password.');
            }
        }

        if ($this->privateKey) {
            $key = PublicKeyLoader::load($this->privateKey);
            if (! $this->connection->login($this->user, $key)) {
                throw new Exception('Could not login with the server\'s key. Is the server set up?');
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
     * The exit status of the last command, or false if the server did not
     * report one.
     *
     * @return int|false
     */
    public function getExitStatus()
    {
        return $this->connection->getExitStatus();
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
