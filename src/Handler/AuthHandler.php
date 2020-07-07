<?php
namespace RestOnPhp\Handler;

use Doctrine\ORM\EntityManager;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Serializer\Serializer;

class AuthHandler {
    private $signer;
    private $entity;
    private $request;
    private $jwtSecret;
    private $entityManager;

    public function __construct(Serializer $serializer, EntityManager $entityManager, string $jwtSecret, string $entity, RequestStack $requestStack) {
        $this->entity = $entity;
        $this->signer = new Sha256();
        $this->jwtSecret = $jwtSecret;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
    }

    public function handle($entityClass = null) {
        $content = $this->request->getContent();
        $params = json_decode($content, true);

        if(!$params || !isset($params['username']) || !isset($params['password'])) {
            throw new UnauthorizedHttpException('username, password', 'Wrong username or password');
        }

        $user = $this->entityManager->getRepository($this->entity)->findOneBy(array(
            'username' => $params['username']
        ));

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

        return new Response(
            json_encode($token->__toString()), 
            200, 
            ['Content-Type' => 'application/json']
        );
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

        $user = $this->entityManager->getRepository($this->entity)->findOneBy(array(
            'id' => $user_id
        ));

        if(!$user) {
            throw new UnauthorizedHttpException('Unable to verify token', 'Unauthorized');
        }

        return $user;
    }
}
