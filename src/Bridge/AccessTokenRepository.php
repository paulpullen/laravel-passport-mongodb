<?php

namespace MoeenBasra\LaravelPassportMongoDB\Bridge;

use DateTime;
use Illuminate\Database\Connection;
use Illuminate\Contracts\Events\Dispatcher;
use MoeenBasra\LaravelPassportMongoDB\Events\AccessTokenCreated;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use MongoDB\BSON\UTCDateTime;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    use FormatsScopesForStorage;

    /**
     * The database connection.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $database;

    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Events\Dispatcher
     */
    private $events;

    /**
     * Create a new repository instance.
     *
     * @param  \Illuminate\Database\Connection  $database
     * @return void
     */
    public function __construct(Connection $database, Dispatcher $events)
    {
        $this->events = $events;
        $this->database = $database;
    }

    /**
     * {@inheritdoc}
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        return new AccessToken($userIdentifier, $scopes);
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $this->database->table('oauth_access_tokens')->insert([
            'id' => $id = $accessTokenEntity->getIdentifier(),
            'user_id' => $userId = $accessTokenEntity->getUserIdentifier(),
            'client_id' => $clientId = $accessTokenEntity->getClient()->getIdentifier(),
            'scopes' => $this->formatScopesForStorage($accessTokenEntity->getScopes()),
            'revoked' => false,
            'created_at' => new UTCDateTime((new \DateTime())->getTimestamp() * 1000),
            'updated_at' => new UTCDateTime((new \DateTime())->getTimestamp() * 1000),
            'expires_at' => new UTCDateTime(($accessTokenEntity->getExpiryDateTime())->getTimestamp() * 1000),
        ]);

        $this->events->fire(new AccessTokenCreated($id, $userId, $clientId));
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAccessToken($tokenId)
    {
        $this->database->table('oauth_access_tokens')
                    ->where('id', $tokenId)->update(['revoked' => true]);
    }

    /**
     * {@inheritdoc}
     */
    public function isAccessTokenRevoked($tokenId)
    {
        return ! $this->database->table('oauth_access_tokens')
                    ->where('id', $tokenId)->where('revoked', false)->exists();
    }
}
