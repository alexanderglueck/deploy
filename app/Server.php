<?php

namespace App;

use App\SSH\Connection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $fillable = [
        'name',
        'user',
        'ip',
        'port',
        'setup_at'
    ];

    protected $attributes = [
        'user' => 'root',
        'port' => 22
    ];

    /**
     * @return bool
     */
    public function isSetUp()
    {
        return $this->setup_at != null;
    }

    /**
     * @param $password
     * @throws \Exception
     */
    public function copyPublicKey($password)
    {
        $connection = (new Connection($this->ip, $this->port, $this->user))
            ->usingPassword($password)
            ->connect();

        $remotePublicFile = '/tmp/deploy-public-key-' . md5('temp_id_rsa.pub');

        // Upload public key
        $connection->upload(storage_path('app/temp_id_rsa.pub'), $remotePublicFile);

        // Add public key to authorized_keys
        $connection->run("mkdir -p ~/.ssh && touch ~/.ssh/authorized_keys");
        $connection->run("cat $remotePublicFile >> ~/.ssh/authorized_keys");
        $connection->run("chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys");
        $connection->run("chown -R {$this->user}:{$this->user} ~/.ssh");

        // Remove public key
        $connection->run("rm $remotePublicFile");

        $connection->disconnect();

        $this->update([
            'setup_at' => Carbon::now()
        ]);
    }

    /**
     * @param Deployment $deployment
     * @return string
     * @throws \Exception
     */
    public function execute(Deployment $deployment)
    {
        \Illuminate\Support\Facades\Log::error('Pre connect');

        $connection = (new Connection($this->ip, $this->port, $this->user))
            ->usingPrivateKey(storage_path('app/temp_id_rsa'))
            ->connect();

        \Illuminate\Support\Facades\Log::error('Post connect');

        $output = '';

        $commands = explode("\r\n", $deployment->actions);

        $newCmds = [];
        foreach ($commands as $cmd) {
            $newCmds[] = $cmd;
        }

        $newCmd = implode("\n", $newCmds);

        \Illuminate\Support\Facades\Log::error('Commands: ', [
            'newCmd' => $newCmd
        ]);

        \Illuminate\Support\Facades\Log::error('Pre run');

        $connection->run($newCmd, function ($str) use ($deployment) {
            $log = $deployment->log;

            \Illuminate\Support\Facades\Log::error('In run', [
                'log' => $str
            ]);

            $log->update([
                'log' => $log->log . $str
            ]);
        });

        \Illuminate\Support\Facades\Log::error('Post run');

        $commandOutput = $connection->getOutput();
        if (trim($commandOutput) !== '') {
            $output .= $commandOutput . "\n";
        }

        $commandError = $connection->getError();
        if (trim($commandError) !== '') {
            $log = $deployment->log;

            $log->update([
                'log' => $log->log . $commandError
            ]);
        }

        $connection->disconnect();

        return $output;
    }

    private function log($string)
    {
        $this->log = $this->log . $string;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }
}
