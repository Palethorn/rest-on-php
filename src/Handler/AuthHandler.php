<?php
namespace RestOnPhp\Handler;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use RestOnPhp\Handler\Response\HandlerResponse;
use RestOnPhp\Metadata\XmlMetadata;
use RestOnPhp\Normalizer\RootNormalizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthHandler implements HandlerInterface {
    private $signer;
    private $entity;
    private $request;
    private $jwtConfiguration;
    private $entityManager;
    private $normalizer;
    private $xmlMetadata;

    public function __construct(
        EntityManager $entityManager, 
        Configuration $jwtConfiguration, 
        string $entity, 
        RequestStack $requestStack,
        RootNormalizer $normalizer,
        XmlMetadata $xmlMetadata
    ) {
        $this->entity = $entity;
        $this->jwtConfiguration = $jwtConfiguration;
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
        
        $token = $this->jwtConfiguration
            ->builder()
            ->issuedAt(new DateTimeImmutable())
            ->withClaim('id', $user->getId())
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());


        $user->setToken($token->toString());

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

        $token = $this->jwtConfiguration->parser()->parse($token);

        if(!$this->jwtConfiguration->validator()->validate(
            $token, 
            new SignedWith(
                $this->jwtConfiguration->signer(), 
                $this->jwtConfiguration->signingKey()
            )
        )) {
            throw new UnauthorizedHttpException('Unable to verify token', 'Unauthorized');
        }

        $user_id = $token->claims()->get('id');

        $user = $this->entityManager->getRepository($this->entity)->findOneBy([
            'id' => $user_id
        ]);

        if(!$user) {
            throw new UnauthorizedHttpException('Unable to verify token', 'Unauthorized');
        }

        return $user;
    }

    public function setFilters($filters) {

    }

    public function setFillers($filters) {
        
    }
}
