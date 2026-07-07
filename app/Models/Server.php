<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use App\SSH\Connection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use phpseclib3\Crypt\EC;

class Server extends Model
{
    use HasFactory;
    use HasPublicUlid;

    public const TYPE_LOCAL = 'local';

    public const TYPE_SSH = 'ssh';

    protected $fillable = [
        'name',
        'type',
        'user',
        'ip',
        'port',
        'setup_at',
    ];

    protected $attributes = [
        'type' => self::TYPE_SSH,
        'user' => 'root',
        'port' => 22,
    ];

    /**
     * The private key never leaves the backend.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'team_id',
        'private_key',
    ];

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
        ];
    }

    public function isLocal(): bool
    {
        return $this->type === self::TYPE_LOCAL;
    }

    /**
     * @return bool
     */
    public function isSetUp()
    {
        // Local servers need no key exchange.
        return $this->isLocal() || $this->setup_at != null;
    }

    /**
     * Install this server's public key by logging in with a password once.
     * Alternative to adding the key to authorized_keys manually.
     *
     * @throws \Exception
     */
    public function copyPublicKey($password)
    {
        if ($this->isLocal()) {
            return;
        }

        $connection = (new Connection($this->ip, $this->port, $this->user))
            ->usingPassword($password)
            ->connect();

        $remotePublicFile = '/tmp/deploy-public-key-'.md5($this->public_key);

        // Upload public key
        $connection->uploadContent($this->public_key."\n", $remotePublicFile);

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

    protected static function booted()
    {
        // Every SSH server gets its own keypair, so revoking one server never
        // affects another and no key is ever shared between installations.
        static::creating(function (Server $server) {
            if ($server->type === self::TYPE_SSH && ! $server->private_key) {
                $key = EC::createKey('Ed25519');

                $server->private_key = $key->toString('OpenSSH');
                $server->public_key = $key->getPublicKey()->toString('OpenSSH', ['comment' => 'deploy']);
            }
        });
    }
}
