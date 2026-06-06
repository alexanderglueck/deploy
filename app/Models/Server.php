<?php

namespace App\Models;

use App\SSH\Connection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user',
        'ip',
        'port',
        'setup_at',
    ];

    protected $attributes = [
        'user' => 'root',
        'port' => 22,
    ];

    /**
     * @return bool
     */
    public function isSetUp()
    {
        return $this->setup_at != null;
    }

    /**
     * @throws \Exception
     */
    public function copyPublicKey($password)
    {
        $connection = (new Connection($this->ip, $this->port, $this->user))
            ->usingPassword($password)
            ->connect();

        $remotePublicFile = '/tmp/deploy-public-key-'.md5('temp_id_rsa.pub');

        // Upload public key
        $connection->upload(storage_path('app/temp_id_rsa.pub'), $remotePublicFile);

        // Add public key to authorized_keys
        $connection->run('mkdir -p ~/.ssh && touch ~/.ssh/authorized_keys');
        $connection->run("cat $remotePublicFile >> ~/.ssh/authorized_keys");
        $connection->run('chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys');
        $connection->run("chown -R {$this->user}:{$this->user} ~/.ssh");

        // Remove public key
        $connection->run("rm $remotePublicFile");

        $connection->disconnect();

        $this->update([
            'setup_at' => Carbon::now(),
        ]);
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    public function execute(Deployment $deployment)
    {
        $connection = (new Connection($this->ip, $this->port, $this->user))
            ->usingPrivateKey(storage_path('app/temp_id_rsa'))
            ->connect();

        $commands = explode("\r\n", $deployment->actions);

        $newCmds = [];
        foreach ($commands as $cmd) {
            $newCmds[] = $cmd;
        }

        $newCmd = implode("\n", $newCmds);

        $connection->run($newCmd, function ($str) use ($deployment) {
            $log = $deployment->log;

            $log->update([
                'log' => $log->log.$str,
            ]);
        });

        $output = '';

        $commandOutput = $connection->getOutput();
        if (trim($commandOutput) !== '') {
            $output .= $commandOutput."\n";
        }

        $commandError = $connection->getError();
        if (trim($commandError) !== '') {
            $log = $deployment->log;

            $log->update([
                'log' => $log->log.'ERROR: '.$commandError,
            ]);
        }

        $connection->disconnect();

        return $output;
    }

    /**
     * @return BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany
     */
    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }
}
