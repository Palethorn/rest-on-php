<?php
namespace RestOnPhp\Handler;

use Doctrine\ORM\EntityManager;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use RestOnPhp\Handler\Response\HandlerResponse;
use RestOnPhp\Metadata\XmlMetadata;
use RestOnPhp\Normalizer\RootNormalizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthHandler {
    private $signer;
    private $entity;
    private $request;
    private $jwtSecret;
    private $entityManager;
    private $normalizer;
    private $xmlMetadata;

    public function __construct(
        EntityManager $entityManager, 
        string $jwtSecret, 
        string $entity, 
        RequestStack $requestStack,
        RootNormalizer $normalizer,
        XmlMetadata $xmlMetadata
    ) {
        $this->entity = $entity;
        $this->signer = new Sha256();
        $this->jwtSecret = $jwtSecret;
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->normalizer = $normalizer;
        $this->xmlMetadata = $xmlMetadata;
    }

    public function handle($entityClass = null) {
        $content = $this->request->getContent();
        $params = json_decode($content, true);

        if(!$params || !isset($params['username']) || !isset($params['password'])) {
            throw new UnauthorizedHttpException('username, password', 'Wrong username or password');
        }

        $user = $this->entityManager->getRepository($this->entity)->findOneBy([
            'username' => $params['username']
        ]);

        if(!$user) {
            throw new UnauthorizedHttpException('username, password', 'Wrong username or password');
        }

        if(!password_verify($params['password'], $user->getPassword())) {
            throw new UnauthorizedHttpException('username, password', 'Wrong username or password');
        }
        
        $token = (new Builder())
            ->issuedAt(time())
            ->withClaim('id', $user->getId())
            ->getToken($this->signer, new Key($this->jwtSecret));

        $user->setToken($token->__toString());

        $normalized = $this->normalizer->normalizeItem($user, $this->xmlMetadata->getMetadataFor('current_user'));

        return new HandlerResponse(HandlerResponse::CARDINALITY_NONE, $normalized);
    }

    /**
     * @return \RestOnPhp\Security\SecureUser
     */
    public function verify($token) {
        if(!$token) {
            throw new UnauthorizedHttpException('Unable to verify token', 'Unauthorized');
        }

        $token = (new Parser())->parse($token);

        if(!$token->verify($this->signer, $this->jwtSecret)) {
            throw new UnauthorizedHttpException('Unable to verify token', 'Unauthorized');
        }

        $user_id = $token->getClaim('id');

        $user = $this->entityManager->getRepository($this->entity)->findOneBy([
            'id' => $user_id
        ]);

        if(!$user) {
            throw new UnauthorizedHttpException('Unable to verify token', 'Unauthorized');
        }

        return $user;
    }
}
